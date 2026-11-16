<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\SyncGeneration;
use App\Http\Controllers\Controller;
use App\Pixelforge\Data\GenerationData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Pixelforge delivers events at least once and in any order. Each event id is
 * recorded in the same transaction as its effect, so a duplicate is skipped,
 * and a delivery that fails half-way leaves no trace and is safely retried.
 */
class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, SyncGeneration $sync): Response
    {
        /** @var array{id: string, type: string, data?: array{object?: array<string, mixed>}} $event */
        $event = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($event, $request, $sync): void {
            $isNew = DB::table('webhook_events')->insertOrIgnore([
                'id' => $event['id'],
                'type' => $event['type'],
                'payload' => $request->getContent(),
            ]) === 1;

            // "ping" (the dashboard's test event) only needs the 2xx.
            if ($isNew && str_starts_with($event['type'], 'generation.') && isset($event['data']['object'])) {
                $sync->handle(GenerationData::fromArray($event['data']['object']));
            }
        });

        return response()->noContent();
    }
}
