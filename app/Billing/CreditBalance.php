<?php

namespace App\Billing;

use App\Models\User;

/**
 * A user's spendable credits, derived from records that already exist:
 * purchases, minus what finished generations were charged, minus the most
 * that still-running generations can cost (an implicit hold, released the
 * moment a generation finishes).
 */
final class CreditBalance
{
    /** In nanodollars. */
    public function available(User $user): int
    {
        $purchased = (int) $user->creditPurchases()->sum('amount');
        $charged = (int) $user->generations()->sum('charged_cost');
        $held = (int) $user->generations()->active()->sum('estimated_cost_max');

        return $purchased - $charged - $held;
    }
}
