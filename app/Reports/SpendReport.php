<?php

namespace App\Reports;

use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What users were charged, what Pixelforge charged us and the profit, per UTC
 * day and per user. Days follow Pixelforge's rule (the day a generation
 * completed), so our provider costs can be reconciled with GET /v1/usage.
 */
final class SpendReport
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /** @return array<string, mixed> */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->rows($from, $to);
        $pixelforge = $this->pixelforgeUsage($from, $to);

        $days = collect(array_keys($pixelforge ?? []))
            ->merge($rows->pluck('day'))
            ->unique()
            ->sortDesc()
            ->map(fn (string $day) => $this->day($day, $rows->where('day', $day), $pixelforge))
            ->filter(fn (array $day) => $day['generations'] > 0 || ($day['pixelforge']['generations'] ?? 0) > 0)
            ->values();

        return [
            'days' => $days,
            'rows' => $rows->map(fn (array $row) => [
                ...$row,
                ...$this->money($row['charged'], $row['provider_cost']),
            ])->values(),
            'totals' => [
                'generations' => $rows->sum('generations'),
                ...$this->money($rows->sum('charged'), $rows->sum('provider_cost')),
            ],
            'reconciled' => $pixelforge !== null,
        ];
    }

    /** @return Collection<int, array{day: string, user: string, generations: int, charged: int, provider_cost: int}> */
    private function rows(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->where('completed_at', '>=', $from->startOfDay())
            ->where('completed_at', '<', $to->addDay()->startOfDay())
            ->selectRaw('DATE(completed_at) AS day, user_id, COUNT(*) AS generations, SUM(charged_cost) AS charged, SUM(provider_cost) AS provider_cost')
            ->groupByRaw('DATE(completed_at), user_id')
            ->orderByDesc('day')
            ->with('user:id,name,email')
            ->get()
            ->map(fn (Generation $row) => [
                'day' => (string) $row->getAttribute('day'),
                'user' => $row->user === null ? 'Deleted user' : "{$row->user->name} <{$row->user->email}>",
                'generations' => (int) $row->getAttribute('generations'),
                'charged' => (int) $row->getAttribute('charged'),
                'provider_cost' => (int) $row->provider_cost,
            ]);
    }

    /** @return array<string, array{generations: int, cost: int}>|null Null when Pixelforge can't be reached */
    private function pixelforgeUsage(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        try {
            return $this->client->usage($from, $to);
        } catch (PixelforgeException) {
            return null;
        }
    }

    /**
     * @param  Collection<int, array{day: string, user: string, generations: int, charged: int, provider_cost: int}>  $rows
     * @param  array<string, array{generations: int, cost: int}>|null  $pixelforge
     * @return array<string, mixed>
     */
    private function day(string $day, Collection $rows, ?array $pixelforge): array
    {
        $providerCost = $rows->sum('provider_cost');
        $theirs = $pixelforge[$day] ?? ($pixelforge === null ? null : ['generations' => 0, 'cost' => 0]);

        return [
            'day' => $day,
            'generations' => $rows->sum('generations'),
            ...$this->money($rows->sum('charged'), $providerCost),
            'pixelforge' => $theirs === null ? null : [
                'generations' => $theirs['generations'],
                'cost' => Money::format($theirs['cost']),
                'matches' => $theirs['cost'] === $providerCost && $theirs['generations'] === $rows->sum('generations'),
            ],
        ];
    }

    /** @return array{charged: string, provider_cost: string, profit: string} */
    private function money(int $charged, int $providerCost): array
    {
        return [
            'charged' => Money::format($charged),
            'provider_cost' => Money::format($providerCost),
            'profit' => Money::format($charged - $providerCost),
        ];
    }
}
