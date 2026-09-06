<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendrierFlowTest extends TestCase
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
    }

    public function test_partners_can_add_and_see_activities_in_real_time(): void
    {
        $this->travelTo('2026-09-10 09:00:00');

        // Alice ajoute une activité au créneau.
        $this->actingAs($this->alice)
            ->postJson(route('calendrier.creer'), [
                'date' => '2026-09-10',
                'titre' => 'Pilates',
                'raison' => 'detente',
                'heure_debut' => '08:30',
                'heure_fin' => '09:30',
                'couleur' => 'vert',
            ])
            ->assertOk()
            ->assertJsonPath('creneau.titre', 'Pilates')
            ->assertJsonPath('creneau.raison', 'detente')
            ->assertJsonPath('creneau.heure_debut', '08:30')
            ->assertJsonPath('creneau.heure_fin', '09:30')
            ->assertJsonPath('creneau.couleur', 'vert')
            ->assertJsonPath('creneau.user_id', $this->alice->id);

        $this->assertDatabaseHas('calendrier_creneaux', [
            'couple_id' => $this->couple->id,
            'user_id' => $this->alice->id,
            'date_jour' => '2026-09-10',
            'titre' => 'Pilates',
            'raison' => 'detente',
            'heure_debut' => '08:30',
            'heure_fin' => '09:30',
            'couleur' => 'vert',
        ]);

        // Bob voit l'activité d'Alice en temps réel (polling de l'état).
        $etat = $this->actingAs($this->bob)
            ->getJson(route('calendrier.state', ['date' => '2026-09-10']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $etat['creneaux']);
        $this->assertSame('Pilates', $etat['creneaux'][0]['titre']);
        $this->assertSame('detente', $etat['creneaux'][0]['raison']);
        $this->assertSame('Alice', $etat['creneaux'][0]['user_name']);

        // Le partenaire n'est pas ignoré : chacun ajoute dans sa propre colonne.
        // Sans raison ni couleur : la couleur par défaut est appliquée.
        $this->actingAs($this->bob)
            ->postJson(route('calendrier.creer'), [
                'date' => '2026-09-10',
                'titre' => 'Course à pied',
                'heure_debut' => '18:00',
            ])
            ->assertOk()
            ->assertJsonPath('creneau.user_id', $this->bob->id)
            ->assertJsonPath('creneau.heure_fin', null)
            ->assertJsonPath('creneau.raison', null)
            ->assertJsonPath('creneau.couleur', 'rouge');

        $etat = $this->actingAs($this->alice)
            ->getJson(route('calendrier.state', ['date' => '2026-09-10']))
            ->assertOk()
            ->json();

        $this->assertCount(2, $etat['creneaux']);

        // Validation des champs (heure au bon format, couleur autorisée, date valide).
        $this->actingAs($this->bob)
            ->postJson(route('calendrier.creer'), [
                'date' => '2026-09-10',
                'titre' => 'X',
                'heure_debut' => '09:99',
            ])
            ->assertStatus(422);
        $this->actingAs($this->bob)
            ->postJson(route('calendrier.creer'), [
                'date' => '2026-09-10',
                'titre' => 'X',
                'heure_debut' => '10:00',
                'couleur' => 'aubergine',
            ])
            ->assertStatus(422);
        $this->actingAs($this->bob)
            ->postJson(route('calendrier.creer'), [
                'date' => '10/09/2026',
                'titre' => 'X',
                'heure_debut' => '10:00',
            ])
            ->assertStatus(422);
    }

    public function test_modify_accepts_unmodified_time_values_and_serialises_hours_without_seconds(): void
    {
        $creneau = $this->couple->calendrierCreneaux()->create([
            'user_id' => $this->alice->id,
            'date_jour' => '2026-09-10',
            'titre' => 'Sport',
            'heure_debut' => '18:00',
            'heure_fin' => null,
            'couleur' => 'vert',
        ]);

        $this->assertDatabaseHas('calendrier_creneaux', ['id' => $creneau->id, 'titre' => 'Sport']);

        // L'état renvoie l'heure sans les secondes (préremplissage du formulaire).
        $etat = $this->actingAs($this->alice)
            ->getJson(route('calendrier.state', ['date' => '2026-09-10']))
            ->assertOk()
            ->json();

        $this->assertSame('18:00', $etat['creneaux'][0]['heure_debut']);

        // Enregistrer sans rien modifier (heure déjà formatée) doit fonctionner.
        $this->actingAs($this->alice)
            ->putJson(route('calendrier.modifier', $creneau), [
                'titre' => 'Sport',
                'raison' => null,
                'heure_debut' => '18:00',
                'heure_fin' => null,
                'couleur' => 'vert',
            ])
            ->assertOk()
            ->assertJsonPath('creneau.heure_debut', '18:00');

        // Défense en profondeur : une heure envoyée avec les secondes est acceptée puis normalisée.
        $this->actingAs($this->alice)
            ->putJson(route('calendrier.modifier', $creneau), [
                'titre' => 'Sport',
                'heure_debut' => '18:30:00',
                'heure_fin' => null,
                'couleur' => 'vert',
            ])
            ->assertOk()
            ->assertJsonPath('creneau.heure_debut', '18:30');
    }

    public function test_activity_can_be_updated_and_deleted_by_its_creator(): void
    {
        $creneau = $this->couple->calendrierCreneaux()->create([
            'user_id' => $this->alice->id,
            'date_jour' => '2026-09-10',
            'titre' => 'Dîner en amoureux',
            'heure_debut' => '20:00',
            'heure_fin' => null,
            'couleur' => 'bleu',
        ]);

        // Alice modifie son créneau.
        $this->actingAs($this->alice)
            ->putJson(route('calendrier.modifier', $creneau), [
                'titre' => 'Dîner surprise',
                'raison' => 'ensemble',
                'heure_debut' => '19:30',
                'heure_fin' => '22:00',
                'couleur' => 'rouge',
            ])
            ->assertOk()
            ->assertJsonPath('creneau.titre', 'Dîner surprise')
            ->assertJsonPath('creneau.raison', 'ensemble')
            ->assertJsonPath('creneau.heure_debut', '19:30')
            ->assertJsonPath('creneau.heure_fin', '22:00')
            ->assertJsonPath('creneau.couleur', 'rouge');

        $this->assertDatabaseHas('calendrier_creneaux', [
            'id' => $creneau->id,
            'titre' => 'Dîner surprise',
            'raison' => 'ensemble',
            'couleur' => 'rouge',
        ]);

        // Bob ne peut pas modifier le créneau d'Alice.
        $this->actingAs($this->bob)
            ->putJson(route('calendrier.modifier', $creneau), [
                'titre' => 'Piraté',
                'heure_debut' => '20:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('calendrier_creneaux', ['id' => $creneau->id, 'titre' => 'Dîner surprise']);

        // Bob ne peut pas non plus le supprimer.
        $this->actingAs($this->bob)
            ->deleteJson(route('calendrier.detruire', $creneau))
            ->assertForbidden();

        $this->assertDatabaseHas('calendrier_creneaux', ['id' => $creneau->id]);

        // Alice peut le supprimer.
        $this->actingAs($this->alice)
            ->deleteJson(route('calendrier.detruire', $creneau))
            ->assertOk();

        $this->assertDatabaseMissing('calendrier_creneaux', ['id' => $creneau->id]);
    }

    public function test_cannot_act_on_another_couples_activity(): void
    {
        $intrus = User::factory()->create(['name' => 'Céline', 'gender' => 'Femme']);
        $autreUser = User::factory()->create(['name' => 'Dan', 'gender' => 'Homme']);
        $other = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $intrus->id,
            'user2_id' => $autreUser->id,
            'streak' => 0,
            'score_total' => 0,
        ]);
        $intrus->forceFill(['couple_id' => $other->id])->save();
        $autreUser->forceFill(['couple_id' => $other->id])->save();

        $creneau = $this->couple->calendrierCreneaux()->create([
            'user_id' => $this->alice->id,
            'date_jour' => '2026-09-10',
            'titre' => 'Secret',
            'heure_debut' => '10:00',
        ]);

        // L'intrus ne peut ni modifier ni supprimer un créneau d'un autre couple.
        $this->actingAs($intrus)
            ->putJson(route('calendrier.modifier', $creneau), [
                'titre' => 'Piraté',
                'heure_debut' => '10:00',
            ])
            ->assertForbidden();
        $this->actingAs($intrus)
            ->deleteJson(route('calendrier.detruire', $creneau))
            ->assertForbidden();

        $this->assertDatabaseHas('calendrier_creneaux', ['id' => $creneau->id]);
    }

    public function test_calendrier_page_renders_its_javascript(): void
    {
        $this->actingAs($this->alice);
        $view = $this->get(route('calendrier.index'))->assertOk();
        $this->assertStringContainsString('async function calCharger', $view->getContent());
        $this->assertStringContainsString('startPolling(calStateUrl', $view->getContent());
        $this->assertStringContainsString('calFinEffective', $view->getContent());
        $this->assertStringNotContainsString('cal-detail-couleur', $view->getContent());
        $this->assertStringContainsString('id="cal-raison"', $view->getContent());
        $this->assertStringContainsString('id="cal-save"', $view->getContent());
        $this->assertStringContainsString('id="cal-save-spinner"', $view->getContent());
    }

    public function test_state_defaults_to_today_and_filters_by_date(): void
    {
        $this->travelTo('2026-09-10 15:00:00');

        $this->couple->calendrierCreneaux()->create([
            'user_id' => $this->alice->id,
            'date_jour' => '2026-09-10',
            'titre' => 'Aujourd\'hui',
            'heure_debut' => '09:00',
        ]);
        $this->couple->calendrierCreneaux()->create([
            'user_id' => $this->bob->id,
            'date_jour' => '2026-09-11',
            'titre' => 'Demain',
            'heure_debut' => '09:00',
        ]);

        $etat = $this->actingAs($this->alice)->getJson(route('calendrier.state'))->assertOk()->json();
        $this->assertSame('2026-09-10', $etat['date']);
        $this->assertCount(1, $etat['creneaux']);
        $this->assertSame('Aujourd\'hui', $etat['creneaux'][0]['titre']);
    }
}
