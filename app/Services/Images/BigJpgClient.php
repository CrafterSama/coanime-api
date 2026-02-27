<?php

declare(strict_types=1);

namespace App\Services\Images;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BigJpgClient
{
    public function enlarge(string $imageUrl): ?string
    {
        $apiKey = (string) config('news.bigjpg.api_key', '');
        $endpoint = (string) config('news.bigjpg.endpoint', 'https://bigjpg.com/api/task/');

        if ($apiKey === '' || $imageUrl === '') {
            return null;
        }

        $payload = array_merge(
            config('news.bigjpg.defaults', []),
            [
                'input' => $imageUrl,
            ]
        );

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
            ])->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('BigJpgClient: request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }

            if (isset($data['output']) && is_string($data['output']) && $data['output'] !== '') {
                return $data['output'];
            }

            if (isset($data['task']['output']) && is_string($data['task']['output']) && $data['task']['output'] !== '') {
                return $data['task']['output'];
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('BigJpgClient: error calling API', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

