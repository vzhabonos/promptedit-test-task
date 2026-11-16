<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Billing\CostCalculator;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\Generations\EstimateGenerationRequest;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PricingCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    private const string PRICING_UNAVAILABLE = 'Pixelforge pricing is unavailable right now, so we can\'t price this generation. Please try again shortly.';

    public function index(Request $request): Response
    {
        return Inertia::render('generations/Index', [
            'generations' => GenerationResource::collection(
                $request->user()->generations()->latest()->paginate(12),
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        // "Try again" reopens the form with a previous generation's settings.
        $from = $request->query('from');
        $previous = is_string($from) ? $request->user()->generations()->find($from) : null;

        return Inertia::render('generations/Create', [
            'sizes' => array_map(fn (ImageSize $size) => ['value' => $size->value, 'label' => $size->label()], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $quality) => ['value' => $quality->value, 'label' => $quality->label()], ImageQuality::cases()),
            'limits' => [
                'prompt' => StoreGenerationRequest::MAX_PROMPT_LENGTH,
                'referenceImages' => StoreGenerationRequest::MAX_REFERENCE_IMAGES,
            ],
            'markupPercent' => CostCalculator::MARKUP_PERCENT,
            'previous' => $previous === null ? null : [
                'prompt' => $previous->prompt,
                'size' => $previous->size->value,
                'quality' => $previous->quality->value,
                'reference_images' => $previous->reference_images,
            ],
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        try {
            $createGeneration->handle(
                $request->user(),
                $request->prompt(),
                $request->size(),
                $request->quality(),
                $request->referenceImages(),
            );
        } catch (PixelforgeException) {
            throw ValidationException::withMessages(['form' => self::PRICING_UNAVAILABLE]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generation queued. It usually takes under a minute.']);

        return to_route('generations.index');
    }

    public function estimate(EstimateGenerationRequest $request, CostCalculator $calculator, PricingCache $pricing): JsonResponse
    {
        try {
            $estimate = $calculator->estimate(
                $pricing->current(),
                $request->prompt(),
                $request->size(),
                $request->quality(),
                count($request->referenceImages()),
            );
        } catch (PixelforgeException) {
            return response()->json(['message' => self::PRICING_UNAVAILABLE], 503);
        }

        return response()->json($estimate->toArray());
    }
}
