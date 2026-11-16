<?php

namespace App\Http\Middleware;

use App\Pixelforge\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Anyone can POST to the webhook URL, so only signed requests get through. */
class VerifyPixelforgeSignature
{
    public function __construct(private readonly WebhookSignature $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Verified against the raw body, before anything decodes it.
        if (! $this->signature->isValid($request->getContent(), $request->header('Pixelforge-Signature'), time())) {
            return response('Invalid Pixelforge signature.', Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
