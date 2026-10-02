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

    public function test_the_appearance_button_offers_three_themes(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('theme-label', $html);

        // Les trois libellés vivent dans app.js, pas dans le Blade : le markup
        // n'affiche que le thème courant (applyTheme() réécrit le reste).
        $js = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("label: 'Rose'", $js);
        $this->assertStringContainsString("label: 'Sombre'", $js);
        $this->assertStringContainsString("label: 'Blanc'", $js);
    }

    public function test_the_rose_theme_stays_on_two_rose_tints(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        preg_match('/html\[data-theme=\'rose\'\]\s*\{([^}]*)\}/s', $css, $m);
        $this->assertNotEmpty($m, 'Le thème rose doit exister dans le CSS.');

        // Les deux roses du ruban, et rien d'autre. Le noir bleuté, les cartes
        // et les textes sont hérités du thème sombre : les redéclarer ici
        // serait du bruit qui peut dériver sans qu'on le voie.
        $this->assertStringContainsString('--primary: #e75480;', $m[1]);
        $this->assertStringContainsString('--primary-dark: #a3244d;', $m[1]);
        $this->assertStringNotContainsString('--bg:', $m[1]);
        $this->assertStringNotContainsString('--card', $m[1]);
        $this->assertStringNotContainsString('--text', $m[1]);
        $this->assertStringNotContainsString('--border:', $m[1]);

        // --primary-2 doit reprendre --primary : le rose pâle #ff8fab
        // s'introduirait sinon par les dégradés, en troisième teinte.
        $this->assertStringContainsString('--primary-2: #e75480;', $m[1]);
        $this->assertStringNotContainsString('#ff8fab', $m[1]);
    }

    public function test_the_appearance_toggle_cycles_through_dark_rose_and_light(): void
    {
        $js = (string) file_get_contents(resource_path('js/app.js'));

        // Le cycle doit contenir les trois thèmes, dans l'ordre du libellé.
        $this->assertStringContainsString("{ id: 'dark',", $js);
        $this->assertStringContainsString("{ id: 'rose',", $js);
        $this->assertStringContainsString("{ id: 'light',", $js);

        // Un thème inconnu (ancien localStorage, saisie manuelle) retombe sur
        // le thème par défaut au lieu de laisser la page sans variables.
        $this->assertStringContainsString(
            'if (!THEMES.some((x) => x.id === current)) current = DEFAULT_THEME;',
            $js
        );
    }

    public function test_rose_is_the_default_theme(): void
    {
        $js = (string) file_get_contents(resource_path('js/app.js'));

        // Rose en premier du cycle, et utilisé comme défaut à deux endroits :
        // la valeur initiale et le repli sur valeur inconnue.
        $this->assertStringContainsString("const DEFAULT_THEME = 'rose';", $js);
        $this->assertStringContainsString('current = DEFAULT_THEME;', $js);
        $this->assertStringContainsString('|| DEFAULT_THEME;', $js);

        // L'ordre du cycle suit le défaut : rose → sombre → blanc.
        $this->assertMatchesRegularExpression(
            "/\{ id: 'rose',.*\{ id: 'dark',.*\{ id: 'light',/s",
            $js
        );
    }

    public function test_legacy_dark_sessions_are_migrated_to_rose(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        // La migration écrase la valeur stockée SANS la tester. Sous l'ancien
        // défaut, applyTheme() écrivait 'dark' à chaque chargement, même sans
        // clic, donc la valeur ne distinguait pas un héritage d'un choix. Un
        // test du type `=== 'dark'` n'aurait migré que les sessions sombres et
        // laissé les choix blancs en place : ce n'est pas « tout le monde ».
        $this->assertStringContainsString('dj_theme_legacy_migrated', $html);
        $this->assertStringContainsString(
            "localStorage.setItem('dj_theme', 'rose');",
            $html
        );
        $this->assertStringNotContainsString(
            "localStorage.getItem('dj_theme') === 'dark'",
            $html
        );
    }

    public function test_the_migration_runs_only_once(): void
    {
        $js = (string) file_get_contents(resource_path('js/app.js'));

        // Le marqueur doit court-circuiter la migration : sans cela, un
        // utilisateur qui choisit volontairement Sombre se reverrait
        // ramené à Rose à chaque rechargement.
        $this->assertStringContainsString(
            "if (localStorage.getItem('dj_theme_legacy_migrated') === '2') return;",
            $js
        );
        $this->assertStringContainsString("localStorage.setItem('dj_theme', DEFAULT_THEME);", $js);
        $this->assertStringContainsString("localStorage.setItem('dj_theme_legacy_migrated', '2');", $js);
    }

    public function test_the_theme_marker_is_versioned_so_rose_actually_reaches_everyone(): void
    {
        $js = (string) file_get_contents(resource_path('js/app.js'));
        $html = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        // Le marqueur est une version, pas un simple « déjà migré ». Avec la
        // valeur '1', les navigateurs qui avaient déjà exécuté la version
        // précédente (celle qui ne migrait que les sessions 'dark') auraient
        // court-circuité la bascule et seraient restés sur leur thème.
        $this->assertStringContainsString("localStorage.setItem('dj_theme_legacy_migrated', '2');", $js);
        $this->assertStringContainsString("localStorage.setItem('dj_theme_legacy_migrated', '2');", $html);
    }

    public function test_the_appearance_button_shows_the_default_theme_before_js_runs(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        // Le markup est visible avant l'exécution d'app.js. S'il annonçait
        // « Sombre », un utilisateur en rose verrait le mauvais thème le temps
        // d'un aller-retour réseau.
        $this->assertStringContainsString('class="theme-label">Rose<', $html);
    }

    public function test_the_pre_paint_script_applies_the_rose_theme_without_a_flash(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        // Sans cela, un utilisateur verrait un flash sombre avant que
        // app.js ne prenne le relais. Le défaut doit aussi être rose : le
        // repli sur la base sombre se verrait sur le premier rendu.
        $this->assertStringContainsString("t === 'light' || t === 'rose'", $html);
        $this->assertStringContainsString("localStorage.getItem('dj_theme') || 'rose'", $html);
    }
}
