<?php

namespace Database\Factories;

use App\Models\LudoPartie;
use App\Models\LudoToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LudoToken>
 */
class LudoTokenFactory extends Factory
{
    protected $model = LudoToken::class;

    public function definition(): array
    {
        return [
            'partie_id' => LudoPartie::factory(),
            'joueur_id' => User::factory(),
            'couleur' => LudoToken::COULEUR_ROUGE,
            'numero' => fake()->numberBetween(0, 3),
            'position' => LudoToken::POSITION_BASE,
        ];
    }
}
