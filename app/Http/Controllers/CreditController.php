<?php

namespace App\Http\Controllers;

use App\Billing\Money;
use App\Models\CreditPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Simulated credit purchases: no payment provider, credits are added at once. */
class CreditController extends Controller
{
    /** Whole US dollars. */
    private const array PACKAGES = [5, 10, 25];

    public function index(Request $request): Response
    {
        return Inertia::render('credits/Index', [
            'packages' => self::PACKAGES,
            'purchases' => $request->user()->creditPurchases()->latest()->limit(20)->get()
                ->map(fn (CreditPurchase $purchase) => [
                    'id' => $purchase->id,
                    'amount' => Money::format($purchase->amount),
                    'created_at' => $purchase->created_at->toIso8601ZuluString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dollars = (int) $request->validate([
            'amount' => ['required', 'integer', Rule::in(self::PACKAGES)],
        ])['amount'];

        $request->user()->creditPurchases()->create([
            'amount' => Money::fromDecimal((string) $dollars),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \${$dollars}.00 in credits."]);

        return back();
    }
}
