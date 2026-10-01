<?php

namespace Tests\Feature;

use App\Models\Celebration;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnniversaireCelebrationTest extends TestCase
{
    use RefreshDatabase;

    private User $abdoul;

    private User $penda;

    private Couple $couple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->abdoul = User::factory()->create(['name' => 'Abdoul', 'date_naissance' => '1994-09-30']);
        $this->penda = User::factory()->create(['name' => 'Penda', 'date_naissance' => '1993-01-01']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->abdoul->id,
            'user2_id' => $this->penda->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->abdoul->forceFill(['couple_id' => $this->couple->id])->save();
        $this->penda->forceFill(['couple_id' => $this->couple->id])->save();
    }

    /**
     * Repositionne les deux anniversaires pour qu'ils tombent tous les deux dans
     * la fenêtre de préparation à la date de voyage donnée, puis recharge les
     * utilisateurs (le coupleModel du user est memoïsé en session d'un rendu).
     */
    private function anniversairesProches(string $date): void
    {
        $reference = Carbon::parse($date)->startOfDay();

        $this->abdoul->forceFill(['date_naissance' => $reference->copy()->addDays(2)->toDateString()])->save();
        $this->penda->forceFill(['date_naissance' => $reference->copy()->addDays(5)->toDateString()])->save();

        $this->abdoul = User::find($this->abdoul->id);
        $this->penda = User::find($this->penda->id);
    }

    public function test_le_bouton_celebrer_apparait_uniquement_dans_la_fenetre_de_7_jours(): void
    {
        // 8 jours avant le 1er janvier 2027 : trop tôt, la fenêtre est fermée.
        $this->travelTo('2026-12-24 10:00:00');
        $this->actingAs($this->abdoul);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Célébrer');
        $this->get(route('anniversaire.celebrer'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('flash');

        // 7 jours avant : la fenêtre est ouverte.
        $this->travelTo('2026-12-25 10:00:00');
        $this->get(route('dashboard'))->assertOk()->assertSee('Célébrer');
        $this->get(route('anniversaire.celebrer'))->assertOk()->assertSee('Dans 7 jours');

        // Le jour même : la fenêtre est encore ouverte, pour le dernier moment.
        $this->travelTo('2027-01-01 09:00:00');
        $this->get(route('anniversaire.celebrer'))->assertOk()->assertSee('aujourd\'hui', false);
    }

    public function test_le_tutoriel_anniversaire_s_affiche_une_seule_fois_et_apres_les_deux_modals(): void
    {
        $this->travelTo('2026-12-25 10:00:00');
        $this->actingAs($this->abdoul);

        // Ouverture de la fenêtre, aucun cadeau encore écrit : les 2 modals sont là.
        $premier = $this->get(route('dashboard'))->assertOk();
        $premier->assertSee('id="anniv-info"', false);
        $premier->assertSee('id="anniv-tuto"', false);
        $premier->assertSee('id="hero-celebrer"', false);
        $premier->assertSee('C\'est bientôt l\'anniversaire de Penda !', false);

        // Tant que le 2e modal n'est pas fermé, le tutoriel revient.
        $this->assertNull($this->abdoul->fresh()->anniv_info_vue_annee);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="anniv-info"', false);

        // Fermeture du 2e modal : POST de confirmation.
        $this->postJson(route('anniversaire.info.vue'))->assertOk();
        $this->assertSame(2027, $this->abdoul->fresh()->anniv_info_vue_annee);

        // Le tutoriel ne revient plus cette année.
        $deuxieme = $this->get(route('dashboard'))->assertOk();
        $deuxieme->assertDontSee('id="anniv-info"', false);
        $deuxieme->assertDontSee('id="anniv-tuto"', false);
        // Le bouton, lui, reste bien présent.
        $deuxieme->assertSee('id="hero-celebrer"', false);

        // L'année suivante, le tutoriel est de nouveau proposé.
        $this->travelTo('2027-12-25 10:00:00');
        $this->get(route('dashboard'))->assertOk()->assertSee('id="anniv-info"', false);
    }

    public function test_les_deux_modals_du_tutoriel_utilisent_le_composant_modal_de_l_app(): void
    {
        $this->travelTo('2026-12-25 10:00:00');
        $this->actingAs($this->abdoul);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        // Les deux étapes réutilisent .modal-ov / .modal, le composant déjà rendu
        // partout ailleurs : c'est ce qui garantit qu'elles s'affichent.
        $this->assertSame(1, preg_match('/<div id="anniv-info" class="modal-ov"[^>]*>/', $html, $info));
        $this->assertSame(1, preg_match('/<div id="anniv-tuto" class="modal-ov anniv-tuto-descend anniv-tuto-veil"[^>]*>/', $html, $tuto));
        $this->assertStringContainsString('class="modal center anniv-tuto-box"', $html);
        $this->assertStringContainsString('anniv-tuto-doigt', $html);
        $this->assertStringContainsString("J'ai compris", $html);

        // Le voile du 1er modal reste celui de l'app : on ne le touche pas.
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('anniv-voile', $css);
        $this->assertStringNotContainsString('anniv-voile', $html);

        // Le 2e modal, lui, n'a pas de flou : le bouton Célébrer visé par la
        // flèche doit rester net, sinon la flèche pointe vers un flou.
        $this->assertSame(1, preg_match('/\.modal-ov\.anniv-tuto-veil\s*\{[^}]*\}/s', $css, $veil));
        $this->assertStringContainsString('backdrop-filter: none', $veil[0]);
        $this->assertSame(1, preg_match('/\.modal-ov\s*\{[^}]*\}/s', $css, $modalOv));
        $this->assertStringContainsString('backdrop-filter: blur(6px)', $modalOv[0]);

        // La flèche du haut du modal désigne le bouton Célébrer, et le modal
        // descend un peu pour lui laisser de la place.
        $this->assertSame(1, preg_match('/\.anniv-tuto-fleche\s*\{[^}]*\}/s', $css, $fleche));
        $this->assertStringContainsString('top: -11px', $fleche[0]);
        $this->assertStringContainsString('rotate(45deg)', $fleche[0]);
        $this->assertSame(1, preg_match('/\.anniv-tuto-descend\s*\{[^}]*\}/s', $css, $descend));
        $this->assertStringContainsString('padding-top', $descend[0]);

        // Le bouton Célébrer, lui seul, doit sortir du voile pour rester
        // visible : impossible tant que .hero-inner crée un contexte
        // d'empilement qui l'enferme dans la carte hero.
        $this->assertSame(1, preg_match('/\.hero-inner\s*\{[^}]*\}/s', $css, $heroInner));
        $this->assertStringNotContainsString('z-index', $heroInner[0]);
        $this->assertSame(1, preg_match('/\.hero-celebrer\.anniv-cible\s*\{[^}]*\}/s', $css, $cible));
        $this->assertStringContainsString('z-index: 151', $cible[0]);

        // Le 1er modal n'est pas fermable sur le fond : sa seule sortie est le
        // bouton « Où trouver ça ? », qui enchaîne sur le 2e.
        $this->assertStringNotContainsString('onclick', $info[0]);

        // Aucune mesure de position : un overlay mesuré en display:none a une
        // hauteur de 0 et atterrit mal. Tout est porté par le CSS du modal.
        $this->assertStringNotContainsString('getBoundingClientRect', $html);
        $this->assertStringNotContainsString('offsetHeight', $html);
        $this->assertStringNotContainsString('aniv-tuto', $html);

        // Le bouton Célébrer n'est jamais intercepté, sinon il devient inerte.
        $this->assertSame(
            1,
            preg_match('/<script>(?:(?!<\/script>).)*annivInfoOuvrir.*?<\/script>/s', $html, $s),
            'le script du tutoriel est introuvable'
        );
        $this->assertStringNotContainsString('preventDefault', $s[0]);

        // Les deux effets sont toujours actifs : le JS ne doit jamais les retirer.
        $this->assertSame(1, preg_match('/function annivBoutonEtat\(.*?\n            \}/s', $s[0], $f));
        $this->assertStringContainsString("classList.toggle('anniv-cible', modaleOuverte)", $s[0]);
        $this->assertStringNotContainsString('pulse-glow', $s[0]);
        $this->assertSame(2, substr_count($s[0], 'annivBoutonEtat(true)'));
        $this->assertStringContainsString('annivBoutonEtat(false)', $s[0]);

        // Shimmer en plus du scintillement, sans toucher à la couleur : le
        // reflet passe par un pseudo-élément, transparent sauf au milieu.
        $this->assertStringContainsString('hero-celebrer pulse-glow', $html);
        $this->assertSame(1, preg_match('/\.hero-celebrer::after\s*\{[^}]*\}/s', $css, $shimmer));
        $this->assertStringContainsString('background: linear-gradient', $shimmer[0]);
        $this->assertStringContainsString('animation: shimmer', $shimmer[0]);
        $this->assertSame(1, preg_match('/\.hero-celebrer\s*\{[^}]*\}/s', $css, $conteneur));
        $this->assertStringNotContainsString('background', $conteneur[0]);
    }

    public function test_le_tutoriel_anniversaire_ne_s_affiche_pas_hors_fenetre(): void
    {
        $this->actingAs($this->abdoul);

        // Hors fenêtre : ni bouton, ni tutoriel.
        $this->travelTo('2026-09-01 10:00:00');
        $hors = $this->get(route('dashboard'))->assertOk();
        $hors->assertDontSee('id="anniv-info"', false);
        $hors->assertDontSee('id="hero-celebrer"', false);

        // Fenêtre ouverte, cadeau déjà écrit : le tutoriel revient quand même,
        // car on ne le masque pas tant qu'il n'a pas été validé.
        $this->travelTo('2026-12-27 10:00:00');
        $this->post(route('anniversaire.enregistrer'), ['message' => 'Déjà écrit']);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="hero-celebrer"', false)
            ->assertSee('id="anniv-info"', false);
    }

    public function test_le_jour_j_remplace_la_fiche_par_une_seule_ligne(): void
    {
        $this->travelTo('2026-12-31 10:00:00');
        $this->actingAs($this->abdoul);

        // La veille : la fiche complète avec le compte à rebours.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Anniversaire de Penda')
            ->assertSee('j-1 jours', false);

        // Le jour J : plus de fiche, une seule ligne de fête.
        $this->travelTo('2027-01-01 10:00:00');
        $jourJ = $this->get(route('dashboard'))->assertOk();
        $jourJ->assertSee('C\'est l\'anniversaire de Penda !', false);
        $jourJ->assertDontSee('Anniversaire de Penda', false);

        // Le lendemain, on retrouve le compte à rebours de l'année suivante.
        $this->travelTo('2027-01-02 10:00:00');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Anniversaire de Penda')
            ->assertSee('j-364 jours', false);
    }

    public function test_le_bouton_celebrer_est_absent_hors_fenetre_et_sur_sa_propre_anniversaire(): void
    {
        $this->travelTo('2026-09-01 10:00:00');
        $this->actingAs($this->abdoul);

        // L'anniversaire de Penda (1er janvier) est dans 122 jours : hors fenêtre.
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $dashboard->assertSee('j-122 jours');
        $dashboard->assertDontSee('Célébrer');

        $this->get(route('anniversaire.celebrer'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('flash');

        // Page inaccessible en direct hors fenêtre.
        $this->post(route('anniversaire.enregistrer'), ['message' => 'Cadeau trop tôt'])
            ->assertForbidden();

        $this->assertDatabaseCount('celebrations', 0);

        // Mon propre anniversaire (30 septembre) n'affiche jamais de bouton Célébrer.
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Célébrer');
    }

    public function test_un_cadeau_se_prepare_avec_un_son_puis_se_met_a_jour(): void
    {
        Storage::fake('public');
        $this->travelTo('2026-12-27 10:00:00');

        $this->actingAs($this->abdoul)
            ->post(route('anniversaire.enregistrer'), [
                'message' => 'Joyeux anniversaire mon amour 💌',
                'son' => UploadedFile::fake()->create('voix.m4a', 64, 'audio/mp4'),
                'activite' => 'Une soirée resto',
                'promesse' => 'Je viens diner samedi',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash');

        $celebration = Celebration::de($this->abdoul, $this->penda);
        $this->assertNotNull($celebration);
        $this->assertSame(2027, $celebration->annee);
        $this->assertSame('Joyeux anniversaire mon amour 💌', $celebration->message);
        $this->assertTrue($celebration->aUnSon());
        Storage::disk('public')->assertExists($celebration->audio_path);

        $this->post(route('anniversaire.enregistrer'), [
            'message' => 'Joyeux anniversaire mon amour 💌',
            'video' => UploadedFile::fake()->create('souvenir.mp4', 128, 'video/mp4'),
        ]);

        $this->assertTrue($celebration->fresh()->aUneVideo());
        Storage::disk('public')->assertExists($celebration->fresh()->video_path);

        $ancienneVideo = $celebration->fresh()->video_path;

        $this->post(route('anniversaire.enregistrer'), [
            'message' => 'Joyeux anniversaire mon amour 💌',
            'supprimer_video' => '1',
        ]);

        Storage::disk('public')->assertMissing($ancienneVideo);
        $this->assertNull($celebration->fresh()->video_path);

        // Le bouton passe à « Modifier » et le son est déjà rejouable.
        $this->get(route('dashboard'))->assertOk()->assertSee('Modifier');
        $this->get(route('anniversaire.celebrer'))->assertOk()->assertSee('Mettre à jour mon cadeau');

        $ancienSon = $celebration->audio_path;

        $this->post(route('anniversaire.enregistrer'), [
            'message' => 'Joyeux anniversaire mon amour 💌',
            'supprimer_son' => '1',
        ]);

        Storage::disk('public')->assertMissing($ancienSon);
        $this->assertNull($celebration->fresh()->audio_path);
    }

    public function test_le_cadeau_reste_secret_jusqua_l_anniversaire_puis_s_ouvre_en_lecture_seule(): void
    {
        $this->travelTo('2026-12-27 10:00:00');

        $this->actingAs($this->abdoul)->post(route('anniversaire.enregistrer'), [
            'message' => 'Mon cadeau secret',
            'activite' => 'Une soirée resto',
        ]);

        // Avant l'anniversaire, Penda ne voit rien et ne peut pas forcer la page.
        $this->actingAs($this->penda);
        $this->get(route('anniversaire.ouvrir'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('flash');

        $jourJ = $this->get(route('dashboard'))->assertOk();
        $jourJ->assertDontSee('appuie pour voir', false);

        // Le jour J, le badge devient un lien vers le cadeau.
        $this->travelTo('2027-01-01 08:30:00');
        $this->actingAs($this->penda);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Abdoul t\'a préparé quelque chose', false);

        $cadeau = $this->get(route('anniversaire.ouvrir'))->assertOk();
        $cadeau->assertSee('Joyeux anniversaire Penda !');
        $cadeau->assertSee('Mon cadeau secret');
        $cadeau->assertSee('Une soirée resto');
        // Lecture seule : ni formulaire ni bouton d'écriture sur la page.
        $cadeau->assertDontSee('<form', false);
        $cadeau->assertDontSee('Mettre à jour', false);
        // Des confettis tombent à l'ouverture.
        $cadeau->assertSee('id="confettis"', false);
    }

    public function test_la_video_du_cadeau_s_affiche_le_jour_j(): void
    {
        Storage::fake('public');
        $this->travelTo('2026-12-27 10:00:00');

        $this->actingAs($this->abdoul)->post(route('anniversaire.enregistrer'), [
            'message' => 'Mon cadeau secret',
            'video' => UploadedFile::fake()->create('souvenir.mp4', 128, 'video/mp4'),
        ]);

        // Avant l'anniversaire, rien de visible.
        $this->actingAs($this->penda);
        $this->get(route('anniversaire.ouvrir'))->assertRedirect(route('dashboard'));

        // Le jour J, la vidéo est lisible.
        $this->travelTo('2027-01-01 08:30:00');
        $this->actingAs($this->penda);
        $this->get(route('anniversaire.ouvrir'))
            ->assertOk()
            ->assertSee('Une vidéo pour toi')
            ->assertSee('<video', false);
    }

    public function test_les_badges_de_la_journee_disparaissent_apres_l_anniversaire(): void
    {
        $this->travelTo('2026-12-27 10:00:00');

        $this->actingAs($this->abdoul)
            ->post(route('anniversaire.enregistrer'), ['message' => 'Mon cadeau secret']);

        // Le jour J : les deux badges sont présents, côte à côte.
        $this->travelTo('2027-01-01 08:30:00');
        $this->actingAs($this->penda);
        $jourJ = $this->get(route('dashboard'))->assertOk();
        $jourJ->assertSee('C\'est l\'anniversaire de Penda !', false);
        $jourJ->assertSee('Abdoul t\'a préparé quelque chose', false);

        // Le lendemain, plus rien de tout ça : le compteur repart sur l'année suivante.
        $this->travelTo('2027-01-02 08:30:00');
        $this->actingAs($this->penda);
        $apres = $this->get(route('dashboard'))->assertOk();
        $apres->assertDontSee('C\'est l\'anniversaire de Penda !', false);
        $apres->assertDontSee('t\'a préparé quelque chose', false);
        $apres->assertSee('Anniversaire de Penda');
        $apres->assertSee('j-364 jours', false);

        // Le cadeau reste ouvrable une fois passé, même si le dashboard ne l'annonce plus.
        $this->get(route('anniversaire.ouvrir'))
            ->assertOk()
            ->assertSee('Mon cadeau secret');
    }

    public function test_un_cadeau_cible_l_annee_suivante_ne_s_ouvre_pas_trop_tot(): void
    {
        $this->travelTo('2026-12-27 10:00:00');

        // Penda est née le 1er janvier : le cadeau préparé par Abdoul cible 2027.
        $this->actingAs($this->abdoul)->post(route('anniversaire.enregistrer'), [
            'message' => 'Pour l\'année prochaine',
        ]);

        $this->assertSame(2027, Celebration::de($this->abdoul, $this->penda)?->annee);

        // Penda ne l'ouvre pas avant le 1er janvier 2027.
        $this->travelTo('2026-12-31 23:30:00');
        $this->actingAs($this->penda);
        $this->get(route('anniversaire.ouvrir'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('flash');

        // Le 1er janvier 2027, c'est bien le sien.
        $this->travelTo('2027-01-01 10:00:00');
        $this->actingAs($this->penda);
        $this->get(route('anniversaire.ouvrir'))
            ->assertOk()
            ->assertSee('Pour l\'année prochaine');
    }

    public function test_chaque_partenaire_ecrit_son_propre_cadeau(): void
    {
        // Les deux anniversaires sont proches : chacun peut écrire pour l'autre.
        $this->anniversairesProches('2026-09-10');
        $this->travelTo('2026-09-10 10:00:00');

        $this->actingAs($this->abdoul);
        $this->get(route('dashboard'))->assertOk()->assertSee('Célébrer');
        $this->post(route('anniversaire.enregistrer'), ['message' => 'De la part d\'Abdoul']);

        $this->actingAs($this->penda);
        $this->get(route('dashboard'))->assertOk()->assertSee('Célébrer');
        $this->post(route('anniversaire.enregistrer'), ['message' => 'De la part de Penda']);

        $this->assertDatabaseCount('celebrations', 2);
        $this->assertSame('De la part d\'Abdoul', Celebration::de($this->abdoul, $this->penda)?->message);
        $this->assertSame('De la part de Penda', Celebration::de($this->penda, $this->abdoul)?->message);
    }
}
