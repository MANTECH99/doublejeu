<?php

namespace Tests\Feature;

use App\Models\CarteVerite;
use App\Models\Couple;
use App\Models\DefiEnveloppe;
use App\Models\Enveloppe;
use App\Models\PartieQuestionQuiDeNous;
use App\Models\PartieQuiDeNous;
use App\Models\PartieVO;
use App\Models\QuestionDuJour;
use App\Models\QuestionJournaliere;
use App\Models\QuestionQuiDeNous;
use App\Models\TourVO;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedoublonnerContenuTest extends TestCase
{
    use RefreshDatabase;

    private Couple $couple;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $alice->id,
            'user2_id' => $bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->alice = $alice;
        $alice->forceFill(['couple_id' => $this->couple->id])->save();
        $bob->forceFill(['couple_id' => $this->couple->id])->save();
    }

    public function test_dedoublonne_les_defis_et_reaffecte_les_enveloppes(): void
    {
        $conserve = DefiEnveloppe::create(['texte' => 'Envoie une photo suggestive mais classe', 'couleur' => 'rouge']);
        $doublon = DefiEnveloppe::create(['texte' => 'Envoie une photo suggestive mais classe', 'couleur' => 'rouge']);
        DefiEnveloppe::create(['texte' => 'Un défi unique', 'couleur' => 'bleue']);

        $liee = Enveloppe::create([
            'couple_id' => $this->couple->id,
            'joueur_id' => $this->alice->id,
            'couleur' => 'rouge',
            'defi_id' => $doublon->id,
        ]);

        $this->artisan('contenu:dedoublonner');

        $this->assertSame(1, DefiEnveloppe::where('texte', 'Envoie une photo suggestive mais classe')->count());
        $this->assertSame($conserve->id, $liee->refresh()->defi_id);
    }

    public function test_dedoublonne_les_questions_du_jour_et_reaffecte_le_journal(): void
    {
        $conserve = QuestionDuJour::create(['texte' => 'Pourquoi tu m\'aimes ?', 'categorie' => 'profonde']);
        $doublon = QuestionDuJour::create(['texte' => 'Pourquoi tu m\'aimes ?', 'categorie' => 'profonde']);

        $journaliere = QuestionJournaliere::create([
            'couple_id' => $this->couple->id,
            'question_id' => $doublon->id,
            'jour' => now()->toDateString(),
        ]);

        $this->artisan('contenu:dedoublonner');

        $this->assertSame(1, QuestionDuJour::where('texte', 'Pourquoi tu m\'aimes ?')->count());
        $this->assertSame($conserve->id, $journaliere->refresh()->question_id);
    }

    public function test_dedoublonne_les_cartes_verite_et_reaffecte_les_tours(): void
    {
        $conserve = CarteVerite::create(['texte' => 'Quel est ton premier souvenir de moi ?', 'niveau' => 'doux']);
        $doublon = CarteVerite::create(['texte' => 'Quel est ton premier souvenir de moi ?', 'niveau' => 'doux']);

        $partie = PartieVO::create([
            'couple_id' => $this->couple->id,
            'niveau' => 'doux',
            'status' => 'en_cours',
            'joueur_actif_id' => $this->alice->id,
        ]);

        $tour = TourVO::create([
            'partie_id' => $partie->id,
            'joueur_id' => $this->alice->id,
            'type' => 'verite',
            'carte_id' => $doublon->id,
            'statut' => 'valide',
        ]);

        $this->artisan('contenu:dedoublonner');

        $this->assertSame(1, CarteVerite::where('texte', 'Quel est ton premier souvenir de moi ?')->count());
        $this->assertSame($conserve->id, $tour->refresh()->carte_id);
    }

    public function test_dedoublonne_qui_de_nous_sans_violer_la_contrainte_unique(): void
    {
        $conserve = QuestionQuiDeNous::create(['texte' => 'Qui est le plus têtu ?', 'categorie' => 'relation']);
        $doublon = QuestionQuiDeNous::create(['texte' => 'Qui est le plus têtu ?', 'categorie' => 'relation']);

        $partie = PartieQuiDeNous::create([
            'couple_id' => $this->couple->id,
            'joueur1_id' => $this->couple->user1_id,
            'joueur2_id' => $this->couple->user2_id,
            'statut' => 'terminee',
        ]);

        PartieQuestionQuiDeNous::create(['partie_id' => $partie->id, 'question_id' => $conserve->id, 'ordre' => 1]);
        PartieQuestionQuiDeNous::create(['partie_id' => $partie->id, 'question_id' => $doublon->id, 'ordre' => 2]);

        $this->artisan('contenu:dedoublonner');

        $this->assertSame(1, QuestionQuiDeNous::where('texte', 'Qui est le plus têtu ?')->count());
        $this->assertDatabaseMissing('parties_qui_de_nous_questions', ['question_id' => $doublon->id]);
        $this->assertDatabaseHas('parties_qui_de_nous_questions', ['question_id' => $conserve->id, 'partie_id' => $partie->id]);
    }

    public function test_commande_idempotente(): void
    {
        DefiEnveloppe::create(['texte' => 'Un défi', 'couleur' => 'rouge']);
        DefiEnveloppe::create(['texte' => 'Un défi', 'couleur' => 'rouge']);

        $this->artisan('contenu:dedoublonner')->assertSuccessful();
        $this->artisan('contenu:dedoublonner')->assertSuccessful();

        $this->assertSame(1, DefiEnveloppe::where('texte', 'Un défi')->count());
    }
}
