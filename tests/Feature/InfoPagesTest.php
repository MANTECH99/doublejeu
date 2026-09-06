<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_info_pages_are_publicly_accessible(): void
    {
        $slugs = [
            'confidentialite',
            'cgu',
            'mentions-legales',
            'cookies',
            'securite',
            'contact',
            'a-propos',
            'installation',
            'modes-de-jeu',
            'categories-questions',
        ];

        foreach ($slugs as $slug) {
            $this->get(route('info.show', $slug))->assertOk("Page /info/$slug attendue en 200.");
        }
    }

    public function test_unknown_info_page_returns_404(): void
    {
        $this->get(route('info.show', 'cette-page-n-existe-pas'))->assertNotFound();
    }

    public function test_info_pages_render_their_titles(): void
    {
        $this->get(route('info.show', 'confidentialite'))->assertSee('Politique de confidentialité');
        $this->get(route('info.show', 'cgu'))->assertSee("Conditions d'utilisation");
        $this->get(route('info.show', 'contact'))->assertSee('Contact & support');
    }

    public function test_public_layout_links_legal_pages_in_footer(): void
    {
        $this->get('/')
            ->assertSee(route('info.show', 'confidentialite'))
            ->assertSee(route('info.show', 'cgu'))
            ->assertSee(route('info.show', 'mentions-legales'))
            ->assertSee(route('info.show', 'contact'));
    }

    public function test_profile_page_lists_info_and_legal_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('légal')
            ->assertSee(route('info.show', 'confidentialite'))
            ->assertSee(route('info.show', 'cgu'))
            ->assertSee(route('info.show', 'contact'))
            ->assertSee(route('info.show', 'installation'))
            ->assertSee('data-theme-toggle')
            ->assertSee('Apparence');
    }
}
