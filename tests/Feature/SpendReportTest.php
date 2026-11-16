<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->admin->forceFill(['is_admin' => true])->save();
});

function succeededGeneration(User $user, string $completedAt, int $providerCost, int $charged): Generation
{
    return Generation::factory()->for($user)->queued()->create([
        'status' => GenerationStatus::Succeeded,
        'provider_cost' => $providerCost,
        'charged_cost' => $charged,
        // Created the day before: billing follows the completion day.
        'created_at' => CarbonImmutable::parse($completedAt)->subDay(),
        'completed_at' => $completedAt,
    ]);
}

/** @param array<string, array{0: int, 1: string}> $days date => [generations, cost_usd] */
function fakeUsage(array $days): void
{
    $data = collect($days)->map(fn (array $day, string $date) => [
        'date' => $date, 'generations' => $day[0], 'input_text_tokens' => 0, 'input_image_tokens' => 0,
        'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => $day[1],
    ])->values()->all();

    Http::fake(['*/usage*' => Http::response(['object' => 'usage', 'data' => $data, 'total' => []])]);
}

it('is only for operators', function () {
    $this->actingAs(User::factory()->create())->get('/admin/report')->assertForbidden();
});

it('reports spend, provider cost and profit per day and user, reconciled with Pixelforge', function () {
    succeededGeneration($this->admin, '2026-11-04 23:59:00', 305_510_000, 381_887_500);
    succeededGeneration($this->admin, '2026-11-04 10:00:00', 100_000_000, 125_000_000);
    succeededGeneration(User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']), '2026-11-05 00:00:01', 169_080_000, 211_350_000);
    Generation::factory()->for($this->admin)->create(['status' => GenerationStatus::Failed, 'provider_cost' => 0, 'charged_cost' => 0, 'completed_at' => '2026-11-04 12:00:00']);

    fakeUsage([
        '2026-11-03' => [0, '0.000000000'],
        '2026-11-04' => [2, '0.405510000'],
        '2026-11-05' => [1, '0.169080001'], // one nanodollar off: must be flagged
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/report?from=2026-11-03&to=2026-11-05')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Report')
            ->where('reconciled', true)
            ->where('totals', ['generations' => 3, 'charged' => '$0.7182375', 'provider_cost' => '$0.57459', 'profit' => '$0.1436475'])
            ->has('days', 2)
            ->where('days.0.day', '2026-11-05')
            ->where('days.0.pixelforge.matches', false)
            ->where('days.1', [
                'day' => '2026-11-04',
                'generations' => 2,
                'charged' => '$0.5068875',
                'provider_cost' => '$0.40551',
                'profit' => '$0.1013775',
                'pixelforge' => ['generations' => 2, 'cost' => '$0.40551', 'matches' => true],
            ])
            ->where('rows.0.user', 'Bob <bob@example.com>')
            ->where('rows.1.user', 'Ada <ada@example.com>'));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'start_date=2026-11-03&end_date=2026-11-05'));
});

it('still reports when Pixelforge usage is unavailable', function () {
    succeededGeneration($this->admin, '2026-11-04 10:00:00', 100_000_000, 125_000_000);
    Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

    $this->actingAs($this->admin)
        ->get('/admin/report?from=2026-11-01&to=2026-11-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('reconciled', false)
            ->where('days.0.pixelforge', null)
            ->where('totals.profit', '$0.025'));
});

it('never asks Pixelforge for more than 92 days', function () {
    fakeUsage([]);

    $this->actingAs($this->admin)
        ->get('/admin/report?from=2026-01-01&to=2026-11-05')
        ->assertInertia(fn (Assert $page) => $page->where('from', '2026-08-06'));
});
