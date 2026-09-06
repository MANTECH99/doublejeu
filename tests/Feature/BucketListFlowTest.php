<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BucketListFlowTest extends TestCase
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
        // Alice ajoute une idée de voyage.
        $this->actingAs($this->alice)
            ->postJson(route('bucket-list.creer'), [
                'titre' => 'Week-end à Rome',
                'categorie' => 'voyages',
                'lieu' => 'Italie',
            ])
            ->assertOk()
            ->assertJsonPath('item.titre', 'Week-end à Rome')
            ->assertJsonPath('item.categorie', 'voyages')
            ->assertJsonPath('item.lieu', 'Italie')
            ->assertJsonPath('item.realise', false)
            ->assertJsonPath('item.cree_par', 'Alice');

        $this->assertDatabaseHas('bucket_list_items', [
            'couple_id' => $this->couple->id,
            'titre' => 'Week-end à Rome',
            'categorie' => 'voyages',
            'lieu' => 'Italie',
            'realise' => false,
        ]);

        // Bob voit l'idée ajoutée par Alice en temps réel (polling de l'état).
        $etat = $this->actingAs($this->bob)->getJson(route('bucket-list.state'))->assertOk()->json();
        $this->assertCount(1, $etat['items']);
        $this->assertSame('Week-end à Rome', $etat['items'][0]['titre']);

        // Validation de la catégorie.
        $this->actingAs($this->bob)
            ->postJson(route('bucket-list.creer'), ['titre' => 'X', 'categorie' => 'inconnue'])
            ->assertStatus(422);
    }

    public function test_activity_can_be_marked_done_reopened_and_deleted(): void
    {
        $item = $this->couple->bucketListItems()->create([
            'titre' => 'Faire du paddle',
            'categorie' => 'activites',
            'lieu' => null,
            'realise' => false,
            'cree_par' => $this->alice->id,
        ]);

        // Marquer réalisée : horodatage enregistré, l'autre voit le changement.
        $this->actingAs($this->alice)
            ->postJson(route('bucket-list.realiser', $item))
            ->assertOk()
            ->assertJsonPath('item.realise', true);

        $this->travelTo('2026-09-01 10:00:00');
        $this->actingAs($this->alice)->postJson(route('bucket-list.realiser', $item))->assertStatus(422);

        $etat = $this->actingAs($this->bob)->getJson(route('bucket-list.state'))->assertOk()->json();
        $this->assertTrue($etat['items'][0]['realise']);
        $this->assertNotNull($etat['items'][0]['realise_at']);

        // Réouvrir.
        $this->actingAs($this->bob)
            ->postJson(route('bucket-list.reouvrir', $item))
            ->assertOk()
            ->assertJsonPath('item.realise', false)
            ->assertJsonPath('item.realise_at', null);

        // Supprimer.
        $this->actingAs($this->bob)
            ->deleteJson(route('bucket-list.detruire', $item))
            ->assertOk();
        $this->assertDatabaseMissing('bucket_list_items', ['id' => $item->id]);
    }

    public function test_photo_is_attached_and_exposed_to_partner(): void
    {
        Storage::fake('public');

        $item = $this->couple->bucketListItems()->create([
            'titre' => 'Brunch au sommet',
            'categorie' => 'gastronomie',
            'lieu' => 'Alpes',
            'realise' => true,
            'realise_at' => now(),
            'cree_par' => $this->alice->id,
        ]);

        $upload = $this->actingAs($this->alice)
            ->post(route('bucket-list.photo', $item), ['photo' => UploadedFile::fake()->image('brunch.png')])
            ->assertOk()
            ->json();

        $this->assertNotNull($upload['url']);
        $this->assertStringStartsWith('/storage/bucket-list-photos/', $upload['url']);

        $this->assertCount(1, $item->fresh()->photos);
        Storage::disk('public')->assertExists($item->fresh()->photos[0]);

        // Bob voit la photo dans l'album.
        $etat = $this->actingAs($this->bob)->getJson(route('bucket-list.state'))->assertOk()->json();
        $this->assertSame($upload['url'], $etat['items'][0]['photos'][0]);
    }

    public function test_cannot_act_on_another_couples_item(): void
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

        $item = $this->couple->bucketListItems()->create([
            'titre' => 'Secret',
            'categorie' => 'projets',
            'realise' => false,
            'cree_par' => $this->alice->id,
        ]);

        // L'intrus ne peut ni réaliser, ni supprimer un item d'un autre couple.
        $this->actingAs($intrus)
            ->postJson(route('bucket-list.realiser', $item))
            ->assertForbidden();
        $this->actingAs($intrus)
            ->deleteJson(route('bucket-list.detruire', $item))
            ->assertForbidden();
    }

    public function test_bucket_list_page_renders_its_javascript(): void
    {
        $this->actingAs($this->alice);
        $view = $this->get(route('bucket-list.index'))->assertOk();
        $this->assertStringContainsString('async function blBasculer', $view->getContent());
        $this->assertStringContainsString('startPolling(blStateUrl', $view->getContent());
    }
}
