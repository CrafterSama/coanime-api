<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeAnimesJob;
use Illuminate\Http\JsonResponse;

class AnimeController extends Controller
{
    public function consumeAnimes(): JsonResponse
    {
        ScrapeAnimesJob::dispatch();

        return response()->json([
            'code' => 202,
            'message' => [
                'type' => 'Accepted',
                'text' => 'Scraping en proceso',
            ],
        ], 202);
    }
}