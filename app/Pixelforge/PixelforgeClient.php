<?php

namespace App\Pixelforge;

use App\Models\Generation;
use App\Pixelforge\Data\GenerationData;
use App\Pixelforge\Data\Pricing;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Our only door to the Pixelforge API. Every failure surfaces as a PixelforgeException. */
class PixelforgeClient
{
    public function __construct(
        #[Config('services.pixelforge.base_url')] private readonly string $baseUrl,
        #[Config('services.pixelforge.api_key')] private readonly ?string $apiKey,
    ) {}

    /**
     * Our generation id doubles as the Idempotency-Key, so retrying after a
     * timeout can never create (or bill) a second generation. It is also sent
     * as metadata, so webhooks can be matched even before this call returns.
     */
    public function createGeneration(Generation $generation): GenerationData
    {
        $payload = [
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'metadata' => ['generation_id' => $generation->id],
        ];

        if ($generation->reference_images !== []) {
            $payload['reference_images'] = $generation->reference_images;
        }

        return GenerationData::fromArray(
            $this->send('post', 'generations', $payload, ['Idempotency-Key' => $generation->id]),
        );
    }

    public function getGeneration(string $pixelforgeId): GenerationData
    {
        return GenerationData::fromArray($this->send('get', "generations/{$pixelforgeId}"));
    }

    public function pricing(): Pricing
    {
        return Pricing::fromArray($this->send('get', 'pricing'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->apiKey)
                ->withHeaders($headers)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->send($method, $path, $method === 'get' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException $exception) {
            throw PixelforgeException::connectionFailed($exception);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return (array) $response->json();
    }
}
