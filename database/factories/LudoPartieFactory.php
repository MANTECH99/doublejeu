<?php

namespace Database\Factories;

use App\Models\Couple;
use App\Models\LudoPartie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LudoPartie>
 */
class LudoPartieFactory extends Factory
{
    protected $model = LudoPartie::class;

    public function definition(): array
    {
        $joueur1 = User::factory()->create();
        $joueur2 = User::factory()->create();

        $couple = Couple::create([
            'code_unique' => Couple::generateCode(),
            'user1_id' => $joueur1->id,
            'user2_id' => $joueur2->id,
            'streak' => 0,
            'score_total' => 0,
        ]);

        $joueur1->forceFill(['couple_id' => $couple->id])->save();
        $joueur2->forceFill(['couple_id' => $couple->id])->save();

        return [
            'couple_id' => $couple->id,
            'joueur1_id' => $joueur1->id,
            'joueur2_id' => $joueur2->id,
            'statut' => LudoPartie::STATUT_EN_COURS,
            'tour_id' => $joueur1->id,
            'six_compte' => 0,
        ];
    }
}
