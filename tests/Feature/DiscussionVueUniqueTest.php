<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiscussionVueUniqueTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Couple $couple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice', 'gender' => 'Femme']);
        $this->bob = User::factory()->create(['name' => 'Bob', 'gender' => 'Homme']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->alice->id,
            'user2_id' => $this->bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->alice->forceFill(['couple_id' => $this->couple->id])->save();
        $this->bob->forceFill(['couple_id' => $this->couple->id])->save();

        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Envoie une photo vue unique : l'upload reste sur le disque public, c'est
     * `send()` qui la transfère vers le disque privé au moment de l'envoi.
     */
    private function envoyerPhotoVueUnique(string $body = ''): Message
    {
        $upload = $this->actingAs($this->alice)
            ->postJson(route('discussion.photo'), [
                'photo' => UploadedFile::fake()->image('secret.jpg'),
            ])
            ->assertOk()
            ->json();

        $this->assertNotNull($upload['url']);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => $upload['path'],
                'photo_w' => 800,
                'photo_h' => 600,
                'view_once' => 1,
                'body' => $body,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        return Message::where('couple_id', $this->couple->id)
            ->whereNotNull('photo_path')
            ->firstOrFail();
    }

    /**
     * Envoie une vidéo vue unique (la miniature est un upload séparé, comme le
     * fait le client : poster puis vidéo, tous deux publics avant l'envoi).
     */
    private function envoyerVideoVueUnique(bool $avecPoster = false): Message
    {
        $posterPath = null;
        if ($avecPoster) {
            $poster = $this->actingAs($this->alice)
                ->postJson(route('discussion.video'), [
                    'poster' => UploadedFile::fake()->image('poster.jpg'),
                ])
                ->assertOk()
                ->json();
            $posterPath = $poster['poster_path'];
        }

        $video = $this->actingAs($this->alice)
            ->postJson(route('discussion.video'), [
                'video' => $this->petiteVideo('secret.mp4'),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), array_filter([
                'video_path' => $video['path'],
                'video_poster_path' => $posterPath,
                'video_w' => 640,
                'video_h' => 360,
                'view_once' => 1,
            ], fn ($v) => $v !== null))
            ->assertOk()
            ->assertJson(['ok' => true]);

        return Message::where('couple_id', $this->couple->id)
            ->whereNotNull('video_path')
            ->firstOrFail();
    }

    /**
     * Vrai fichier mp4 minimal : les "fake" de Laravel ne contiennent que des
     * zéros, finfo ne détecterait pas video/mp4 et la règle mimes rejeterait.
     * (Même construction que DiscussionFlowTest::smallMp4.)
     */
    private function petiteVideo(string $name, int $kilobytes = 64): UploadedFile
    {
        $header = "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41";

        $temp = tempnam(sys_get_temp_dir(), 'video');
        file_put_contents($temp, $header.str_repeat("\0", max(0, $kilobytes * 1024 - strlen($header))));

        return new UploadedFile($temp, $name, 'video/mp4', null, true);
    }

    public function test_l_envoi_vue_unique_transfere_la_photo_sur_le_disque_prive(): void
    {
        $upload = $this->actingAs($this->alice)
            ->postJson(route('discussion.photo'), [
                'photo' => UploadedFile::fake()->image('secret.jpg'),
            ])
            ->assertOk()
            ->json();

        // Avant envoi : le fichier est sur le disque public (aucun ré-envoi
        // n'aura lieu — c'est `send()` qui déplace le fichier).
        Storage::disk('public')->assertExists($upload['path']);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => $upload['path'],
                'photo_w' => 800,
                'photo_h' => 600,
                'view_once' => 1,
            ])
            ->assertOk();

        $message = Message::where('couple_id', $this->couple->id)->firstOrFail();

        $this->assertStringStartsWith('discussion-photos-once/', $message->photo_path);
        Storage::disk('local')->assertExists($message->photo_path);
        Storage::disk('public')->assertMissing($upload['path']);
        Storage::disk('public')->assertDirectoryEmpty('discussion-photos');
    }

    public function test_fetch_ne_montre_jamais_l_url_de_la_photo_vue_unique(): void
    {
        $message = $this->envoyerPhotoVueUnique();

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $fetch['messages']);
        $this->assertTrue($fetch['messages'][0]['view_once']);
        $this->assertFalse($fetch['messages'][0]['view_once_viewed']);
        $this->assertTrue($fetch['messages'][0]['is_photo']);
        $this->assertNull($fetch['messages'][0]['photo_url']);
        $this->assertNotNull($message->photo_path);
    }

    public function test_le_destinataire_ouvre_la_photo_une_seule_fois(): void
    {
        $message = $this->envoyerPhotoVueUnique();

        // Première ouverture : le fichier est servi et viewed_at est posé.
        $this->actingAs($this->bob)
            ->get(route('discussion.media.once', ['id' => $message->id]))
            ->assertOk();
        $this->assertNotNull($message->fresh()->viewed_at);

        // Seconde ouverture : 410 Gone.
        $this->actingAs($this->bob)
            ->getJson(route('discussion.media.once', ['id' => $message->id]))
            ->assertStatus(410);
    }

    public function test_l_exp_editeur_ne_peut_pas_ouvrir_sa_propre_vue_unique(): void
    {
        $message = $this->envoyerPhotoVueUnique();

        $this->actingAs($this->alice)
            ->getJson(route('discussion.media.once', ['id' => $message->id]))
            ->assertStatus(403);
        $this->assertNull($message->fresh()->viewed_at);
    }

    public function test_un_autre_couple_ne_peut_pas_ouvrir_la_vue_unique(): void
    {
        $message = $this->envoyerPhotoVueUnique();

        $carole = User::factory()->create(['name' => 'Carole', 'gender' => 'Femme']);
        $dan = User::factory()->create(['name' => 'Dan', 'gender' => 'Homme']);
        $autre = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $carole->id,
            'user2_id' => $dan->id,
            'streak' => 0,
            'score_total' => 0,
        ]);
        $carole->forceFill(['couple_id' => $autre->id])->save();

        $this->actingAs($carole)
            ->getJson(route('discussion.media.once', ['id' => $message->id]))
            ->assertStatus(404);
    }

    public function test_le_drapeau_view_once_sans_photo_ni_video_est_rejete(): void
    {
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'body' => 'Juste du texte',
                'view_once' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'La vue unique s\'applique aux photos et vidéos.']);

        // Un GIF n'est pas un média ouvrable une seule fois non plus.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'gif_url' => 'https://example.com/sticker.gif',
                'view_once' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'La vue unique s\'applique aux photos et vidéos.']);
    }

    public function test_un_chemin_media_invalide_est_rejete(): void
    {
        Storage::disk('public')->put('discussion-photos/normal.jpg', 'bytes');
        Storage::disk('local')->put('discussion-photos-once/once.jpg', 'bytes');

        // Chemin privé + drapeau absent : le disque public n'a pas ce fichier
        // (fantomatique côté privé).
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => 'discussion-photos-once/once.jpg',
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Photo introuvable.']);

        // Chemin inexistant + drapeau : rien à transférer.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => 'discussion-photos/inconnu.jpg',
                'view_once' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Photo introuvable.']);

        // Fichier hors du dossier média + drapeau : aucun transfert d'un
        // fichier étranger (avatar…) vers le dossier privé de la discussion.
        Storage::disk('public')->put('avatars/leak.jpg', 'bytes');
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => 'avatars/leak.jpg',
                'view_once' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Photo introuvable.']);
    }

    public function test_une_photo_normale_n_est_pas_soumise_a_la_vue_unique(): void
    {
        $upload = $this->actingAs($this->alice)
            ->postJson(route('discussion.photo'), [
                'photo' => UploadedFile::fake()->image('normal.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('view_once', null);

        $this->assertNotNull($upload->json('url'));
        Storage::disk('public')->assertExists($upload->json('path'));

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'photo_path' => $upload->json('path'),
                'photo_w' => 800,
                'photo_h' => 600,
            ])
            ->assertOk();

        $message = Message::where('couple_id', $this->couple->id)->firstOrFail();
        Storage::disk('public')->assertExists($message->photo_path);

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertFalse($fetch['messages'][0]['view_once']);
        $this->assertNotNull($fetch['messages'][0]['photo_url']);
    }

    public function test_une_video_vue_unique_est_transferee_sur_le_disque_prive(): void
    {
        $poster = $this->actingAs($this->alice)
            ->postJson(route('discussion.video'), [
                'poster' => UploadedFile::fake()->image('poster.jpg'),
            ])
            ->assertOk()
            ->json();
        $video = $this->actingAs($this->alice)
            ->postJson(route('discussion.video'), [
                'video' => $this->petiteVideo('secret.mp4'),
            ])
            ->assertOk()
            ->json();

        Storage::disk('public')->assertExists($video['path']);
        Storage::disk('public')->assertExists($poster['poster_path']);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'video_path' => $video['path'],
                'video_poster_path' => $poster['poster_path'],
                'video_w' => 640,
                'video_h' => 360,
                'view_once' => 1,
            ])
            ->assertOk();

        $message = Message::where('couple_id', $this->couple->id)->firstOrFail();

        $this->assertTrue($message->view_once);
        $this->assertStringStartsWith('discussion-videos-once/', $message->video_path);
        $this->assertStringStartsWith('discussion-video-posters-once/', $message->video_poster_path);
        Storage::disk('local')->assertExists($message->video_path);
        Storage::disk('local')->assertExists($message->video_poster_path);
        Storage::disk('public')->assertMissing($video['path']);
        Storage::disk('public')->assertMissing($poster['poster_path']);

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $fetch['messages']);
        $this->assertTrue($fetch['messages'][0]['view_once']);
        $this->assertTrue($fetch['messages'][0]['is_video']);
        $this->assertFalse($fetch['messages'][0]['view_once_viewed']);
        $this->assertNull($fetch['messages'][0]['video_url']);
        $this->assertNull($fetch['messages'][0]['video_poster_url']);
    }

    public function test_le_destinataire_ouvre_la_video_une_seule_fois(): void
    {
        $message = $this->envoyerVideoVueUnique();

        $this->actingAs($this->bob)
            ->get(route('discussion.media.once', ['id' => $message->id]))
            ->assertOk();
        $this->assertNotNull($message->fresh()->viewed_at);

        $this->actingAs($this->bob)
            ->getJson(route('discussion.media.once', ['id' => $message->id]))
            ->assertStatus(410);

        // L'expéditeur reste bloqué même après l'ouverture par le destinataire.
        $this->actingAs($this->alice)
            ->getJson(route('discussion.media.once', ['id' => $message->id]))
            ->assertStatus(403);
    }

    public function test_la_citation_d_une_vue_unique_ne_revele_leur_media(): void
    {
        $video = $this->envoyerVideoVueUnique(avecPoster: true);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'body' => 'Regarde ça',
                'reply_to_id' => $video->id,
            ])
            ->assertOk();

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $reply = collect($fetch['messages'])->firstWhere('body', 'Regarde ça')['reply_to'];
        $this->assertTrue($reply['view_once']);
        $this->assertTrue($reply['is_video']);
        $this->assertNull($reply['video_url']);
        $this->assertNull($reply['video_poster_url']);
    }

    public function test_le_delta_vus_signale_a_l_expe_diteur_que_sa_vue_unique_a_ete_ouverte(): void
    {
        $message = $this->envoyerPhotoVueUnique();

        // Premier fetch (Alice) : aucun vus, curseur courant renvoyé.
        $premier = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertSame([], $premier['vus']);
        $this->assertNull($premier['curseur']['vus']);

        // Bob ouvre la photo.
        $this->actingAs($this->bob)
            ->get(route('discussion.media.once', ['id' => $message->id]))
            ->assertOk();

        // Deuxième fetch (Alice) : le message ouvert arrive dans `vus`.
        $second = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch', ['vusSince' => $premier['curseur']['vus'] ?? '']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $second['vus']);
        $this->assertSame($message->id, $second['vus'][0]['id']);
        $this->assertTrue($second['vus'][0]['view_once_viewed']);

        // Un troisième fetch sans nouvelle ouverture ne rejoue pas le delta.
        $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch', ['vusSince' => $second['curseur']['vus']]))
            ->assertOk()
            ->assertJsonPath('vus', []);
    }
}
