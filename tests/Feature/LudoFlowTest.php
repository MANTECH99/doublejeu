<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\LudoPartie;
use App\Models\LudoToken;
use App\Models\User;
use App\Services\LudoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LudoFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $carol;

    private Couple $couple;

    private LudoService $ludo;

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

        $this->ludo = app(LudoService::class);
    }

    private function nouvellePartie(): LudoPartie
    {
        return $this->ludo->start($this->couple, $this->alice);
    }

    private function pion(LudoPartie $partie, string $couleur, int $numero): LudoToken
    {
        return $partie->tokens()->where('couleur', $couleur)->where('numero', $numero)->first();
    }

    public function test_start_cree_partie_et_huit_pions_et_redirige(): void
    {
        $this->actingAs($this->alice);

        $this->post(route('ludo.start'))->assertRedirect();

        $partie = LudoPartie::first();
        $this->assertNotNull($partie);
        $this->assertSame('en_cours', $partie->statut);
        $this->assertSame($this->alice->id, $partie->tour_id);
        $this->assertSame(8, $partie->tokens()->count());
        $this->assertSame(4, $partie->tokens()->where('couleur', 'rouge')->count());
        $this->assertSame(4, $partie->tokens()->where('couleur', 'bleue')->count());
    }

    public function test_etat_expose_le_plateau_au_couple(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->bob)
            ->getJson(route('ludo.state', $partie))
            ->assertOk()
            ->assertJsonPath('statut', 'en_cours')
            ->assertJsonPath('tour_id', $this->alice->id)
            ->assertJsonPath('joueurs.j1.couleur', 'rouge')
            ->assertJsonPath('joueurs.j2.couleur', 'bleue')
            ->assertJsonCount(8, 'pions')
            ->assertJsonPath('pions.0.position', -1);
    }

    public function test_non_membre_du_couple_forbidden(): void
    {
        // Carol est liée à un AUTRE couple : le middleware passe, le contrôleur refuse.
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
            ->getJson(route('ludo.state', $partie))
            ->assertForbidden();

        $this->actingAs($this->carol)
            ->get(route('ludo.jouer', $partie))
            ->assertForbidden();
    }

    public function test_seul_le_joueur_courant_peut_lancer(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->bob)
            ->postJson(route('ludo.lancer', $partie))
            ->assertStatus(422);
    }

    public function test_lancer_sans_avoir_bouge_est_refuse(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('ludo.lancer', $partie))
            ->assertOk();

        $this->actingAs($this->alice)
            ->postJson(route('ludo.lancer', $partie))
            ->assertStatus(422);
    }

    public function test_seul_un_six_sort_un_pion_et_autrement_le_tour_passe(): void
    {
        $partie = $this->nouvellePartie();

        $resultat = $this->ludo->lancer($partie, 5);
        $this->assertSame([], $resultat['coups']);
        $this->assertTrue($resultat['passe']);

        // Tour passé à Bob.
        $this->assertSame($this->bob->id, $partie->fresh()->tour_id);

        $resultat = $this->ludo->lancer($partie, 6);
        $this->assertCount(4, $resultat['coups']);
        $this->assertTrue($resultat['bonus']);
        $this->assertFalse($resultat['passe']);
    }

    public function test_passe_ne_cede_pas_le_de_a_l_adversaire(): void
    {
        $partie = $this->nouvellePartie();

        $resultat = $this->ludo->lancer($partie, 5);
        $this->assertTrue($resultat['passe']);
        $this->assertSame(5, $resultat['de_passe']);

        $partie->refresh();
        $this->assertSame($this->bob->id, $partie->tour_id);
        $this->assertNull($partie->dernier_de);
        $this->assertSame(0, $partie->six_compte);

        // Le dé passé est visible pour LES DEUX joueurs.
        $etatAlice = $this->ludo->etat($partie, $this->alice);
        $etatBob = $this->ludo->etat($partie, $this->bob);
        $this->assertSame(5, $etatAlice['de_passe']);
        $this->assertSame(5, $etatBob['de_passe']);
        $this->assertNull($etatBob['dernier_de']);
        $this->assertSame([], $etatBob['legal']);

        // Bob doit pouvoir lancer son propre dé au lieu d'utiliser le reste du lancement d'Alice.
        $this->actingAs($this->bob)
            ->postJson(route('ludo.lancer', $partie))
            ->assertOk();
    }

    public function test_un_relance_apres_pass_efface_le_de_passe(): void
    {
        $partie = $this->nouvellePartie();

        $this->ludo->lancer($partie, 5);
        $partie->refresh();
        $this->assertSame(5, $partie->de_passe);

        // Bob (partenaire) lance un 6 : le de_passe est effacé, le dernier_de prend le dessus.
        $resultat = $this->ludo->lancer($partie, 6);
        $partie->refresh();
        $this->assertFalse($resultat['passe']);
        $this->assertNull($partie->de_passe);
        $this->assertSame(6, $partie->dernier_de);

        $etat = $this->ludo->etat($partie, $this->bob);
        $this->assertSame(6, $etat['dernier_de']);
        $this->assertNull($etat['de_passe']);
    }

    public function test_le_joueur_qui_a_un_six_relance_avec_son_propre_de(): void
    {
        $partie = $this->nouvellePartie();

        // Alice sort un pion avec un 6 : bonus, elle relance.
        $resultat = $this->ludo->lancer($partie, 6);
        $this->ludo->jouer($partie, $resultat['coups'][0]);

        $partie->refresh();
        $this->assertSame($this->alice->id, $partie->tour_id);
        $this->assertNull($partie->dernier_de);

        // Alice lance un 2, déplace, et le tour passe à Bob.
        $resultat = $this->ludo->lancer($partie, 2);
        $this->ludo->jouer($partie, $resultat['coups'][0]);

        $partie->refresh();
        $this->assertSame($this->bob->id, $partie->tour_id);

        // Bob lance un 2 sans coup possible : tour passé à Alice, mais sans offrir son 2.
        $resultat = $this->ludo->lancer($partie, 2);
        $this->assertTrue($resultat['passe']);

        $partie->refresh();
        $this->assertSame($this->alice->id, $partie->tour_id);
        $this->assertNull($partie->dernier_de);

        // Alice ne peut pas jouer avec le 2 de Bob : elle doit lancer son propre dé.
        $this->actingAs($this->alice)
            ->postJson(route('ludo.bouger', $partie), ['pion' => $this->pion($partie, 'rouge', 0)->id])
            ->assertStatus(422);

        $this->actingAs($this->alice)
            ->postJson(route('ludo.lancer', $partie))
            ->assertOk();
    }

    public function test_entree_sur_six_et_bonus_relance(): void
    {
        $partie = $this->nouvellePartie();

        $resultat = $this->ludo->lancer($partie, 6);
        $pionId = $resultat['coups'][0];

        $this->ludo->jouer($partie, $pionId);

        $pion = $partie->tokens()->whereKey($pionId)->first();
        $this->assertSame(1, $pion->position);
        $this->assertNull($partie->fresh()->dernier_de);
        $this->assertSame(0, $partie->fresh()->six_compte);
        // 6 = bonus : le même joueur relance.
        $this->assertSame($this->alice->id, $partie->fresh()->tour_id);
    }

    public function test_pion_adverse_envoye_au_depart(): void
    {
        $partie = $this->nouvellePartie();

        $rouge = $this->pion($partie, 'rouge', 0);
        $bleue = $this->pion($partie, 'bleue', 0);

        $rouge->update(['position' => 27]); // en avançant de 6 → case 33
        $bleue->update(['position' => 7]);  // case (26+7)%52 = 33, même case

        $this->assertSame(33, (26 + $bleue->fresh()->position) % 52);

        $resultat = $this->ludo->lancer($partie, 6);
        $this->assertContains($rouge->id, $resultat['coups']);

        $res = $this->ludo->jouer($partie, $rouge->id);

        $this->assertTrue($res['mange']);
        $this->assertSame(33, $rouge->fresh()->position);
        $this->assertSame(-1, $bleue->fresh()->position);
    }

    public function test_case_depart_protegee_contre_le_manger(): void
    {
        $partie = $this->nouvellePartie();

        $rouge = $this->pion($partie, 'rouge', 0);
        $bleue = $this->pion($partie, 'bleue', 0);

        $rouge->update(['position' => 21]);
        $bleue->update(['position' => 1]); // case (26+1) = 27, case sûre (porte bleue)

        $resultat = $this->ludo->lancer($partie, 6);
        $this->assertContains($rouge->id, $resultat['coups']);

        $this->ludo->jouer($partie, $rouge->id);

        $this->assertSame(27, $rouge->fresh()->position);
        $this->assertSame(1, $bleue->fresh()->position);
    }

    public function test_case_etoile_protegee_contre_le_manger(): void
    {
        $partie = $this->nouvellePartie();

        $rouge = $this->pion($partie, 'rouge', 0);
        $bleue = $this->pion($partie, 'bleue', 0);

        $rouge->update(['position' => 3]);
        $bleue->update(['position' => 35]); // case (26+35)%52 = 9, case étoile rouge

        $resultat = $this->ludo->lancer($partie, 6);
        $this->assertContains($rouge->id, $resultat['coups']);

        $this->ludo->jouer($partie, $rouge->id);

        $this->assertSame(9, $rouge->fresh()->position);
        $this->assertSame(35, $bleue->fresh()->position);
    }

    public function test_case_etoile_neutre_protegee_contre_le_manger(): void
    {
        $partie = $this->nouvellePartie();

        $rouge = $this->pion($partie, 'rouge', 0);
        $bleue = $this->pion($partie, 'bleue', 0);

        $rouge->update(['position' => 16]);
        $bleue->update(['position' => 48]); // case (26+48)%52 = 22, case étoile neutre

        $resultat = $this->ludo->lancer($partie, 6);
        $this->assertContains($rouge->id, $resultat['coups']);

        $this->ludo->jouer($partie, $rouge->id);

        $this->assertSame(22, $rouge->fresh()->position);
        $this->assertSame(48, $bleue->fresh()->position);
    }

    public function test_six_consecutifs_offrent_un_bonus_illimite(): void
    {
        $partie = $this->nouvellePartie();

        for ($i = 0; $i < 5; $i++) {
            $resultat = $this->ludo->lancer($partie, 6);
            $this->assertTrue($resultat['bonus']);
            $this->ludo->jouer($partie, $resultat['coups'][0]);
        }

        $partie->refresh();
        $this->assertSame($this->alice->id, $partie->tour_id);
        $this->assertSame(0, $partie->six_compte);
    }

    public function test_victoire_termine_la_partie_et_donne_des_points(): void
    {
        $partie = $this->nouvellePartie();

        $pions = $partie->tokens()->where('couleur', 'rouge')->get();
        foreach ($pions as $i => $pion) {
            $pion->update(['position' => $i === 2 ? 56 : 57]);
        }

        $resultat = $this->ludo->lancer($partie, 1);
        $this->assertCount(1, $resultat['coups']);

        $res = $this->ludo->jouer($partie, $resultat['coups'][0]);

        $this->assertTrue($res['terminee']);
        $this->assertSame($this->alice->id, $res['vainqueur']);
        $this->assertSame('terminee', $partie->fresh()->statut);
        $this->assertSame($this->alice->id, $partie->fresh()->vainqueur_id);

        $this->assertDatabaseHas('points', [
            'couple_id' => $this->couple->id,
            'joueur_id' => $this->alice->id,
            'montant' => 25,
            'source' => 'ludo',
        ]);

        $this->assertSame(25, $this->couple->fresh()->score_total);
    }

    public function test_abandon_termine_la_partie_au_profit_du_partenaire(): void
    {
        $partie = $this->nouvellePartie();

        $this->actingAs($this->alice)
            ->postJson(route('ludo.abandonner', $partie))
            ->assertOk();

        $this->assertSame('terminee', $partie->fresh()->statut);
        $this->assertSame($this->bob->id, $partie->fresh()->vainqueur_id);

        $this->assertDatabaseHas('points', [
            'couple_id' => $this->couple->id,
            'joueur_id' => $this->bob->id,
            'montant' => 25,
        ]);
    }

    public function test_bouger_un_pion_adverse_est_refuse(): void
    {
        $partie = $this->nouvellePartie();
        $partie->forceFill(['dernier_de' => 6])->save();

        $pionBleu = $this->pion($partie, 'bleue', 0);

        $this->actingAs($this->alice)
            ->postJson(route('ludo.bouger', $partie), ['pion' => $pionBleu->id])
            ->assertStatus(422);
    }

    public function test_etat_compte_les_pions_jouables_quand_le_de_est_lance(): void
    {
        $partie = $this->nouvellePartie();

        $this->ludo->lancer($partie, 6);

        $this->actingAs($this->alice)
            ->getJson(route('ludo.state', $partie))
            ->assertOk()
            ->assertJsonCount(4, 'legal');
    }
}
