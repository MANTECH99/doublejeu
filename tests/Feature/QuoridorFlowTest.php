<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\Point;
use App\Models\QuoridorPartie;
use App\Models\User;
use App\Services\QuoridorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuoridorFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $carol;

    private Couple $couple;

    private QuoridorService $quoridor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice', 'gender' => 'Femme']);
        $this->bob = User::factory()->create(['name' => 'Bob', 'gender' => 'Homme']);
        $this->carol = User::factory()->create(['name' => 'Carol', 'gender' => 'Femme']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->alice->id,
            'user2_id' => $this->bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->alice->forceFill(['couple_id' => $this->couple->id])->save();
        $this->bob->forceFill(['couple_id' => $this->couple->id])->save();

        $this->quoridor = app(QuoridorService::class);
    }

    private function nouvellePartie(): QuoridorPartie
    {
        return $this->quoridor->start($this->couple, $this->alice);
    }

    private function recalerPartie(QuoridorPartie $partie, array $pions, array $murs = [], ?int $tour = null): QuoridorPartie
    {
        DB::table('quoridor_parties')->where('id', $partie->id)->update([
            'statut' => QuoridorPartie::STATUT_EN_COURS,
            'pions' => json_encode($pions),
            'murs' => $murs === [] ? null : json_encode($murs),
            'tour_id' => $tour ?? $this->alice->id,
        ]);

        return $partie->fresh();
    }

    public function test_start_cree_partie_et_redirige(): void
    {
        $this->actingAs($this->alice);

        $this->post(route('quoridor.start'))->assertRedirect();

        $partie = QuoridorPartie::query()->first();
        $this->assertNotNull($partie);
        $this->assertSame('en_cours', $partie->statut);
        $this->assertSame('classique', $partie->mode);
        $this->assertSame($this->alice->id, $partie->tour_id);
        $this->assertSame(['row' => 8, 'col' => 4], $partie->pions['j1']);
        $this->assertSame(['row' => 0, 'col' => 4], $partie->pions['j2']);
        $this->assertSame([], $partie->murs);
    }

    public function test_start_en_mode_course_met_les_deux_pions_en_bas(): void
    {
        $this->actingAs($this->alice);

        $this->post(route('quoridor.start'), ['mode' => 'course'])->assertRedirect();

        $partie = QuoridorPartie::query()->first();
        $this->assertSame('course', $partie->mode);
        $this->assertSame(['row' => 8, 'col' => 3], $partie->pions['j1']);
        $this->assertSame(['row' => 8, 'col' => 5], $partie->pions['j2']);
    }

    public function test_etat_expose_le_plateau_au_couple(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->bob)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonPath('statut', 'en_cours')
            ->assertJsonPath('tour_id', $this->alice->id)
            ->assertJsonPath('joueurs.j1.couleur', 'rouge')
            ->assertJsonPath('joueurs.j2.couleur', 'bleue')
            ->assertJsonPath('pions.j1.row', 8)
            ->assertJsonCount(0, 'murs')
            ->assertJsonPath('mursRestants.j1', 10)
            ->assertJsonPath('mursRestants.j2', 10);
    }

    public function test_etat_donne_coups_et_murs_au_joueur_courant(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonPath('legal.deplacements', [['row' => 7, 'col' => 4], ['row' => 8, 'col' => 3], ['row' => 8, 'col' => 5]])
            ->assertJsonCount(128, 'legal.murs');
    }

    public function test_etat_legal_nul_pour_qui_ne_joue_pas(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->bob)
            ->getJson(route('quoridor.state', $partie))
            ->assertJsonMissingPath('legal.deplacements')
            ->assertJsonPath('legal', null);
    }

    public function test_non_membre_du_couple_forbidden(): void
    {
        $partenaireDeCarol = User::factory()->create(['name' => 'Dave', 'gender' => 'Homme']);
        $coupleCarol = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->carol->id,
            'user2_id' => $partenaireDeCarol->id,
            'streak' => 0,
            'score_total' => 0,
        ]);
        $this->carol->forceFill(['couple_id' => $coupleCarol->id])->save();
        $partenaireDeCarol->forceFill(['couple_id' => $coupleCarol->id])->save();

        $partie = $this->nouvellePartie();

        $this->actingAs($this->carol)
            ->getJson(route('quoridor.state', $partie))
            ->assertForbidden();

        $this->actingAs($this->carol)
            ->get(route('quoridor.jouer', $partie))
            ->assertForbidden();
    }

    public function test_vue_de_jeu_definit_le_helper_esc_et_le_bouton_de_fin(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->get(route('quoridor.jouer', $partie))
            ->assertOk()
            ->assertSee('function esc(s)', false)
            ->assertSee('id="q-fin"', false)
            ->assertSee('id="q-barre-h"', false)
            ->assertSee('id="q-barre-v"', false);
    }

    public function test_seul_le_joueur_courant_peut_jouer(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->bob)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 7, 'col' => 4])
            ->assertStatus(422)
            ->assertJson(['error' => 'Ce n\'est pas à toi de jouer.']);
    }

    public function test_partie_terminee_non_jouable(): void
    {
        $partie = $this->nouvellePartie();
        $partie->forceFill(['statut' => QuoridorPartie::STATUT_TERMINEE])->save();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 3, 'c' => 3, 'o' => 'h'])
            ->assertStatus(422)
            ->assertJson(['error' => 'La partie est déjà terminée.']);
    }

    public function test_deplacement_d_une_case_et_tour_alterne(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 7, 'col' => 4])
            ->assertOk()
            ->assertJson(['message' => 'Pion déplacé.', 'terminee' => false]);

        $partie->refresh();
        $this->assertSame(7, $partie->pions['j1']['row']);
        $this->assertSame(4, $partie->pions['j1']['col']);
        $this->assertSame($this->bob->id, $partie->tour_id);
    }

    public function test_deplacement_invalide_refuse(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 6, 'col' => 4])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ce déplacement n\'est pas valide.']);

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 7, 'col' => 3])
            ->assertStatus(422);
    }

    public function test_saut_au_dessus_du_pion_adverse(): void
    {
        $partie = $this->nouvellePartie();
        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 4, 'col' => 4], 'j2' => ['row' => 3, 'col' => 4]]);

        $this->actingAs($this->alice)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonPath('legal.deplacements', [['row' => 2, 'col' => 4], ['row' => 5, 'col' => 4], ['row' => 4, 'col' => 3], ['row' => 4, 'col' => 5]]);

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 2, 'col' => 4])
            ->assertOk();

        $partie->refresh();
        $this->assertSame(['row' => 2, 'col' => 4], $partie->pions['j1']);
        $this->assertSame($this->bob->id, $partie->tour_id);
    }

    public function test_diagonale_quand_le_saut_est_bloque_par_un_mur(): void
    {
        $partie = $this->nouvellePartie();
        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 4, 'col' => 4], 'j2' => ['row' => 3, 'col' => 4]], [
            ['r' => 2, 'c' => 3, 'o' => 'h', 'who' => 'j1'],
        ]);

        $this->actingAs($this->alice)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonPath('legal.deplacements', [['row' => 3, 'col' => 3], ['row' => 3, 'col' => 5], ['row' => 5, 'col' => 4], ['row' => 4, 'col' => 3], ['row' => 4, 'col' => 5]]);

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 3, 'col' => 3])
            ->assertOk();

        $partie->refresh();
        $this->assertSame(['row' => 3, 'col' => 3], $partie->pions['j1']);
    }

    public function test_pose_de_mur_valide_et_tour_alterne(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 3, 'c' => 2, 'o' => 'h'])
            ->assertOk()
            ->assertJson(['message' => '🧱 Mur posé.', 'terminee' => false]);

        $partie->refresh();
        $this->assertSame([['r' => 3, 'c' => 2, 'o' => 'h', 'who' => 'j1']], $partie->murs);
        $this->assertSame($this->bob->id, $partie->tour_id);

        $this->actingAs($this->alice)
            ->getJson(route('quoridor.state', $partie))
            ->assertJsonPath('mursRestants.j1', 9);
    }

    public function test_mur_posable_dans_les_deux_dernieres_cases(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 7, 'c' => 7, 'o' => 'h'])
            ->assertOk()
            ->assertJson(['message' => '🧱 Mur posé.', 'terminee' => false]);

        $partie->refresh();
        $this->assertSame([['r' => 7, 'c' => 7, 'o' => 'h', 'who' => 'j1']], $partie->murs);

        $this->actingAs($this->bob)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 7, 'c' => 0, 'o' => 'v'])
            ->assertOk()
            ->assertJson(['message' => '🧱 Mur posé.', 'terminee' => false]);

        $partie->refresh();
        $this->assertContains(['r' => 7, 'c' => 0, 'o' => 'v', 'who' => 'j2'], $partie->murs);
    }

    public function test_mur_refuse_en_chevauchement_du_meme_mur(): void
    {
        $partie = $this->nouvellePartie();
        $partie = $this->recalerPartie($partie, $partie->pions, [
            ['r' => 3, 'c' => 2, 'o' => 'h', 'who' => 'j1'],
        ]);

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 3, 'c' => 2, 'o' => 'h'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ce mur ne peut pas être posé ici.']);
    }

    public function test_mur_qui_bloque_le_chemin_est_refuse(): void
    {
        $partie = $this->nouvellePartie();
        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 8, 'col' => 4], 'j2' => ['row' => 0, 'col' => 0]], [
            ['r' => 0, 'c' => 0, 'o' => 'v', 'who' => 'j2'],
        ]);

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 0, 'c' => 0, 'o' => 'h'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ce mur ne peut pas être posé ici.']);
    }

    public function test_limite_de_murs_atteinte_refuse(): void
    {
        $partie = $this->nouvellePartie();
        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 8, 'col' => 4], 'j2' => ['row' => 0, 'col' => 4]], [
            ['r' => 1, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 2, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 3, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 4, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 5, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 6, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 7, 'c' => 0, 'o' => 'h', 'who' => 'j1'],
            ['r' => 1, 'c' => 2, 'o' => 'h', 'who' => 'j1'],
            ['r' => 2, 'c' => 2, 'o' => 'h', 'who' => 'j1'],
            ['r' => 3, 'c' => 2, 'o' => 'h', 'who' => 'j1'],
        ]);

        $this->actingAs($this->alice)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonCount(0, 'legal.murs');

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'mur', 'r' => 4, 'c' => 2, 'o' => 'h'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Tu n\'as plus de murs à poser.']);
    }

    public function test_victoire_en_atteignant_la_ligne_arrivee(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 7, 'col' => 4])
            ->assertOk();

        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 1, 'col' => 4], 'j2' => ['row' => 0, 'col' => 3]], [], $this->alice->id);

        $res = $this->actingAs($this->alice)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 0, 'col' => 4]);

        $res->assertOk()
            ->assertJsonPath('terminee', true)
            ->assertJsonPath('vainqueur', $this->alice->id);

        $partie->refresh();
        $this->assertSame(QuoridorPartie::STATUT_TERMINEE, $partie->statut);
        $this->assertSame($this->alice->id, $partie->vainqueur_id);
        $this->assertNull($partie->tour_id);

        $point = Point::query()->where('joueur_id', $this->alice->id)->first();
        $this->assertNotNull($point);
        $this->assertSame(25, $point->montant);
        $this->assertSame('quoridor', $point->source);
    }

    public function test_victoire_en_mode_course_du_cote_bleu(): void
    {
        $partie = $this->quoridor->start($this->couple, $this->alice, QuoridorPartie::MODE_COURSE);
        $partie = $this->recalerPartie($partie, ['j1' => ['row' => 8, 'col' => 4], 'j2' => ['row' => 1, 'col' => 4]], [], $this->bob->id);

        $this->actingAs($this->bob)
            ->getJson(route('quoridor.state', $partie))
            ->assertOk()
            ->assertJsonPath('mode', 'course');

        $this->actingAs($this->bob)
            ->postJson(route('quoridor.action', $partie), ['type' => 'pion', 'row' => 0, 'col' => 4])
            ->assertOk()
            ->assertJsonPath('terminee', true)
            ->assertJsonPath('vainqueur', $this->bob->id);

        $partie->refresh();
        $this->assertSame(QuoridorPartie::STATUT_TERMINEE, $partie->statut);
        $this->assertSame($this->bob->id, $partie->vainqueur_id);
    }

    public function test_abandon_offre_victoire_a_l_adversaire(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('quoridor.abandonner', $partie))
            ->assertOk()
            ->assertJsonPath('message', 'Partie abandonnée. Victoire de Bob !');

        $partie->refresh();
        $this->assertSame(QuoridorPartie::STATUT_TERMINEE, $partie->statut);
        $this->assertSame($this->bob->id, $partie->vainqueur_id);

        $point = Point::query()->where('joueur_id', $this->bob->id)->first();
        $this->assertSame(25, $point->montant);
        $this->assertSame('Victoire au Quoridor (abandon)', $point->raison);
    }
}
