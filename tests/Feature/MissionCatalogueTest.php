<?php

namespace Tests\Feature;

use App\Http\Controllers\MissionSecreteController;
use App\Models\Couple;
use App\Models\Mission;
use App\Models\MissionSecrete;
use App\Models\MissionTrack;
use App\Models\User;
use Database\Seeders\MissionCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Couple $couple;

    /** Premier jour où le catalogue est actif. */
    private const BASCULE = MissionSecreteController::CATALOGUE_START_DATE_DEFAUT;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob = User::factory()->create(['name' => 'Bob']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->alice->id,
            'user2_id' => $this->bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->alice->forceFill(['couple_id' => $this->couple->id])->save();
        $this->bob->forceFill(['couple_id' => $this->couple->id])->save();

        $this->seed(MissionCatalogueSeeder::class);

        // Les tests se placent après la bascule pour ne pas dépendre de la date
        // réelle d'exécution : le catalogue est actif par défaut ici.
        $this->travelTo(Carbon::parse(self::BASCULE)->addDay()->setTime(9, 0));
    }

    public function test_le_catalogue_contient_cent_missions_sans_doublon(): void
    {
        $this->assertSame(100, Mission::count());
        $this->assertSame(100, Mission::distinct()->count('texte'));
    }

    public function test_une_mission_ne_revient_jamais_avant_epuisement_du_catalogue(): void
    {
        $servies = [];

        // 100 jours : chaque jour doit apporter une mission inédite.
        for ($jour = 0; $jour < 100; $jour++) {
            $this->travelTo(now()->addDays($jour)->setTime(9, 0));

            [$mission, $cree] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

            $this->assertNotNull($mission, "Aucune mission servie au jour {$jour}.");
            $this->assertTrue($cree);
            $servies[] = $mission->texte;
        }

        $this->assertCount(100, array_unique($servies));
    }

    public function test_chaque_partenaire_parcourt_tout_le_catalogue(): void
    {
        for ($jour = 0; $jour < 100; $jour++) {
            $this->travelTo(now()->addDays($jour)->setTime(9, 0));

            MissionSecreteController::genererPourUser($this->couple, $this->alice);
            MissionSecreteController::genererPourUser($this->couple, $this->bob);
        }

        // Le suivi est par utilisateur : chacun a reçu les 100 missions.
        $this->assertSame(100, MissionTrack::where('user_id', $this->alice->id)->count());
        $this->assertSame(100, MissionTrack::where('user_id', $this->bob->id)->count());
    }

    public function test_plus_de_mission_a_l_epuisement_du_catalogue(): void
    {
        for ($jour = 0; $jour < 100; $jour++) {
            $this->travelTo(now()->addDays($jour)->setTime(9, 0));
            MissionSecreteController::genererPourUser($this->couple, $this->alice);
        }

        $this->assertSame(0, MissionSecreteController::missionsRestantes($this->alice));

        $this->travelTo(now()->addDays(100)->setTime(9, 0));
        [$mission, $cree] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->assertNull($mission);
        $this->assertFalse($cree);
        $this->assertSame(100, MissionSecrete::where('joueur_cible_id', $this->alice->id)->count());
    }

    public function test_la_page_mission_signale_la_fin_du_catalogue(): void
    {
        for ($jour = 0; $jour < 100; $jour++) {
            $this->travelTo(now()->addDays($jour)->setTime(9, 0));
            MissionSecreteController::genererPourUser($this->couple, $this->alice);
        }

        $this->travelTo(now()->addDays(100)->setTime(9, 0));
        $html = $this->actingAs($this->alice)->get(route('mission.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Plus de mission', $html);
    }

    public function test_une_mission_du_jour_pre_existante_est_conservee(): void
    {
        $date = $this->alice->localToday()->toDateString();

        $existante = MissionSecrete::create([
            'couple_id' => $this->couple->id,
            'joueur_cible_id' => $this->alice->id,
            'texte' => 'Mission déjà attribuée avant le basculement',
            'difficulte' => 'facile',
            'statut' => 'en_attente',
            'date_mission' => $date,
            'date_debut' => now(),
            'date_fin' => $this->alice->deadlineSoir(),
        ]);

        [$mission, $cree] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->assertFalse($cree);
        $this->assertSame($existante->id, $mission->id);
        // Elle n'est pas consommée : elle ne compte pas dans le catalogue.
        $this->assertSame(0, MissionTrack::where('user_id', $this->alice->id)->count());
    }

    public function test_le_catalogue_ne_demarre_pas_avant_la_date_de_bascule(): void
    {
        // On se place la veille de la bascule : rien ne doit être servi ni consommé.
        $veille = Carbon::parse(self::BASCULE)->subDay();

        $this->travelTo($veille->setTime(9, 0));
        [$mission, $cree] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->assertNull($mission);
        $this->assertFalse($cree);
        $this->assertSame(0, MissionTrack::count());

        // Le jour de la bascule, le catalogue s'ouvre.
        $this->travelTo($veille->addDay()->setTime(9, 0));
        [$mission, $cree] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->assertNotNull($mission);
        $this->assertTrue($cree);
    }

    public function test_la_bascule_ne_depend_pas_du_cache_de_configuration(): void
    {
        // Si la bascule venait directement de la config sans repli, une valeur
        // nulle ferait de « maintenant » la date de bascule et bloquerait le jeu
        // pour tout le monde. Le repli rend le comportement déterministe.
        config(['missions.catalogue_start_date' => null]);

        $veille = Carbon::parse(self::BASCULE)->subDay();
        $this->travelTo($veille->setTime(9, 0));

        [$mission] = MissionSecreteController::genererPourUser($this->couple, $this->alice);
        $this->assertNull($mission);

        $this->travelTo($veille->addDay()->setTime(9, 0));
        [$mission] = MissionSecreteController::genererPourUser($this->couple, $this->alice);
        $this->assertNotNull($mission);
    }

    public function test_le_seeder_est_rejouable_sans_detruire_la_progression(): void
    {
        // Le seeder est appelé par DatabaseSeeder, donc à chaque déploiement.
        // Le rejouer ne doit ni dupliquer le catalogue ni faire perdre les
        // missions déjà parcourues.
        MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->seed(MissionCatalogueSeeder::class);

        $this->assertSame(100, Mission::count());
        $this->assertSame(100, Mission::distinct()->count('texte'));
        $this->assertSame(1, MissionTrack::where('user_id', $this->alice->id)->count());
        $this->assertSame(99, MissionSecreteController::missionsRestantes($this->alice));
    }

    public function test_generer_deux_missions_le_meme_jour_ne_consomme_une_seule_mission(): void
    {
        [$premiere, $cree1] = MissionSecreteController::genererPourUser($this->couple, $this->alice);
        [$seconde, $cree2] = MissionSecreteController::genererPourUser($this->couple, $this->alice);

        $this->assertTrue($cree1);
        $this->assertFalse($cree2);
        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, MissionTrack::where('user_id', $this->alice->id)->count());
    }
}
