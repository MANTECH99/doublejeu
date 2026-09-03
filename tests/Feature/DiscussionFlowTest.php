<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\GifFavorite;
use App\Models\Message;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class DiscussionFlowTest extends TestCase
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
    }

    public function test_partners_can_send_and_read_private_messages(): void
    {
        // Alice écrit un message.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Coucou, on dîne ce soir ?'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'couple_id' => $this->couple->id,
            'sender_id' => $this->alice->id,
            'body' => 'Coucou, on dîne ce soir ?',
        ]);

        // Bob reçoit le message, non lu, puis il le lit.
        $bobFetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $bobFetch['messages']);
        $this->assertSame('Coucou, on dîne ce soir ?', $bobFetch['messages'][0]['body']);
        $this->assertSame('Alice', $bobFetch['messages'][0]['sender_name']);
        $this->assertTrue($bobFetch['messages'][0]['lu']);

        // Le statut du partenaire est renvoyé : Alice est en ligne (activité touchée).
        $this->assertTrue($bobFetch['partenaire']['enLigne']);
        $this->assertTrue($bobFetch['partenaire']['present']);

        // Le lecteur reçoit la confirmation "✓✓" côté Alice.
        $aliceFetch = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertTrue($aliceFetch['messages'][0]['lu']);
    }

    public function test_fetch_incrementally_returns_only_new_messages(): void
    {
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Un']);
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Deux']);
        $third = Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Trois']);

        $this->actingAs($this->bob);

        // Rien après le dernier id.
        $empty = $this->getJson(route('discussion.fetch').'?after='.$third->id)->assertOk()->json();
        $this->assertCount(0, $empty['messages']);

        // Seuls les premiers visibles via after.
        $partial = $this->getJson(route('discussion.fetch').'?after=2')->assertOk()->json();
        $this->assertCount(1, $partial['messages']);
        $this->assertSame('Trois', $partial['messages'][0]['body']);
    }

    public function test_fetch_returns_all_messages_in_chronological_order_on_initial_load(): void
    {
        // Tout l'historique est renvoyé au chargement initial, du plus ancien au plus récent.
        for ($i = 1; $i <= 110; $i++) {
            Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'm-'.$i]);
        }

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(110, $fetch['messages']);
        $this->assertSame('m-1', $fetch['messages'][0]['body'], 'Le plus ancien message est attendu en premier.');
        $this->assertSame('m-110', $fetch['messages'][109]['body'], 'Le tout dernier message doit être présent.');
    }

    public function test_partner_can_reply_to_a_message(): void
    {
        // Alice écrit un message auquel Bob répondra.
        $base = $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Rendez-vous à 20h ?'])
            ->assertOk()
            ->json();

        // Bob répond en citant ce message.
        $this->actingAs($this->bob)
            ->postJson(route('discussion.send'), [
                'body' => 'Parfait pour moi !',
                'reply_to_id' => $base['id'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'couple_id' => $this->couple->id,
            'sender_id' => $this->bob->id,
            'body' => 'Parfait pour moi !',
            'reply_to_id' => $base['id'],
        ]);

        // On ne peut pas répondre à un message d'un autre couple.
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $other = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $u1->id,
            'user2_id' => $u2->id,
            'streak' => 0,
            'score_total' => 0,
        ]);
        $foreign = Message::create(['couple_id' => $other->id, 'sender_id' => $u1->id, 'body' => 'très loin']);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'body' => 'On ne sait pas',
                'reply_to_id' => $foreign->id,
            ])
            ->assertStatus(422);

        // Le fetch renvoie le message cité avec son contenu.
        $fetch = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $reply = collect($fetch['messages'])->firstWhere('body', 'Parfait pour moi !');
        $this->assertNotNull($reply['reply_to']);
        $this->assertSame('Rendez-vous à 20h ?', $reply['reply_to']['body']);
        $this->assertSame('Alice', $reply['reply_to']['sender_name']);
    }

    public function test_partner_sees_typing_indicator_while_other_is_typing(): void
    {
        // Personne ne tape au départ.
        $idle = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertFalse($idle['partenaire']['typing']);

        // Alice tape juste maintenant.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.typing'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $whileTyping = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertTrue($whileTyping['partenaire']['typing']);

        // L'indicateur expire après 3 secondes d'inactivité.
        $this->travel(4)->seconds();
        $expired = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertFalse($expired['partenaire']['typing']);
    }

    public function test_partners_can_send_and_receive_gif_messages(): void
    {
        // Alice envoie un message GIF.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'gif_url' => 'https://media.giphy.com/media/xyz/giphy.gif',
                'gif_alt' => 'Petit chat mignon',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'couple_id' => $this->couple->id,
            'gif_url' => 'https://media.giphy.com/media/xyz/giphy.gif',
        ]);

        // Bob reçoit le GIF avec ses métadonnées.
        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $gif = $fetch['messages'][0];
        $this->assertTrue($gif['is_gif']);
        $this->assertSame('https://media.giphy.com/media/xyz/giphy.gif', $gif['gif_url']);
        $this->assertSame('Petit chat mignon', $gif['gif_alt']);

        // Message vide sans GIF refusé, et GIF + légende accepté.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => '', 'gif_url' => null])
            ->assertStatus(422);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Regarde ça !', 'gif_url' => 'https://media.giphy.com/media/abc/giphy.gif'])
            ->assertOk();
    }

    public function test_giphy_proxy_endpoint_returns_gifs(): void
    {
        Http::fake([
            'api.giphy.com/*' => Http::response([
                'data' => [
                    [
                        'id' => 'abc',
                        'title' => 'Funny cat',
                        'alt_text' => 'Funny cat',
                        'images' => [
                            'original' => ['url' => 'https://media.giphy.com/media/abc/giphy.gif'],
                            'downsized' => ['url' => 'https://media.giphy.com/media/abc/downsized.gif'],
                        ],
                    ],
                ],
            ]),
        ]);

        config(['services.giphy.key' => 'fake-key']);

        $res = $this->actingAs($this->alice)
            ->getJson(route('discussion.gifs').'?q=chat')
            ->assertOk()
            ->json();

        $this->assertCount(1, $res['gifs']);
        $this->assertSame('https://media.giphy.com/media/abc/giphy.gif', $res['gifs'][0]['url']);
        $this->assertSame('https://media.giphy.com/media/abc/downsized.gif', $res['gifs'][0]['preview']);

        // Sans clé configurée → 503 avec un message clair.
        config(['services.giphy.key' => null]);
        $this->actingAs($this->alice)
            ->getJson(route('discussion.gifs').'?q=chat')
            ->assertStatus(503);
    }

    public function test_couples_can_favorite_and_unfavorite_gifs(): void
    {
        $url = 'https://media.giphy.com/media/xyz/giphy.gif';

        $this->assertSame((int) $this->alice->coupleModel->id, (int) $this->bob->coupleModel->id);

        // Liste vide au départ.
        $empty = $this->actingAs($this->bob)
            ->getJson(route('discussion.favorites'))
            ->assertOk()
            ->json();
        $this->assertCount(0, $empty['favorites']);

        // Alice ajoute un favori → renvoyé en favori avec la liste à jour.
        $added = $this->actingAs($this->alice)
            ->postJson(route('discussion.favorites.toggle'), ['gif_url' => $url, 'gif_alt' => 'Chat mignon'])
            ->assertOk()
            ->json();
        $this->assertTrue($added['favorite']);
        $this->assertCount(1, $added['favorites']);
        $this->assertSame($url, $added['favorites'][0]['url']);

        // Le partenaire (même couple) voit ce favori.
        $list = $this->actingAs($this->bob)
            ->getJson(route('discussion.favorites'))
            ->assertOk()
            ->json();
        $this->assertCount(1, $list['favorites']);
        $this->assertSame('Chat mignon', $list['favorites'][0]['alt']);

        // Un deuxième toggle sur la même URL retire le favori (aller-retour).
        $removed = $this->actingAs($this->alice)
            ->postJson(route('discussion.favorites.toggle'), ['gif_url' => $url, 'gif_alt' => 'Chat mignon'])
            ->assertOk()
            ->json();
        $this->assertFalse($removed['favorite']);
        $this->assertCount(0, $removed['favorites']);
    }

    public function test_gif_favorite_is_unique_per_couple_and_url(): void
    {
        $url = 'https://media.giphy.com/media/xyz/giphy.gif';

        $this->actingAs($this->alice)
            ->postJson(route('discussion.favorites.toggle'), ['gif_url' => $url])
            ->assertOk();

        // La contrainte unique (couple_id, gif_url) empêche tout doublon en base.
        $this->expectExceptionMessageMatches('/UNIQUE/i');
        GifFavorite::create([
            'couple_id' => $this->alice->coupleModel->id,
            'gif_url' => $url,
        ]);
    }

    public function test_validation_and_authorization(): void
    {

        // Contenu vide refusé.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => '   '])
            ->assertStatus(422);
        // Un utilisateur non lié ne peut pas accéder à la discussion.
        $solo = User::factory()->create();
        $this->actingAs($solo)
            ->get(route('discussion.index'))
            ->assertRedirect(route('couple.setup'));

        // Page rendue pour un couple lié.
        $this->actingAs($this->alice)
            ->get(route('discussion.index'))
            ->assertOk()
            ->assertSee('disc-messages', false);
    }

    public function test_message_push_carries_unread_badge_count_for_partner(): void
    {
        // Bob a déjà 2 messages non lus (il n'a jamais lu la discussion).
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Premier']);
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Deuxième']);

        // Quand Alice envoie un nouveau message, le badge envoyé au partenaire
        // doit refléter le total de ses messages non lus (2 existants + 1 nouveau = 3).
        $this->mock(PushService::class)
            ->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user->is($this->bob)),
                Mockery::on(fn ($payload) => isset($payload['badge']) && $payload['badge'] === 3)
            );

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Troisième'])
            ->assertOk();
    }

    public function test_service_worker_sets_badge_on_push_and_clears_on_notification_click(): void
    {
        $sw = $this->get(route('pwa.sw'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->getContent();

        // La pastille est posée quand une push porte un compteur.
        $this->assertStringContainsString('if (data.badge != null) setBadge(data.badge);', $sw);
        // Le service worker sait poser le badge via setNotificationBadge (iOS) et setAppBadge (Android).
        $this->assertStringContainsString('self.registration.setNotificationBadge(n)', $sw);
        $this->assertStringContainsString('self.registration.setAppBadge(n)', $sw);
        // Effacement au clic sur la notification.
        $this->assertStringContainsString('notificationclick', $sw);
        $this->assertStringContainsString('setBadge(0)', $sw);
    }
}
