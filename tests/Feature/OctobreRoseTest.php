<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Database\Seeders\MissionCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OctobreRoseTest extends TestCase
{
    use RefreshDatabase;

    private User $abdoul;

    private User $penda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->abdoul = User::factory()->create(['name' => 'Abdoul', 'date_naissance' => '1994-09-30']);
        $this->penda = User::factory()->create(['name' => 'Penda', 'date_naissance' => '1993-01-01']);

        $couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->abdoul->id,
            'user2_id' => $this->penda->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->abdoul->forceFill(['couple_id' => $couple->id])->save();
        $this->penda->forceFill(['couple_id' => $couple->id])->save();

        config(['missions.catalogue_start_date' => '2020-01-01']);

        $this->seed(MissionCatalogueSeeder::class);
    }

    public function test_le_module_octobre_rose_s_affiche_le_premier_octobre(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->abdoul);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="octobre-rose"', $html);
        $this->assertStringContainsString('Octobre rose', $html);
        // Sans genre renseigné, le modal cite le partenaire par son prénom.
        $this->assertStringContainsString('Pense à Penda', $html);
        // Le contenu est ancré sur le Sénégal, pas sur la France.
        $this->assertStringContainsString('Au Sénégal', $html);
        $this->assertStringNotContainsString('En France', $html);
        $this->assertStringContainsString('GLOBOCAN', $html);
        // Le POST de validation et son bouton sont présents.
        $this->assertStringContainsString('octobreRoseFermer()', $html);
        $this->assertStringContainsString("J'ai compris", $html);
        // Avertissement médical : le module reste informatif.
        $this->assertStringContainsString('ne remplace pas un avis médical', $html);
    }

    public function test_le_titre_cite_la_partenaire_si_je_suis_un_homme(): void
    {
        $this->abdoul->forceFill(['gender' => 'Homme'])->save();
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->abdoul);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pense à Penda', false);
    }

    public function test_le_titre_nomme_mon_partenaire_si_je_suis_une_femme(): void
    {
        // La casse du champ libre est normalisée.
        $this->penda->forceFill(['gender' => 'femme'])->save();
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->penda);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Abdoul pense à toi', false);
    }

    public function test_le_module_ne_s_affiche_pas_les_autres_jours_du_mois(): void
    {
        $this->actingAs($this->abdoul);

        // Le 2 octobre, et le dernier jour du mois : plus rien.
        foreach (['2026-10-02 10:00:00', '2026-10-31 10:00:00'] as $date) {
            $this->travelTo($date);
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('id="octobre-rose"', false);
        }

        // Ni le dernier jour de septembre, ni le premier de novembre.
        foreach (['2026-09-30 10:00:00', '2026-11-01 10:00:00'] as $date) {
            $this->travelTo($date);
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('id="octobre-rose"', false);
        }
    }

    public function test_le_module_ne_revient_pas_apres_validation(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->abdoul);

        $this->assertNull($this->abdoul->fresh()->octobre_rose_vue_annee);

        $this->postJson(route('octobre-rose.info.vue'))->assertOk();
        $this->assertSame(2026, $this->abdoul->fresh()->octobre_rose_vue_annee);

        // Le 1er octobre suivant, il est de nouveau proposé.
        $this->travelTo('2027-10-01 10:00:00');
        $this->get(route('dashboard'))->assertOk()->assertSee('id="octobre-rose"', false);
    }

    public function test_chaque_partenaire_a_sa_propre_validation(): void
    {
        $this->travelTo('2026-10-01 10:00:00');

        // Abdoul valide : le module disparaît pour lui, reste pour Penda.
        $this->actingAs($this->abdoul);
        $this->postJson(route('octobre-rose.info.vue'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="octobre-rose"', false);

        $this->actingAs($this->penda);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="octobre-rose"', false);
        $this->assertNull($this->penda->fresh()->octobre_rose_vue_annee);

        // Penda valide à son tour.
        $this->postJson(route('octobre-rose.info.vue'))->assertOk();
        $this->assertSame(2026, $this->penda->fresh()->octobre_rose_vue_annee);
    }

    public function test_le_module_ne_depend_pas_de_la_fenetre_anniversaire(): void
    {
        // En dehors de toute fenêtre d'anniversaire, le module s'affiche
        // quand même : c'est une sensibilisation, pas une fonctionnalité.
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->abdoul);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="octobre-rose"', false)
            ->assertDontSee('id="hero-celebrer"', false);
    }

    public function test_le_tutoriel_anniversaire_attend_la_fin_du_module_octobre_rose(): void
    {
        // Le 1er octobre peut tomber dans la fenêtre d'un anniversaire :
        // les deux modals existent, mais ne doivent pas se superposer.
        $this->penda->forceFill(['date_naissance' => '1993-10-08'])->save();
        $this->travelTo('2026-10-01 10:00:00');
        $this->actingAs($this->abdoul);

        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="octobre-rose"', false)
            ->assertSee('id="anniv-info"', false)
            ->getContent();

        // La sensibilisation s'ouvre seule : le bootstrap ne lance que celle-ci.
        $this->assertSame(1, preg_match(
            "/document.addEventListener\('DOMContentLoaded', function \(\) \{(.*?)\n\s*\}\);/s",
            $html,
            $bootstrap
        ));
        $this->assertStringContainsString('octobreRoseOuvrir();', $bootstrap[1]);
        $this->assertStringNotContainsString('annivInfoOuvrir();', $bootstrap[1]);

        // Et le tutoriel prend le relais une fois la sensibilisation validée :
        // c'est le seul endroit de la page qui appelle annivInfoOuvrir.
        $this->assertSame(1, substr_count($html, 'annivInfoOuvrir();'));
        $this->assertGreaterThan(
            strpos($html, 'function octobreRoseFermer()'),
            strpos($html, 'annivInfoOuvrir();')
        );
    }

    public function test_la_question_du_soir_attend_la_fin_du_module_octobre_rose(): void
    {
        // 1er octobre, 20h30 : le module et la question du soir sont dus ensemble.
        $this->travelTo('2026-10-01 00:30:00');
        $this->artisan('missions:routine');
        $this->travelTo('2026-10-01 20:30:00');
        $this->actingAs($this->abdoul);

        // La question du soir de Penda est bien en attente…
        $infos = $this->getJson(route('mission.infos'))->assertOk()->json('modals');
        $this->assertContains('question', collect($infos)->pluck('type')->all());

        // … mais elle s'affiche après le modal, pas dessus : les deux passent
        // par le même sémaphore.
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="octobre-rose"', false)
            ->getContent();

        $this->assertStringContainsString('window.djModalLibre(showNext)', $html);
        $this->assertStringContainsString('window.djModalLibre(suite)', $html);
    }

    public function test_le_theme_rose_est_bien_applique(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--rose: #e75480', $css);
        $this->assertSame(1, preg_match('/\.rose-ov\s*\{[^}]*\}/s', $css, $ov));
        $this->assertStringContainsString('231, 84, 128', $ov[0]);

        // Le bouton utilise sa propre classe rose, pas btn-primary (rouge).
        $this->assertSame(1, preg_match('/\.btn-rose\s*\{[^}]*\}/s', $css, $btn));
        $this->assertStringNotContainsString('--primary', $btn[0]);
    }
}
