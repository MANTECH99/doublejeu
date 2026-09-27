<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_photo_selectionnee_est_mise_en_attente_sans_remplacer_l_actuelle(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);
        Storage::disk('public')->put('profile-photos/actuelle.jpg', 'ancienne');

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('nouvelle.jpg', 400, 400)])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        /* La photo en attente est stockée, mais l'avatar affiché ne change pas encore. */
        $this->assertNotNull($user->avatar_url_pending);
        $this->assertSame('profile-photos/actuelle.jpg', $user->avatar_url);
        $this->assertSame('ancienne', Storage::disk('public')->get('profile-photos/actuelle.jpg'));
    }

    public function test_la_photo_en_attente_est_appliquee_et_supprime_lancienne(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);
        Storage::disk('public')->put('profile-photos/actuelle.jpg', 'ancienne');
        $user->forceFill(['avatar_url_pending' => 'profile-photos/attente.jpg'])->save();
        Storage::disk('public')->put('profile-photos/attente.jpg', 'nouvelle');

        $this->actingAs($user)
            ->post('/profile/photo/apply')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('profile-photos/attente.jpg', $user->avatar_url);
        $this->assertNull($user->avatar_url_pending);
        $this->assertFalse(Storage::disk('public')->exists('profile-photos/actuelle.jpg'));
    }

    public function test_annuler_une_photo_en_attente_conserve_la_photo_actuelle(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);
        Storage::disk('public')->put('profile-photos/actuelle.jpg', 'ancienne');
        $user->forceFill(['avatar_url_pending' => 'profile-photos/attente.jpg'])->save();
        Storage::disk('public')->put('profile-photos/attente.jpg', 'nouvelle');

        $this->actingAs($user)
            ->post('/profile/photo/cancel')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('profile-photos/actuelle.jpg', $user->avatar_url);
        $this->assertNull($user->avatar_url_pending);
        $this->assertFalse(Storage::disk('public')->exists('profile-photos/attente.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('profile-photos/actuelle.jpg'));
    }

    public function test_mettre_a_jour_sans_photo_en_attente_ne_echoue_pas(): void
    {
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);

        $this->actingAs($user)
            ->post('/profile/photo/apply')
            ->assertRedirect('/profile');

        $this->assertSame('profile-photos/actuelle.jpg', $user->refresh()->avatar_url);
    }

    public function test_une_nouvelle_selection_abandonne_la_photo_en_attente_precedente(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->forceFill(['avatar_url_pending' => 'profile-photos/precedente.jpg'])->save();
        Storage::disk('public')->put('profile-photos/precedente.jpg', 'precedente');

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('nouvelle.jpg', 400, 400)])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_url_pending);
        $this->assertNotSame('profile-photos/precedente.jpg', $user->avatar_url_pending);
        $this->assertFalse(Storage::disk('public')->exists('profile-photos/precedente.jpg'));
    }

    public function test_un_fichier_invalide_est_refuse_sans_toucher_a_la_photo_actuelle(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);
        Storage::disk('public')->put('profile-photos/actuelle.jpg', 'ancienne');

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->create('pas-une-image.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        $user->refresh();

        $this->assertSame('profile-photos/actuelle.jpg', $user->avatar_url);
        $this->assertNull($user->avatar_url_pending);
    }

    public function test_supprimer_la_photo_ebrasse_egalement_la_photo_en_attente(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => 'profile-photos/actuelle.jpg']);
        Storage::disk('public')->put('profile-photos/actuelle.jpg', 'ancienne');
        $user->forceFill(['avatar_url_pending' => 'profile-photos/attente.jpg'])->save();
        Storage::disk('public')->put('profile-photos/attente.jpg', 'nouvelle');

        $this->actingAs($user)
            ->delete('/profile/photo')
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertNull($user->avatar_url);
        $this->assertNull($user->avatar_url_pending);
        $this->assertFalse(Storage::disk('public')->exists('profile-photos/actuelle.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('profile-photos/attente.jpg'));
    }

    public function test_la_page_profil_affiche_le_bouton_mettre_a_jour_quand_une_photo_est_en_attente(): void
    {
        $user = User::factory()->create(['avatar_url_pending' => 'profile-photos/attente.jpg']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Valider');
        $response->assertSee('Annuler', false);
        $response->assertSee('profile-photos/attente.jpg', false);
    }
}
