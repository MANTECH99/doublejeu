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

    public function test_fetch_returns_all_messages_in_chronological_order_on_initial_load(): void
    {
        // Tout l'historique est renvoyé au chargement initial, du plus ancien au plus récent.
        for ($i = 1; $i <= 110; $i++) {
            Message::create(['couple_id' => $this->couple->id, 'sender_id' => $this->alice->id, 'body' => 'm-'.$i]);
        }

        $fetch = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $this->assertCount(110, $fetch['messages']);
        $this->assertSame('m-1', $fetch['messages'][0]['body'], 'Le plus ancien message est attendu en premier.');
        $this->assertSame('m-110', $fetch['messages'][109]['body'], 'Le tout dernier message doit être présent.');
    }

    public function test_partner_can_reply_to_a_message(): void
    {
        // Alice écrit un message auquel Bob répondra.
        $base = $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), ['body' => 'Rendez-vous à 20h ?'])
            ->assertOk()
            ->json();

        // Bob répond en citant ce message.
        $this->actingAs($this->bob)
            ->postJson(route('discussion.send'), [
                'body' => 'Parfait pour moi !',
                'reply_to_id' => $base['id'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'couple_id' => $this->couple->id,
            'sender_id' => $this->bob->id,
            'body' => 'Parfait pour moi !',
            'reply_to_id' => $base['id'],
        ]);

        // On ne peut pas répondre à un message d'un autre couple.
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $other = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $u1->id,
            'user2_id' => $u2->id,
            'streak' => 0,
            'score_total' => 0,
        ]);
        $foreign = Message::create(['couple_id' => $other->id, 'sender_id' => $u1->id, 'body' => 'très loin']);

        $this->actingAs($this->alice)
            ->postJson(route('discussion.send'), [
                'body' => 'On ne sait pas',
                'reply_to_id' => $foreign->id,
            ])
            ->assertStatus(422);

        // Le fetch renvoie le message cité avec son contenu.
        $fetch = $this->actingAs($this->alice)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();

        $reply = collect($fetch['messages'])->firstWhere('body', 'Parfait pour moi !');
        $this->assertNotNull($reply['reply_to']);
        $this->assertSame('Rendez-vous à 20h ?', $reply['reply_to']['body']);
        $this->assertSame('Alice', $reply['reply_to']['sender_name']);
    }

    public function test_partner_sees_typing_indicator_while_other_is_typing(): void
    {
        // Personne ne tape au départ.
        $idle = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertFalse($idle['partenaire']['typing']);

        // Alice tape juste maintenant.
        $this->actingAs($this->alice)
            ->postJson(route('discussion.typing'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $whileTyping = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertTrue($whileTyping['partenaire']['typing']);

        // L'indicateur expire après 3 secondes d'inactivité.
        $this->travel(4)->seconds();
        $expired = $this->actingAs($this->bob)
            ->getJson(route('discussion.fetch'))
            ->assertOk()
            ->json();
        $this->assertFalse($expired['partenaire']['typing']);
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
