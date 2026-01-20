<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class AnimeScraperService
{
    protected string $baseUrl = 'https://jkanime.net';

    public function scrapeDirectory(): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
            ->get("{$this->baseUrl}/directorio/");

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo acceder al directorio');
        }

        $crawler = new Crawler($response->body());
        $results = [];

        $crawler->filter('div.card.mb-3.custom_item2 a')->each(
            function (Crawler $node) use (&$results) {
                $results[] = [
                    'title' => trim($node->text()),
                    'url'   => $node->attr('href'),
                ];
            }
        );

        return $results;
    }

    protected function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/120.0 Safari/537.36';
    }
}