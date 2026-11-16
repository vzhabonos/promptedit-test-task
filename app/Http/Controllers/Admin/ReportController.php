<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** GET /v1/usage covers at most 92 days. */
    private const int MAX_DAYS = 92;

    public function __invoke(Request $request, SpendReport $report): Response
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($request->query('to') ?? 'today', 'UTC');
        $from = CarbonImmutable::parse($request->query('from') ?? $to->subDays(29)->toDateString(), 'UTC');
        $from = $from->max($to->subDays(self::MAX_DAYS - 1));

        return Inertia::render('admin/Report', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$report->build($from, $to),
        ]);
    }
}
