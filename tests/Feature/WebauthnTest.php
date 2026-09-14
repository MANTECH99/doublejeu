<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebauthnTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_face_id_button(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Se connecter avec Face ID')
            ->assertSee('sans rien taper')
            ->assertSee('autocomplete="username webauthn"', false);
    }

    public function test_userless_options_do_not_require_email(): void
    {
        $response = $this->postJson('/webauthn/auth/options');

        $response->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge']])
            ->assertJson(['publicKey' => ['allowCredentials' => []]]);
    }

    public function test_assertion_submission_requires_a_payload(): void
    {
        $this->postJson('/webauthn/auth')
            ->assertStatus(422);
    }

    public function test_registration_options_require_authentication(): void
    {
        $this->post('/webauthn/keys/options')
            ->assertRedirect(route('login'));
    }

    public function test_registration_options_work_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/webauthn/keys/options');

        $response->assertOk()
            ->assertJsonStructure(['publicKey' => ['rp' => ['id'], 'user' => ['id'], 'challenge']]);
    }

    public function test_key_store_rejects_invalid_attestation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/webauthn/keys', [
            'id' => 'fake-credential',
            'type' => 'public-key',
            'rawId' => 'ZmFrZS1jcmVkZW50aWFs',
            'name' => 'Test',
            'response' => [
                'clientDataJSON' => 'eyJ0eXBlIjoi',
                'attestationObject' => 'ZmFrZQ==',
            ],
        ])->assertStatus(422);
    }

    public function test_user_can_delete_own_key(): void
    {
        $user = User::factory()->create();
        $keyId = DB::table('webauthn_keys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Test',
            'credentialId' => 'fake-credential',
            'type' => 'public-key',
            'transports' => '[]',
            'attestationType' => 'none',
            'trustPath' => '',
            'aaguid' => '',
            'credentialPublicKey' => '',
            'counter' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson(route('webauthn.destroy', $keyId))
            ->assertNoContent();

        $this->assertDatabaseMissing('webauthn_keys', ['id' => $keyId]);
    }

    public function test_profile_page_shows_biometric_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Connexion avec Face ID')
            ->assertSee('Enregistrer cet appareil');
    }
}
