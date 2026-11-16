<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CreditPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A (simulated) purchase of credits. Credits are US dollars.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount Nanodollars
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['amount'])]
class CreditPurchase extends Model
{
    /** @use HasFactory<CreditPurchaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
