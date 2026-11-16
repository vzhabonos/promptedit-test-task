<?php

namespace Database\Factories;

use App\Models\CreditPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPurchase>
 */
class CreditPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 10_000_000_000, // $10
        ];
    }
}
