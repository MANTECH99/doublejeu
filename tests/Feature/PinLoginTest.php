<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private function appareilDe(string $pin = '246810'): DeviceToken
    {
        return DeviceToken::factory()->create([
            'user_id' => User::factory()->withPin($pin),
        ]);
    }

    public function test_le_pin_ne_memorise_pas_la_session_pour_le_redemander_a_chaque_ouverture(): void
    {
        $appareil = $this->appareilDe();

        $reponse = $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ]);

        $reponse->assertOk();
        $this->assertAuthenticatedAs($appareil->user);

        /* Sans cookie « se souvenir », la session meurt et le code est redemandé. */
        $reponse->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_un_code_pin_et_un_appareil_de_confiance_ouvrent_la_session(): void
    {
        $appareil = $this->appareilDe();

        $response = $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertAuthenticatedAs($appareil->user);
    }

    public function test_le_code_pin_est_empreinte_et_jamais_envoye_en_clair(): void
    {
        $appareil = $this->appareilDe();

        $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['pin_hash' => '246810']);
        $this->assertNotSame('246810', $appareil->user->fresh()->pin_hash);
    }

    public function test_un_code_pin_faux_est_refuse_et_bloque_apres_cinq_essais(): void
    {
        $appareil = $this->appareilDe();

        foreach (range(1, 5) as $essai) {
            $this->postJson(route('pin.login.store'), [
                'pin' => '999999',
                'device_token' => $appareil->token,
            ])->assertStatus(422);
        }

        $this->assertGuest();

        /* Même le bon code est refusé pendant la fenêtre de blocage. */
        $bloque = $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ]);

        $bloque->assertStatus(422);
        $bloque->assertJsonValidationErrors('pin');
        $this->assertGuest();
    }

    public function test_un_compte_sans_pin_ne_peut_pas_etre_ouvert_avec_un_pin(): void
    {
        $user = User::factory()->create();
        $appareil = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_un_jeton_inconnu_ne_revele_rien_et_ne_connecte_pas(): void
    {
        $this->appareilDe();

        $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => str_repeat('a', 64),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_le_jeton_est_oblige_puisqu_un_pin_seul_ne_designe_aucun_compte(): void
    {
        $this->appareilDe();

        $this->postJson(route('pin.login.store'), ['pin' => '246810'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_token');

        $this->assertGuest();
    }

    public function test_un_appareil_revoque_ne_peut_plus_ouvrir_la_session(): void
    {
        $appareil = $this->appareilDe();
        $token = $appareil->token;

        $appareil->delete();

        $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $token,
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_la_verification_d_appareil_signale_les_appareils_revoques(): void
    {
        $this->appareilDe();

        $this->postJson(route('pin.login.device'), ['device_token' => str_repeat('a', 64)])
            ->assertNotFound();
    }

    public function test_la_page_de_connexion_garde_le_bouton_face_id_et_replie_le_formulaire(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Se connecter avec Face ID')
            ->assertSee('Utiliser mon email et mon mot de passe')
            ->assertSee('data-pin-url', false);
    }

    public function test_un_compte_peut_poser_et_supprimer_son_code_pin_avec_son_mot_de_passe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'password',
                'pin' => '135790',
                'pin_confirmation' => '135790',
                'appareil' => 'Mon téléphone',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('pin');

        $this->assertTrue($user->fresh()->hasPin());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'Mon téléphone',
        ]);

        $this->actingAs($user)
            ->delete(route('pin.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($user->fresh()->hasPin());
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_un_mauvais_mot_de_passe_ne_permet_pas_de_poser_un_pin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'mauvais',
                'pin' => '135790',
                'pin_confirmation' => '135790',
            ])
            ->assertSessionHasErrors('current_password', null, 'updatePin');

        $this->assertFalse($user->fresh()->hasPin());
    }

    public function test_un_pin_trop_faible_est_refuse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'password',
                'pin' => '111111',
                'pin_confirmation' => '111111',
            ])
            ->assertSessionHasErrors('pin', null, 'updatePin');

        $this->assertFalse($user->fresh()->hasPin());
    }

    public function test_un_pin_seul_ne_demande_pas_la_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'password',
                'pin' => '135790',
                'pin_confirmation' => '135799',
            ])
            ->assertSessionHasErrors('pin_confirmation', null, 'updatePin');

        $this->assertFalse($user->fresh()->hasPin());
    }

    public function test_la_page_profil_affiche_la_section_code_pin_avec_les_appareils(): void
    {
        $appareil = $this->appareilDe();

        $this->actingAs($appareil->user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Code PIN')
            ->assertSee('Appareils de confiance')
            ->assertSee($appareil->name)
            ->assertSee('Supprimer le code PIN');
    }

    public function test_un_appareil_peut_etre_ajoute_depuis_le_profil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('device.store'), ['name' => 'Mon tablette'])
            ->assertOk()
            ->assertJsonStructure(['token', 'name']);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'Mon tablette',
        ]);
    }

    public function test_un_appareil_peut_etre_retire_depuis_le_profil(): void
    {
        $user = User::factory()->create();
        $appareil = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('device.destroy', $appareil))
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('device_tokens', ['id' => $appareil->id]);
    }

    public function test_un_appareil_ne_peut_pas_etre_retire_par_un_autre_compte(): void
    {
        $appareil = DeviceToken::factory()->create();
        $autre = User::factory()->create();

        $this->actingAs($autre)
            ->delete(route('device.destroy', $appareil))
            ->assertNotFound();

        $this->assertDatabaseHas('device_tokens', ['id' => $appareil->id]);
    }

    public function test_le_compteur_d_essais_est_remis_a_zero_apres_une_session_reussie(): void
    {
        $appareil = $this->appareilDe();
        $cle = 'pin|u'.$appareil->user_id;

        $this->postJson(route('pin.login.store'), [
            'pin' => '999999',
            'device_token' => $appareil->token,
        ])->assertStatus(422);

        $this->assertSame(1, RateLimiter::attempts($cle));

        $this->postJson(route('pin.login.store'), [
            'pin' => '246810',
            'device_token' => $appareil->token,
        ])->assertOk();

        $this->assertSame(0, RateLimiter::attempts($cle));
    }
}
