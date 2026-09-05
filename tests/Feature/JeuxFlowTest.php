<?php

namespace Tests\Feature;

use App\Http\Controllers\QuiDeNousDeuxController;
use App\Models\CarteAction;
use App\Models\CarteVerite;
use App\Models\Couple;
use App\Models\DefiEnveloppe;
use App\Models\Gage;
use App\Models\GrilleMotsCroises;
use App\Models\MissionOuiNon;
use App\Models\MissionSecrete;
use App\Models\MotCroiseContenu;
use App\Models\PartieOuiNon;
use App\Models\PartieQuiDeNous;
use App\Models\PartieVO;
use App\Models\Point;
use App\Models\QuestionDuJour;
use App\Models\QuestionOuiNon;
use App\Models\QuestionQuiDeNous;
use App\Models\QuestionQuiz;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Recompense;
use App\Models\ReponseOuiNon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JeuxFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Couple $couple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice', 'gender' => 'Femme']);
        $this->bob = User::factory()->create(['name' => 'Bob', 'gender' => 'Homme']);

        $this->couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $this->alice->id,
            'user2_id' => $this->bob->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $this->alice->forceFill(['couple_id' => $this->couple->id])->save();
        $this->bob->forceFill(['couple_id' => $this->couple->id])->save();

        CarteVerite::create(['texte' => 'Ton plus beau souvenir avec nous ?', 'niveau' => 'doux']);
        CarteVerite::create(['texte' => 'Dis ce que tu trouves le plus sexy chez moi', 'niveau' => 'doux']);
        CarteAction::create(['texte' => 'Envoie un selfie avec la langue tirée', 'niveau' => 'doux']);
        CarteAction::create(['texte' => 'Fais 10 squats en comptant en espagnol', 'niveau' => 'doux']);
        Gage::create(['texte' => 'Mime ton animal préféré']);
        Gage::create(['texte' => 'Danse comme si personne ne regardait']);
        DefiEnveloppe::create(['texte' => 'Chante une sérénade', 'couleur' => 'rouge']);
        DefiEnveloppe::create(['texte' => 'Dis 3 choses que tu adores chez moi', 'couleur' => 'bleue']);
        QuestionOuiNon::create(['texte' => 'Te verrais-tu vivre à l\'étranger ?', 'categorie' => 'aventure']);
        QuestionOuiNon::create(['texte' => 'Crois-tu à l\'amour au premier regard ?', 'categorie' => 'intimite']);
    }

    public function test_vo_complete_round(): void
    {
        $this->actingAs($this->alice);

        $this->post(route('vo.start'), ['niveau' => 'doux'])->assertRedirect();
        $partie = PartieVO::first();
        $this->assertNotNull($partie);
        $actif = $partie->joueur_actif_id;

        $this->actingAs(User::find($actif))
            ->postJson(route('vo.choisir', $partie), ['type' => 'verite'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $tour = $partie->tours()->first();
        $this->assertNotNull($tour->carte_id);

        $this->postJson(route('vo.repondre', $partie), [
            'accepte' => 1,
            'reponse' => 'Je t\'ai menti sur mes projets cette année',
        ])->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('Je t\'ai menti sur mes projets cette année', $tour->fresh()->reponse);

        $validatorId = $partie->couple->partnerOf(User::find($actif))->id;

        $this->actingAs(User::find($validatorId))
            ->postJson(route('vo.valider', $partie))
            ->assertOk()
            ->assertJson(['points' => 10]);

        $this->assertDatabaseHas('points', [
            'couple_id' => $this->couple->id,
            'joueur_id' => $actif,
            'montant' => 10,
        ]);

        $valideur = $partie->fresh();
        if ($valideur->couple->user1_id === $actif) {
            $this->assertEquals(10, $valideur->score_joueur1);
        } else {
            $this->assertEquals(10, $valideur->score_joueur2);
        }
    }

    public function test_vo_refus_gives_gage_and_switch(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('vo.start'), ['niveau' => 'doux'])->assertRedirect();
        $partie = PartieVO::first();
        $actif = $partie->joueur_actif_id;
        $actifUser = User::find($actif);
        $partner = $partie->couple->partnerOf($actifUser);

        $this->actingAs($actifUser)
            ->postJson(route('vo.choisir', $partie), ['type' => 'action'])
            ->assertOk();

        $this->postJson(route('vo.repondre', $partie), ['accepte' => 0])
            ->assertOk()
            ->assertJson(['statut' => 'refuse'])
            ->assertJsonStructure(['gage']);

        $partie->refresh();
        $this->assertNotEquals($partie->joueur_actif_id, $actif);
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'montant' => -5]);
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'montant' => 5]);
    }

    public function test_vo_partenaire_peut_invalider_verite(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('vo.start'), ['niveau' => 'doux'])->assertRedirect();
        $partie = PartieVO::first();
        $actif = $partie->joueur_actif_id;
        $actifUser = User::find($actif);
        $partner = $partie->couple->partnerOf($actifUser);

        $this->actingAs($actifUser)
            ->postJson(route('vo.choisir', $partie), ['type' => 'verite'])
            ->assertOk();

        $this->postJson(route('vo.repondre', $partie), ['accepte' => 1, 'reponse' => 'Une fausse vérité'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($partner)
            ->postJson(route('vo.invalider', $partie))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('refuse', $partie->tours()->first()->fresh()->statut);
        $this->assertNotEquals($partie->fresh()->joueur_actif_id, $actif);
        $this->assertDatabaseMissing('points', ['couple_id' => $this->couple->id]);
    }

    public function test_ouinon_double_oui_creates_mission(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('ouinon.start'))->assertRedirect();
        $partie = PartieOuiNon::first();

        $questions = ReponseOuiNon::where('partie_id', $partie->id)->distinct('question_id')->pluck('question_id');

        $this->actingAs($this->alice);
        foreach ($questions as $q) {
            $this->postJson(route('ouinon.repondre', $partie), ['question_id' => $q, 'reponse' => 'oui'])->assertOk();
        }
        $this->actingAs($this->bob);
        foreach ($questions as $q) {
            $this->postJson(route('ouinon.repondre', $partie), ['question_id' => $q, 'reponse' => 'oui'])->assertOk();
        }

        $this->assertTrue(MissionOuiNon::where('couple_id', $this->couple->id)->exists());
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'source' => 'oui_non', 'montant' => 5]);
    }

    public function test_ouinon_explication_survives_et_est_vue_par_le_partenaire(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('ouinon.start'))->assertRedirect();
        $partie = PartieOuiNon::first();

        $questions = ReponseOuiNon::where('partie_id', $partie->id)
            ->distinct('question_id')
            ->pluck('question_id');

        foreach ($questions as $q) {
            $this->actingAs($this->alice)
                ->postJson(route('ouinon.repondre', $partie), ['question_id' => $q, 'reponse' => 'oui'])
                ->assertOk();
        }

        foreach ($questions as $q) {
            $this->actingAs($this->bob)
                ->postJson(route('ouinon.repondre', $partie), ['question_id' => $q, 'reponse' => 'non'])
                ->assertOk();
        }

        $this->actingAs($this->bob)
            ->postJson(route('ouinon.expliquer', $partie), [
                'question_id' => $questions->first(),
                'explication' => 'Je suis timide pour le moment.',
            ])->assertOk();

        $this->assertDatabaseHas('reponses_oui_non', [
            'explication' => 'Je suis timide pour le moment.',
        ]);

        $stateAlice = $this->actingAs($this->alice)
            ->getJson(route('ouinon.state', $partie))
            ->assertOk()
            ->json('questions');

        $cible = collect($stateAlice)->firstWhere('id', $questions->first());
        $this->assertSame('non', $cible['saReponse']);
        $this->assertSame('Je suis timide pour le moment.', $cible['explication']);

        $stateBob = $this->actingAs($this->bob)
            ->getJson(route('ouinon.state', $partie))
            ->assertOk()
            ->json('questions');

        $cibleBob = collect($stateBob)->firstWhere('id', $questions->first());
        $this->assertSame('Je suis timide pour le moment.', $cibleBob['maExplication']);

        $this->actingAs($this->bob)
            ->get(route('ouinon.jouer', $partie))
            ->assertOk();
        $this->actingAs($this->bob)
            ->get(route('ouinon.index'))
            ->assertOk()
            ->assertSee('Rouvrir');
    }

    public function test_enveloppe_flow(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('enveloppe.nouvelle'))->assertRedirect(route('enveloppe.index'));

        $this->getJson(route('enveloppe.state', $this->couple))->assertOk();

        $enveloppe = $this->couple->enveloppes()->where('joueur_id', $this->alice->id)->first();
        $this->assertNotNull($enveloppe);

        $this->postJson(route('enveloppe.ouvrir', [$this->couple, $enveloppe]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->postJson(route('enveloppe.repondre', [$this->couple, $enveloppe]), ['accepte' => 1])
            ->assertOk();

        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'joueur_id' => $this->alice->id, 'montant' => 15]);

        $state = $this->getJson(route('enveloppe.state', $this->couple))->assertOk()->json();
        $aliceJ = collect($state['joueurs'])->firstWhere('id', $this->alice->id);
        $this->assertEquals(15, $aliceJ['score']);
        $this->assertFalse($state['recompenseEnvoyee']);

        $this->postJson(route('enveloppe.recompense', $this->couple), [
            'perdant_id' => $this->bob->id,
            'texte' => 'Un massage de 30 minutes',
        ])->assertStatus(422); // partie non terminée

        $this->couple->enveloppes()->update(['statut' => 'realisee']);

        $this->postJson(route('enveloppe.recompense', $this->couple), [
            'perdant_id' => $this->bob->id,
            'texte' => 'Un massage de 30 minutes',
        ])->assertOk();

        $state2 = $this->getJson(route('enveloppe.state', $this->couple))->assertOk()->json();
        $this->assertTrue($state2['recompenseEnvoyee']);
        $this->assertEquals($this->alice->name, $state2['recompense']['gagnant']);
        $this->assertEquals($this->bob->name, $state2['recompense']['perdant']);
        $this->assertEquals('Un massage de 30 minutes', $state2['recompense']['texte']);

        // Le perdant ne peut pas exiger une récompense
        $this->actingAs($this->bob)
            ->postJson(route('enveloppe.recompense', $this->couple), [
                'perdant_id' => $this->alice->id,
                'texte' => 'Pas possible',
            ])->assertStatus(422);
    }

    public function test_mission_secrete_flow(): void
    {
        $this->actingAs($this->alice);

        $res = $this->postJson(route('mission.nouvelle'), ['frequence' => 24])->assertOk();
        $mission = MissionSecrete::find($res->json('id'));

        $this->postJson(route('mission.reveler', $mission))->assertOk();
        $this->postJson(route('mission.accomplir', $mission))->assertOk();

        // Silence total : aucun point, aucune « devin » par mission, elle reste accomplie.
        $this->assertDatabaseMissing('points', ['couple_id' => $this->couple->id, 'joueur_id' => $this->bob->id]);
        $this->assertEquals('accomplie', $mission->fresh()->statut);
        $this->assertNull($mission->fresh()->devine);

        // Bob soupçonne (« oui ») → mission démasquée, +10 pts chacun.
        $this->actingAs($this->bob);
        $this->postJson(route('mission.question'), ['reponse' => 'oui'])->assertOk();
        $this->assertEquals('demasquee', $mission->fresh()->statut);
        $this->assertEquals('mission', $mission->fresh()->devine);
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'joueur_id' => $this->bob->id, 'montant' => 10]);
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'joueur_id' => $this->alice->id, 'montant' => 10]);

        // 5 réponses max par jour : les 5 premières sont acceptées, la 6e est refusée.
        $this->actingAs($this->bob);
        foreach (['non', 'oui', 'non', 'oui'] as $r) {
            $this->postJson(route('mission.question'), ['reponse' => $r])->assertOk();
        }
        $this->postJson(route('mission.question'), ['reponse' => 'oui'])->assertStatus(422);
        $this->postJson(route('mission.question'), ['reponse' => 'non'])->assertStatus(422);

        // Alice en tire une 2e, l'accomplit en silence → Bob répond « non » : +25 pour Alice seule.
        $this->travel(6)->minutes();
        $this->actingAs($this->alice);
        $res2 = $this->postJson(route('mission.nouvelle'), ['frequence' => 24])->assertOk();
        $mission2 = MissionSecrete::find($res2->json('id'));
        $this->postJson(route('mission.reveler', $mission2))->assertOk();
        $this->postJson(route('mission.accomplir', $mission2))->assertOk();

        $this->travel(1)->day();
        $this->actingAs($this->bob);
        $nbPoints = Point::count();
        $this->postJson(route('mission.question'), ['reponse' => 'non'])->assertOk();
        $this->assertEquals('accomplie', $mission2->fresh()->statut);
        $this->assertEquals('spontane', $mission2->fresh()->devine);
        $this->assertDatabaseHas('points', ['couple_id' => $this->couple->id, 'joueur_id' => $this->alice->id, 'montant' => 25]);
        $this->assertSame($nbPoints + 1, Point::count());

        // Fausse accusation le lendemain (aucune mission en jeu) → aucun point ajouté.
        $this->travel(1)->day();
        $this->postJson(route('mission.question'), ['reponse' => 'oui'])->assertOk();
        $this->assertSame($nbPoints + 1, Point::count());

        // Vue propriétaire : mission réussie en secret ; vue partenaire : « Raté ».
        $this->actingAs($this->alice)
            ->get(route('mission.index'))
            ->assertOk()
            ->assertSee('réussie en secret', false);

        $this->actingAs($this->bob)
            ->get(route('mission.index'))
            ->assertOk()
            ->assertSee('Raté');
    }

    public function test_recompense_and_custom_cards(): void
    {
        $this->actingAs($this->alice);

        $this->postJson(route('recompenses.creer'), ['texte' => 'Un massage, perdant !'])->assertOk();
        $recompense = Recompense::first();

        $this->actingAs($this->bob);
        $this->postJson(route('recompenses.marquer', $recompense))->assertOk();
        $this->assertEquals('offerte', $recompense->fresh()->statut);

        $this->actingAs($this->bob);
        $this->post(route('cartes.verite'), ['texte' => 'Ma carte perso', 'niveau' => 'chaud'])
            ->assertRedirect();
        $this->assertDatabaseHas('cartes_verite', ['texte' => 'Ma carte perso', 'created_by' => $this->bob->id]);

        $id = CarteVerite::where('texte', 'Ma carte perso')->first()->id;
        $this->delete(route('cartes.detruire', ['type' => 'verite', 'id' => $id]))->assertRedirect();
        $this->assertDatabaseMissing('cartes_verite', ['id' => $id]);

        $this->assertDatabaseCount('points', 0);
    }

    public function test_quiz_tu_me_connais_flow(): void
    {
        for ($i = 0; $i < 8; $i++) {
            QuestionQuiz::create([
                'texte_soi' => "Question sur moi $i",
                'texte_partenaire' => "Question sur mon/ma partenaire $i",
            ]);
        }

        $this->actingAs($this->alice);
        $this->post(route('quiz.start'))->assertRedirect();

        $session = QuizSession::first();
        $this->assertNotNull($session);
        $this->assertEquals(8, QuizSessionQuestion::where('session_id', $session->id)->count());

        $sqs = QuizSessionQuestion::where('session_id', $session->id)->get();

        // 4 questions sur Alice : Bob (devinant) répond, Alice (cible) juge (1 raté volontaire).
        $surAlice = $sqs->where('cible_id', $this->alice->id);
        $this->assertCount(4, $surAlice);

        foreach ($surAlice as $i => $sq) {
            $this->actingAs($this->bob)
                ->postJson(route('quiz.repondre', $session), ['question_id' => $sq->id, 'reponse' => $i === 0 ? 'Quiche' : 'Pizza'])
                ->assertOk();
            // La cible ne répond pas : elle ne fait que juger.
            $this->actingAs($this->alice)
                ->postJson(route('quiz.repondre', $session), ['question_id' => $sq->id, 'reponse' => 'Pizza'])
                ->assertStatus(422);
            $this->actingAs($this->alice)
                ->postJson(route('quiz.juger', $session), [
                    'question_id' => $sq->id,
                    'correct' => $i !== 0,
                    'bonne_reponse' => $i === 0 ? 'Pizza' : null,
                ])->assertOk();
        }

        // 4 questions sur Bob : Alice (devinant) répond parfaitement, Bob (cible) juge.
        $surBob = $sqs->where('cible_id', $this->bob->id);
        $this->assertCount(4, $surBob);

        foreach ($surBob as $sq) {
            $this->actingAs($this->alice)
                ->postJson(route('quiz.repondre', $session), ['question_id' => $sq->id, 'reponse' => 'Dessin'])
                ->assertOk();
            $this->actingAs($this->bob)
                ->postJson(route('quiz.juger', $session), [
                    'question_id' => $sq->id,
                    'correct' => true,
                ])->assertOk();
        }

        $this->assertEquals('terminee', $session->fresh()->statut);

        $this->assertEquals(30, Point::where('joueur_id', $this->bob->id)->where('source', 'quiz')->sum('montant'));
        $this->assertEquals(40, Point::where('joueur_id', $this->alice->id)->where('source', 'quiz')->sum('montant'));

        $this->assertDatabaseHas('quiz_session_questions', [
            'session_id' => $session->id,
            'resultat' => 'manque',
        ]);
        $this->assertDatabaseHas('quiz_session_questions', [
            'session_id' => $session->id,
            'resultat' => 'match',
        ]);
        $this->assertDatabaseHas('quiz_session_questions', [
            'session_id' => $session->id,
            'resultat' => 'manque',
            'bonne_reponse' => 'Pizza',
        ]);
    }

    public function test_qui_de_nous_deux_flow(): void
    {
        // Alice crée une question perso (elle rejoindra le tirage).
        $this->actingAs($this->alice);
        $this->post(route('qdn2.questions.creer'), [
            'texte' => 'Qui de nous deux ronfle le plus ?',
            'categorie' => 'habitudes',
        ])->assertRedirect();

        $this->assertDatabaseHas('questions_qui_de_nous', [
            'texte' => 'Qui de nous deux ronfle le plus ?',
            'created_by' => $this->alice->id,
        ]);

        // Banque officielle.
        foreach ([
            'Qui de nous deux est le plus bavard ?' => 'personnalite',
            'Qui de nous deux est le plus têtu ?' => 'personnalite',
            'Qui de nous deux se lève le plus tôt ?' => 'vie_quotidienne',
            'Qui de nous deux cuisinerait le mieux ?' => 'vie_quotidienne',
            'Qui de nous deux dit je t\'aime en premier ?' => 'relation',
            'Qui de nous deux est le plus jaloux ?' => 'relation',
            'Qui de nous deux ronfle le plus ?' => 'habitudes',
            'Qui de nous deux boit le plus de café ?' => 'habitudes',
        ] as $texte => $categorie) {
            QuestionQuiDeNous::create(['texte' => $texte, 'categorie' => $categorie]);
        }

        $this->actingAs($this->alice);
        $this->post(route('qdn2.start'))->assertRedirect();

        $partie = PartieQuiDeNous::first();
        $this->assertNotNull($partie);
        $this->assertEquals(QuiDeNousDeuxController::NB_QUESTIONS, $partie->partieQuestions()->count());

        // Chaque question a 2 réponses pré-créées (une par joueur).
        foreach ($partie->partieQuestions as $pq) {
            $this->assertEquals(2, $pq->reponses()->count());
        }

        $questions = $partie->partieQuestions;

        // Alice répond toute seule : encore rien de révélé.
        $this->actingAs($this->alice);
        foreach ($questions as $i => $pq) {
            $this->postJson(route('qdn2.repondre', $partie), [
                'question_id' => $pq->id,
                'designation' => $i === 0 ? 'moi' : 'partenaire',
            ])->assertOk();
        }

        $state1 = $this->getJson(route('qdn2.state', $partie))->assertOk()->json();
        $this->assertEquals('en_cours', $state1['status']);
        $this->assertNull($state1['questions'][0]['resultat']);

        // Bob répond « moi » partout : il se désigne lui-même.
        // Alice avait désigné « partenaire » les questions 1..n → accord parfait.
        $this->actingAs($this->bob);
        foreach ($questions as $i => $pq) {
            $this->postJson(route('qdn2.repondre', $partie), [
                'question_id' => $pq->id,
                'designation' => 'moi',
            ])->assertOk();
        }

        $partie->refresh();
        $this->assertEquals('terminee', $partie->statut);

        // Question 0 seule : Alice a dit « moi » et Bob « moi » → divergence.
        // Toutes les autres : Alice « partenaire » + Bob « moi » → accord (Bob désigné).
        $state = $this->getJson(route('qdn2.state', $partie))->assertOk()->json();
        $this->assertEquals('terminee', $state['status']);
        $this->assertSame('divergence', $state['questions'][0]['resultat']);
        foreach (array_slice($state['questions'], 1) as $q) {
            $this->assertSame('accord', $q['resultat']);
        }

        // Points : NB_QUESTIONS - 1 accords × 5 pts chacun.
        $this->assertEquals((QuiDeNousDeuxController::NB_QUESTIONS - 1) * 5, $state['mesPoints']);
        $this->assertEquals((QuiDeNousDeuxController::NB_QUESTIONS - 1) * 5, $state['sesPoints']);
        $this->assertDatabaseHas('points', [
            'couple_id' => $this->couple->id,
            'source' => 'qui_de_nous',
            'montant' => 5,
        ]);

        // Déjà répondu → 422.
        $firstPq = $questions->first();
        $this->actingAs($this->alice)
            ->postJson(route('qdn2.repondre', $partie), ['question_id' => $firstPq->id, 'designation' => 'moi'])
            ->assertStatus(422);

        // Pages rendues.
        $this->actingAs($this->bob)->get(route('qdn2.index'))->assertOk();
    }

    public function test_qui_de_nous_deux_mode_debat(): void
    {
        QuestionQuiDeNous::create(['texte' => 'Qui est le plus bavard ?', 'categorie' => 'personnalite']);
        QuestionQuiDeNous::create(['texte' => 'Qui est le plus têtu ?', 'categorie' => 'personnalite']);

        $this->actingAs($this->alice);
        $this->post(route('qdn2.start'))->assertRedirect();
        $partie = PartieQuiDeNous::first();
        $pq1 = $partie->partieQuestions()->orderBy('ordre')->first();

        // Alice désigne « moi », Bob désigne « moi » → divergence.
        $this->actingAs($this->alice)
            ->postJson(route('qdn2.repondre', $partie), ['question_id' => $pq1->id, 'designation' => 'moi'])
            ->assertOk();
        $this->actingAs($this->bob)
            ->postJson(route('qdn2.repondre', $partie), ['question_id' => $pq1->id, 'designation' => 'moi'])
            ->assertOk();

        $state = $this->getJson(route('qdn2.state', $partie))->assertOk()->json();
        $q1 = collect($state['questions'])->firstWhere('id', $pq1->id);
        $this->assertSame('divergence', $q1['resultat']);
        $this->assertFalse($q1['debat_resolu']);
        // Divergence → aucun point.
        $this->assertDatabaseMissing('points', ['couple_id' => $this->couple->id, 'source' => 'qui_de_nous']);

        // Le mode débat : on marque « on s'est expliqués ».
        $this->actingAs($this->alice)
            ->postJson(route('qdn2.resoudre', $partie), ['question_id' => $pq1->id])
            ->assertOk();
        $this->assertTrue($pq1->fresh()->debat_resolu);

        // Déjà résolu → 422.
        $this->actingAs($this->alice)
            ->postJson(route('qdn2.resoudre', $partie), ['question_id' => $pq1->id])
            ->assertStatus(422);

        // Pas de débat sur une question en accord → 422.
        $pq2 = $partie->partieQuestions()->orderBy('ordre')->skip(1)->first();
        $this->actingAs($this->alice)
            ->postJson(route('qdn2.repondre', $partie), ['question_id' => $pq2->id, 'designation' => 'moi'])
            ->assertOk();
        $this->actingAs($this->bob)
            ->postJson(route('qdn2.repondre', $partie), ['question_id' => $pq2->id, 'designation' => 'partenaire'])
            ->assertOk();

        $this->postJson(route('qdn2.resoudre', $partie), ['question_id' => $pq2->id])
            ->assertStatus(422);

        // La partie se termine quand tous sont résolus.
        $this->assertEquals('terminee', $partie->fresh()->statut);
    }

    public function test_qui_de_nous_deux_questions_privees(): void
    {
        $this->actingAs($this->alice);
        $this->post(route('qdn2.questions.creer'), [
            'texte' => 'Qui fait le plus de crêpes ?',
            'categorie' => 'vie_quotidienne',
        ])->assertRedirect();

        $id = QuestionQuiDeNous::where('texte', 'Qui fait le plus de crêpes ?')->first()->id;

        // Bob ne peut pas supprimer la question d'Alice.
        $this->actingAs($this->bob)
            ->delete(route('qdn2.questions.detruire', $id))
            ->assertForbidden();
        $this->assertDatabaseHas('questions_qui_de_nous', ['id' => $id]);

        // Catégorie invalide → 422.
        $this->actingAs($this->alice)
            ->post(route('qdn2.questions.creer'), ['texte' => 'Test', 'categorie' => 'inconnue'])
            ->assertSessionHasErrors('categorie');

        // Alice supprime la sienne.
        $this->actingAs($this->alice)
            ->delete(route('qdn2.questions.detruire', $id))
            ->assertRedirect();
        $this->assertDatabaseMissing('questions_qui_de_nous', ['id' => $id]);

        $this->actingAs($this->alice)->get(route('qdn2.questions'))->assertOk();
    }

    public function test_question_du_jour_flow(): void
    {
        QuestionDuJour::create(['texte' => 'Quel est ton avenir idéal ?', 'categorie' => 'profonde']);
        QuestionDuJour::create(['texte' => 'Quelle est ta blague préférée ?', 'categorie' => 'drole']);

        $this->actingAs($this->alice);
        $this->get(route('question.index'))->assertOk();

        $this->assertDatabaseCount('questions_journalieres', 1);

        $s0 = $this->getJson(route('question.state'))->assertOk()->json();
        $this->assertFalse($s0['jaiRepondu']);
        $this->assertNull($s0['saReponse']);

        $this->postJson(route('question.repondre'), ['reponse' => 'Devenir pâtissier'])->assertOk();

        $s1 = $this->getJson(route('question.state'))->assertOk()->json();
        $this->assertTrue($s1['jaiRepondu']);
        $this->assertFalse($s1['ilElleARepondu']);
        $this->assertFalse($s1['revelee']);

        $this->actingAs($this->bob);
        $s2 = $this->getJson(route('question.state'))->assertOk()->json();
        $this->assertTrue($s2['ilElleARepondu']);

        $this->postJson(route('question.repondre'), ['reponse' => 'Devenir pâtissier'])->assertOk();

        $s3 = $this->getJson(route('question.state'))->assertOk()->json();
        $this->assertTrue($s3['revelee']);
        $this->assertSame('Devenir pâtissier', $s3['saReponse']);

        // Toujours une seule question du jour malgré les accès multiples
        $this->assertDatabaseCount('questions_journalieres', 1);
    }

    public function test_meteo_couple_flow(): void
    {
        $this->actingAs($this->alice);

        // Humeur invalide refusée
        $this->postJson(route('meteo.checkin'), ['humeur' => 'ensoleile'])->assertStatus(422);

        $this->postJson(route('meteo.checkin'), ['humeur' => 'heureux', 'commentaire' => 'belle journée'])->assertOk();

        // On peut partager une 2e fois par jour…
        $this->postJson(route('meteo.checkin'), ['humeur' => 'calme', 'commentaire' => 'soirée tranquille'])->assertOk();

        // …mais pas un 3e.
        $this->postJson(route('meteo.checkin'), ['humeur' => 'triste'])->assertStatus(422);

        $s = $this->getJson(route('meteo.state'))->assertOk()->json();
        $this->assertTrue($s['jaiRepondu']);
        $this->assertFalse($s['ilElleARepondu']);
        $this->assertFalse($s['revelee']);
        // Le partage le plus récent est affiché.
        $this->assertSame('calme', $s['maHumeur']);
        $this->assertCount(2, $s['mesPartages']);
        $this->assertSame('heureux', $s['mesPartages'][0]['humeur']);
        $this->assertSame('calme', $s['mesPartages'][1]['humeur']);

        $this->actingAs($this->bob);
        $this->postJson(route('meteo.checkin'), ['humeur' => 'stress'])->assertOk();

        $s2 = $this->getJson(route('meteo.state'))->assertOk()->json();
        $this->assertTrue($s2['revelee']);
        $this->assertFalse($s2['lesDeuxMauvais']);
        // Recent de bob = stress ; recent d'alice = calme.
        $this->assertSame('🌦️', $s2['synthese']['emoji']);

        $this->assertDatabaseHas('meteo_couples', [
            'couple_id' => $this->couple->id,
            'humeur_user1' => 'heureux',
            'humeur_user1_2' => 'calme',
            'commentaire_user1' => 'belle journée',
            'commentaire_user1_2' => 'soirée tranquille',
            'humeur_user2' => 'stress',
        ]);

        // Idées personnalisées : on peut écrire, une par jour et par personne
        $this->actingAs($this->alice);
        $this->postJson(route('meteo.suggestion'), ['suggestion' => 'Pique-nique au parc ce week-end'])
            ->assertOk();
        $this->postJson(route('meteo.suggestion'), ['suggestion' => 'Encore une idée'])->assertStatus(422);
        $this->postJson(route('meteo.suggestion'), ['suggestion' => '  '])->assertStatus(422);

        $this->actingAs($this->bob);
        $this->postJson(route('meteo.suggestion'), ['suggestion' => 'Film doudou ce soir'])->assertOk();

        $sb = $this->getJson(route('meteo.state'))->assertOk()->json();
        $this->assertSame('Film doudou ce soir', $sb['maSuggestion']);
        $this->assertSame('Pique-nique au parc ce week-end', $sb['saSuggestion']);

        // Le lendemain : les deux tristes → alerte tempête
        $this->travel(1)->days();
        $this->actingAs($this->alice);
        $this->postJson(route('meteo.checkin'), ['humeur' => 'triste'])->assertOk();
        $this->actingAs($this->bob);
        $this->postJson(route('meteo.checkin'), ['humeur' => 'triste'])->assertOk();

        $this->actingAs($this->alice);
        $s3 = $this->getJson(route('meteo.state'))->assertOk()->json();
        $this->assertTrue($s3['lesDeuxMauvais']);
        $this->assertSame('🌩️', $s3['synthese']['emoji']);
        $this->assertNotNull($s3['suggestionReconfort']);
        $this->assertCount(2, $s3['historique']);
        // Jour le plus ancien : alice [heureux, calme], bob [stress].
        $this->assertSame('heureux', $s3['historique'][0]['moi'][0]['humeur']);
        $this->assertSame('calme', $s3['historique'][0]['moi'][1]['humeur']);
        $this->assertSame('stress', $s3['historique'][0]['lui'][0]['humeur']);
    }

    public function test_dashboard_affiche_la_meteo_du_couple(): void
    {
        $this->actingAs($this->alice);

        // Rien de partagé : placeholders + invitation.
        $dash = $this->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('Météo du couple', $dash->getContent());
        $this->assertStringContainsString('Partagez votre météo du jour', $dash->getContent());
        $this->assertStringContainsString('❓', $dash->getContent());

        // Ma météo affichée, celle du partenaire encore en attente.
        $this->postJson(route('meteo.checkin'), ['humeur' => 'heureux'])->assertOk();
        $dash2 = $this->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('😊', $dash2->getContent());
        $this->assertStringContainsString('Pas encore partagée', $dash2->getContent());

        // Le partenaire répond → conseil (synthèse) visible en bas de la carte.
        $this->actingAs($this->bob);
        $this->postJson(route('meteo.checkin'), ['humeur' => 'stress'])->assertOk();

        $this->actingAs($this->alice);
        $dash3 = $this->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('😊', $dash3->getContent());
        $this->assertStringContainsString('😰', $dash3->getContent());
        $this->assertStringContainsString('Quelques nuages, mais rien d&#039;insurmontable', $dash3->getContent());
    }

    public function test_dashboard_affiche_les_anniversaires_des_deux_partenaire(): void
    {
        $this->travelTo('2026-09-01 10:00:00');

        // Sans date de naissance : les lignes restent, avec un rappel pour le profil.
        $dash = $this->actingAs($this->alice)->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('Anniversaire de Alice', $dash->getContent());
        $this->assertStringContainsString('Anniversaire de Bob', $dash->getContent());
        $this->assertStringContainsString('Date de naissance à renseigner sur le profil', $dash->getContent());
        $this->assertStringNotContainsString('Score cumulé', $dash->getContent());
        $this->assertStringNotContainsString('Jours de complicité', $dash->getContent());

        $this->alice->forceFill(['date_naissance' => '1992-10-20'])->save();
        $this->bob->forceFill(['date_naissance' => '1990-01-15'])->save();

        // Recharge alice pour lever le cache de la relation coupleModel (partenaire) du 1er rendu.
        $this->alice = User::find($this->alice->id);
        $this->actingAs($this->alice);

        $view = $this->get(route('dashboard'))->assertOk();

        $this->assertStringContainsString('Anniversaire de Alice', $view->getContent());
        $this->assertStringContainsString('mardi 20 octobre 2026', $view->getContent());
        $this->assertStringContainsString('j-49 jours', $view->getContent());

        $this->assertStringContainsString('Anniversaire de Bob', $view->getContent());
        $this->assertStringContainsString('vendredi 15 janvier 2027', $view->getContent());
        $this->assertStringContainsString('j-136 jours', $view->getContent());
    }

    public function test_mots_croises_flow(): void
    {
        // Alice crée ses mots (seule, en secret).
        $this->actingAs($this->alice);
        $this->get(route('mots-croises.mots'))->assertOk();
        foreach ([
            'VOYAGE' => 'Pour deux, loin d\'ici',
            'CALIN' => 'Le geste le plus tendre',
            'RIRES' => 'On en partage tous les soirs',
            'COEUR' => 'Il bat plus fort pour toi',
            'DANSE' => 'On n\'arrête pas les pieds',
        ] as $mot => $indice) {
            $this->post(route('mots-croises.mots.creer'), ['mot' => $mot, 'indice' => $indice])->assertRedirect();
        }

        // Bob ne voit pas les mots d'Alice (liste privée).
        $bobView = $this->actingAs($this->bob)->get(route('mots-croises.mots'))->assertOk();
        $this->assertStringNotContainsString('VOYAGE', $bobView->getContent());

        // Alice génère LA grille de Bob uniquement avec ses mots.
        $this->actingAs($this->alice);
        $generation = $this->postJson(route('mots-croises.generer'))->assertOk()->json();
        $indices = array_map(fn ($m) => $m['indice'], $generation['etat']['mots']);
        $this->assertNotCount(0, $indices);
        foreach ($indices as $i) {
            $this->assertContains($i, [
                'Pour deux, loin d\'ici', 'Le geste le plus tendre', 'On en partage tous les soirs', 'Il bat plus fort pour toi', 'On n\'arrête pas les pieds',
            ]);
        }

        $grille = GrilleMotsCroises::where('couple_id', $this->couple->id)
            ->where('createur_id', $this->alice->id)
            ->first();
        $this->assertNotNull($grille);

        // Bob ne peut pas générer sa grille pour Alice : il n'a pas encore de mots.
        $this->actingAs($this->bob)->postJson(route('mots-croises.generer'))->assertStatus(422);

        // Bob voit la grille d'Alice pour lui et peut la résoudre.
        $this->actingAs($this->bob);
        $etat = $this->getJson(route('mots-croises.state'))->assertOk()->json();
        $grillePourMoi = $etat['aGrillePourMoi'];
        $this->assertNotNull($grillePourMoi);
        $this->assertArrayHasKey('cases', $grillePourMoi);
        $this->assertArrayHasKey('noires', $grillePourMoi);
        $this->assertArrayHasKey('numeros', $grillePourMoi);
        $this->assertGreaterThan(0, $grillePourMoi['progress']['total']);

        // Couverture cases noires + blanches de toute la grille.
        $this->assertCount($grillePourMoi['lignes'] * $grillePourMoi['colonnes'], array_unique(array_merge(
            $grillePourMoi['noires'],
            array_keys($grillePourMoi['cases'])
        )));
        // Chaque indice porte le numéro de sa case de départ dans la grille.
        foreach ($grillePourMoi['mots'] as $mot) {
            [$r, $c] = $mot['position'];
            $this->assertArrayHasKey("{$r},{$c}", $grillePourMoi['numeros']);
            $this->assertSame($mot['numero'], $grillePourMoi['numeros']["{$r},{$c}"]);
        }

        $cases = $grille->grille['cases'];
        $grilleMots = $grille->grille['mots'];
        $blanches = array_keys(array_filter($cases, fn ($v) => $v !== ''));
        $this->assertGreaterThanOrEqual(3, count($grilleMots));

        // Cases couvertes par un mot, dans l'ordre du mot (début → fin).
        $cellulesMot = function (array $m): array {
            [$r, $c] = $m['position'];
            $out = [];
            for ($k = 0; $k < mb_strlen($m['mot']); $k++) {
                $out[] = $m['orientation'] === 'h' ? "{$r},".($c + $k) : ($r + $k).",{$c}";
            }

            return $out;
        };

        // Saisie libre : une lettre (même fausse) reste en brouillon, sans point ni verrou.
        $mot0 = $grilleMots[0];
        $clesMot0 = $cellulesMot($mot0);
        $cle0 = $clesMot0[0];
        [$r0, $c0] = array_map('intval', explode(',', $cle0));
        $fausse = $cases[$cle0] === 'Z' ? 'A' : 'Z';
        $res = $this->postJson(route('mots-croises.verifier'), ['r' => $r0, 'c' => $c0, 'lettre' => $fausse])
            ->assertOk()
            ->json();
        $this->assertSame(0, $res['points_gagnes']);
        $this->assertSame($fausse, $res['etat']['brouillon'][$cle0]);
        $this->assertSame('', $res['etat']['cases'][$cle0]);

        // Alice observe en temps réel la lettre même fausse que Bob est en train d'écrire.
        $etatObserve = $this->actingAs($this->alice)->getJson(route('mots-croises.state'))->assertOk()->json();
        $this->assertNotNull($etatObserve['maGrillePourX']);
        $this->assertSame($fausse, $etatObserve['maGrillePourX']['brouillon'][$cle0]);
        $this->assertSame(0, $etatObserve['maGrillePourX']['progress']['trouvees']);

        // Bob complète le mot 0 avec les BONNES lettres → mot entièrement verrouillé.
        $this->actingAs($this->bob);
        $resMot0 = null;
        foreach ($clesMot0 as $kk) {
            [$rr, $cc] = array_map('intval', explode(',', $kk));
            $resMot0 = $this->postJson(route('mots-croises.verifier'), ['r' => $rr, 'c' => $cc, 'lettre' => $cases[$kk]])
                ->assertOk()
                ->json();
        }
        $this->assertNotNull($resMot0);
        $this->assertSame('correct', $resMot0['statuts'][0]['statut']);
        $this->assertSame(count($clesMot0), $resMot0['points_gagnes']);
        foreach ($clesMot0 as $kk) {
            $this->assertSame($cases[$kk], $resMot0['etat']['cases'][$kk]);
            $this->assertArrayNotHasKey($kk, $resMot0['etat']['brouillon']);
        }

        $this->assertDatabaseHas('points', [
            'couple_id' => $this->couple->id,
            'joueur_id' => $this->bob->id,
            'source' => 'mots_croises',
            'montant' => 1,
        ]);
        $this->assertDatabaseCount('points', count($clesMot0));

        // Un mot rempli avec des mauvaises lettres : refusé, lettres gardées en brouillon, aucun point.
        $mot1 = $grilleMots[1];
        $clesMot1 = array_values(array_filter($cellulesMot($mot1), fn ($kk) => ! in_array($kk, $clesMot0, true)));
        $this->assertNotEmpty($clesMot1);
        $mauvaise = $cases[$clesMot1[0]] === 'A' ? 'Z' : 'A';
        $resMot1 = null;
        foreach ($clesMot1 as $kk) {
            [$rr, $cc] = array_map('intval', explode(',', $kk));
            $resMot1 = $this->postJson(route('mots-croises.verifier'), ['r' => $rr, 'c' => $cc, 'lettre' => $mauvaise])
                ->assertOk()
                ->json();
        }
        $this->assertNotNull($resMot1);
        $this->assertSame(0, $resMot1['points_gagnes']);
        $incorrects = array_values(array_filter($resMot1['statuts'], fn ($s) => $s['statut'] === 'incorrect'));
        $this->assertNotCount(0, $incorrects);
        foreach ($clesMot1 as $kk) {
            $this->assertSame($mauvaise, $resMot1['etat']['brouillon'][$kk]);
        }
        $this->assertDatabaseCount('points', count($clesMot0));

        // Alice ne peut PAS remplir la grille qu'elle a créée pour Bob.
        $this->actingAs($this->alice)
            ->postJson(route('mots-croises.verifier'), ['r' => $r0, 'c' => $c0, 'lettre' => $cases[$cle0]])
            ->assertStatus(422);

        // Bob corrige le mot 1 puis complète toute la grille → terminee.
        $this->actingAs($this->bob);
        foreach ($blanches as $b) {
            if (($grille->fresh()->reponsesPour($this->bob->id)[$b] ?? '') !== '') {
                continue;
            }
            [$rr, $cc] = array_map('intval', explode(',', $b));
            $this->postJson(route('mots-croises.verifier'), ['r' => $rr, 'c' => $cc, 'lettre' => $cases[$b]])->assertOk();
        }
        $this->assertEquals('terminee', $grille->fresh()->statut);
        $this->assertEmpty($grille->fresh()->brouillonsPour($this->bob->id));
        $this->assertDatabaseCount('points', count($blanches));

        $etatFin = $this->getJson(route('mots-croises.state'))->assertOk()->json();
        $this->assertTrue($etatFin['aGrillePourMoi']['complete']);
    }

    public function test_mots_croises_creer_gerer_mots_personnels(): void
    {
        $this->actingAs($this->alice);
        $this->get(route('mots-croises.mots'))->assertOk();

        // Création d'un mot personnalisé, normalisé.
        $this->post(route('mots-croises.mots.creer'), ['mot' => 'Bagage', 'indice' => 'On le prépare à deux'])
            ->assertRedirect();
        $this->assertDatabaseHas('mots_croises_contenu', [
            'mot' => 'BAGAGE',
            'indice' => 'On le prépare à deux',
            'created_by' => $this->alice->id,
        ]);

        // Doublon refusé, mot trop court refusé.
        $this->post(route('mots-croises.mots.creer'), ['mot' => 'bagage', 'indice' => 'Encore'])
            ->assertSessionHasErrors('mot');
        $this->post(route('mots-croises.mots.creer'), ['mot' => 'XY', 'indice' => 'Trop court'])
            ->assertSessionHasErrors('mot');

        // Bob peut aussi ajouter le sien… mais ne voit pas celui d'Alice.
        $this->actingAs($this->bob)
            ->post(route('mots-croises.mots.creer'), ['mot' => 'OPERA', 'indice' => 'Chanté à deux'])
            ->assertRedirect();
        $bobView = $this->actingAs($this->bob)->get(route('mots-croises.mots'))->assertOk();
        $this->assertStringNotContainsString('BAGAGE', $bobView->getContent());
        $this->assertStringContainsString('OPERA', $bobView->getContent());

        // Bob ne peut pas supprimer le mot d'Alice.
        $idAlice = MotCroiseContenu::where('mot', 'BAGAGE')->first()->id;
        $this->actingAs($this->bob)
            ->delete(route('mots-croises.mots.detruire', $idAlice))
            ->assertRedirect();
        $this->assertDatabaseHas('mots_croises_contenu', ['id' => $idAlice]);

        // Alice peut supprimer le sien.
        $this->actingAs($this->alice)
            ->delete(route('mots-croises.mots.detruire', $idAlice))
            ->assertRedirect();
        $this->assertDatabaseMissing('mots_croises_contenu', ['id' => $idAlice]);
    }

    public function test_mots_croises_generer_grille_et_reinitialiser(): void
    {
        $this->actingAs($this->alice);
        foreach (['VOYAGE' => 'Partir à deux', 'CALIN' => 'Très tendre', 'RIRES' => 'Souvent ensemble', 'ETOILE' => 'Elle veille sur nous', 'DANSE' => 'Fou rire assuré'] as $mot => $indice) {
            $this->post(route('mots-croises.mots.creer'), ['mot' => $mot, 'indice' => $indice])->assertRedirect();
        }

        // Sans mots (Bob), génération impossible.
        $this->actingAs($this->bob)->postJson(route('mots-croises.generer'))->assertStatus(422);

        // Génération pour Bob, puis Bob saisit une lettre (brouillon, pas encore verrouillée).
        $this->actingAs($this->alice)->postJson(route('mots-croises.generer'))->assertOk();
        $grille = GrilleMotsCroises::pourCreateur($this->couple, $this->alice->id);
        $this->assertNotNull($grille);
        $cases = $grille->grille['cases'];
        [$r0, $c0] = array_map('intval', explode(',', array_key_first(array_filter($cases, fn ($v) => $v !== ''))));

        $this->actingAs($this->bob)
            ->postJson(route('mots-croises.verifier'), ['r' => $r0, 'c' => $c0, 'lettre' => 'A'])
            ->assertOk();
        $this->assertNotEmpty($grille->fresh()->proposition_user2);
        $this->assertEmpty($grille->fresh()->reponses_user2);

        // Alice régénère : une seule grille par créateur, progression et brouillon remis à zéro.
        $this->actingAs($this->alice);
        $res = $this->postJson(route('mots-croises.generer'))->assertOk()->json();
        $this->assertSame(0, $res['etat']['progress']['trouvees']);
        $this->assertSame('en_cours', $grille->fresh()->statut);
        $this->assertEmpty($grille->fresh()->reponses_user2);
        $this->assertEmpty($grille->fresh()->proposition_user2);
        $this->assertGreaterThanOrEqual(3, count($grille->fresh()->grille['mots']));
        $this->assertSame(1, GrilleMotsCroises::where('couple_id', $this->couple->id)->where('createur_id', $this->alice->id)->count());
    }

    public function test_game_pages_render_their_javascript(): void
    {
        $this->actingAs($this->alice);

        $pages = [
            route('vo.index') => 'function setNiveau',
            route('discussion.index') => 'disc-messages',
            route('ouinon.index') => 'async function realiserMission',
            route('mission.index') => 'async function nouvelleMission',
            route('enveloppe.index') => 'startPolling(stateUrl, renderEnvs',
            route('quiz.index') => 'async function lancerQuiz',
            route('qdn2.index') => 'Mes questions',
            route('qdn2.questions') => 'Ajouter une question',
            route('question.index') => 'async function repondreQuestion',
            route('meteo.index') => 'async function enregistrerMeteo',
            route('mots-croises.index') => 'async function onMcInput',
            route('mots-croises.mots') => 'Mes mots',
            route('recompenses.index') => 'async function marquer',
        ];

        foreach ($pages as $url => $marker) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertStringContainsString($marker, $response->getContent(), "JS absent sur $url");
        }
    }
}
