<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionFlowTest extends TestCase
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

    public function test_partners_can_send_and_read_private_messages(): void
    {
        // Alice écrit un message.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Coucou, on dîne ce soir ?'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'couple_id' => $this->couple->id,
            'sender_id' => $this->alice->id,
            'body' => 'Coucou, on dîne ce soir ?',
        ]);

        // Bob reçoit le message, non lu, puis il le lit.
        $bobFetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $bobFetch['messages']);
        $this->assertSame('Coucou, on dîne ce soir ?', $bobFetch['messages'][0]['body']);
        $this->assertSame('Alice', $bobFetch['messages'][0]['sender_name']);
        $this->assertTrue($bobFetch['messages'][0]['lu']);

        // Le statut du partenaire est renvoyé : Alice est en ligne (activité touchée).
        $this->assertTrue($bobFetch['partenaire']['enLigne']);
        $this->assertTrue($bobFetch['partenaire']['present']);

        // Le lecteur reçoit la confirmation "✓✓" côté Alice.
        $aliceFetch = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertTrue($aliceFetch['messages'][0]['lu']);
    }

    public function test_fetch_incrementally_returns_only_new_messages(): void
    {
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Un']);
        Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Deux']);
        $third = Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'Trois']);

        $this->actingAs($this->bob);

        // Rien après le dernier id.
        $empty = $this->getJson(route('discussion.fetch').'?after='.$third->id)->assertOk()->json();
        $this->assertCount(0, $empty['messages']);

        // Seuls les premiers visibles via after.
        $partial = $this->getJson(route('discussion.fetch').'?after=2')->assertOk()->json();
        $this->assertCount(1, $partial['messages']);
        $this->assertSame('Trois', $partial['messages'][0]['body']);
    }

    public function test_validation_and_authorization(): void
    {
        // Contenu vide refusé.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => '   '])
            ->assertStatus(422);

        // Un utilisateur non lié ne peut pas accéder à la discussion.
        $solo = User::factory()->create();
        $this->actingAs($solo)
            ->get(route('discussion.index'))
            ->assertRedirect(route('couple.setup'));

        // Page rendue pour un couple lié.
        $this->actingAs($this->alice)
            ->get(route('discussion.index'))
            ->assertOk()
            ->assertSee('disc-messages', false);
    }
}
