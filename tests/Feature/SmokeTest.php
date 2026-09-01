<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/manifest.json')->assertOk();
        $this->get('/service-worker.js')->assertOk();
        $this->get('/offscreen')->assertOk();
    }

    public function test_registration_creates_user_with_gender(): void
    {
        $response = $this->post('/register', [
            'name' => 'Camille',
            'gender' => 'Femme',
            'email' => 'camille@test.fr',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('couple.setup'));
        $this->assertDatabaseHas('users', ['email' => 'camille@test.fr', 'gender' => 'Femme']);
    }

    public function test_linked_couple_can_access_all_pages(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'gender' => 'Femme']);
        $bob = User::factory()->create(['name' => 'Bob', 'gender' => 'Homme']);

        $couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $alice->id,
            'user2_id' => $bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $alice->forceFill(['couple_id' => $couple->id])->save();
        $bob->forceFill(['couple_id' => $couple->id])->save();

        $pages = [
            'dashboard',
            'couple.setup',
            'discussion.index',
            'vo.index',
            'ouinon.index',
            'mission.index',
            'enveloppe.index',
            'quiz.index',
            'qdn2.index',
            'question.index',
            'recompenses.index',
            'cartes.index',
            'profile.edit',
        ];

        $this->actingAs($alice);

        foreach ($pages as $page) {
            $this->get(route($page))->assertOk('Lỗi rendu => '.$page);
        }
    }

    public function test_middleware_blocks_unlinked_user(): void
    {
        $solo = User::factory()->create();

        $this->actingAs($solo)
            ->get(route('vo.index'))
            ->assertRedirect(route('couple.setup'));
    }
}
