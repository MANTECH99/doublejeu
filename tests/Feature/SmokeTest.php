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

    public function test_home_affiche_la_presentation_animee(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('id="dj-player"', false);
        $response->assertSee('Voir la présentation');

        /* 20 scènes : lien + discussion + les 14 jeux + journée + points + récompenses + app. */
        $this->assertSame(20, substr_count($response->getContent(), 'class="dj-scene"'));

        $response->assertSee('Une messagerie rien que pour vous');

        /* La scène du lien montre un vrai portrait par joueur, plus des initiales. */
        $html = $response->getContent();

        $response->assertSee(asset('images/alice.jpg'), false);
        $response->assertSee(asset('images/bob.jpg'), false);
        $this->assertStringNotContainsString('<span class="a a1">', $html);
        $this->assertStringNotContainsString('<span class="a a2">', $html);

        /* Le script du lecteur échoue silencieusement si un de ces hooks disparaît du HTML. */
        foreach (['dj-player', 'dj-stage', 'dj-track', 'dj-fill', 'dj-time', 'dj-play', 'dj-toggle', 'dj-legende', 'dj-score-n', 'dj-install'] as $hook) {
            $response->assertSee('id="'.$hook.'"', false);
        }

        /* Chaque jeu a sa propre scène, donc sa propre entrée de légende. */
        preg_match_all('/data-legende="([^"]+)"/', $html = $response->getContent(), $m);
        $chapitres = array_map('html_entity_decode', $m[1]);

        $this->assertCount(20, $chapitres);
        /* Chaque jeu a sa scène, son titre et son aperçu animé. */
        $this->assertSame(14, substr_count($html, 'class="dj-apercu dj-el dj-pop"'));
        $this->assertSame(14, substr_count($html, 'class="dj-jeu-titre"'));
        $this->assertSame(14, substr_count($html, 'class="dj-jeu-desc dj-el dj-up"'));

        /* Un aperçu qui reflète chaque jeu, pas une simple carte. */
        $apercus = [
            'Discussion' => 'class="dj-typing dj-in dj-el"',
            'Vérité ou Action' => 'class="dj-flip-b"',
            'Oui ou Non' => 'class="dj-ap-jauge"',
            'Mission secrète' => 'CLASSÉ',
            'Enveloppes' => 'class="dj-env-coeur dj-fade"',
            'Tu me connais ?' => 'class="dj-ap-ligne dj-ap-ko dj-el"',
            'Qui de nous deux ?' => 'class="dj-av dj-av-a2 dj-el dj-right"',
            'Question du jour' => '✓ accord',
            'Météo du couple' => 'class="dj-ciel dj-fade"',
            'Mots croisés' => 'class="dj-grille"',
            'Bucket List' => 'class="dj-ligne-boite"',
            'Calendrier' => 'class="dj-grille dj-grille-cal"',
            'Ludo à deux' => 'class="dj-pion dj-fade"',
            'Quoridor' => '⛔ Barrière !',
        ];
        foreach ($apercus as $jeu => $signature) {
            $response->assertSee($signature, false, $jeu.' : aperçu manquant');
        }

        $jeux = ['Discussion', 'Vérité ou Action', 'Oui ou Non', 'Mission secrète', 'Enveloppes', 'Tu me connais ?', 'Qui de nous deux ?', 'Question du jour', 'Météo du couple', 'Mots croisés', 'Bucket List', 'Calendrier', 'Ludo à deux', 'Quoridor'];
        foreach ($jeux as $jeu) {
            $response->assertSee($jeu);
            $this->assertNotEmpty(
                preg_grep('/\s'.preg_quote($jeu, '/').'$/u', $chapitres),
                $jeu.' doit avoir sa propre scène dans la légende'
            );
        }

        /* Les chapitres conservés, dans l'ordre, et l'app en dernier. */
        foreach (['Le lien', 'Discussion', 'La journée', 'Les points', 'Récompenses', "L'app"] as $chapitre) {
            $this->assertContains($chapitre, $chapitres);
        }
        $this->assertSame("L'app", end($chapitres));

        /* La dernière scène montre le logo de l'app et le bouton d'installation. */
        $response->assertSee('icons/icon-512.png', false);
        $response->assertSee("Installer l'application", false);
        $response->assertSee('1 minute · 20 scènes', false);
        /* La bannière d'installation masquerait le bouton de la dernière scène : le lecteur la range. */
        $response->assertSee('window.djInstallPrompt.dismiss()', false);
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
