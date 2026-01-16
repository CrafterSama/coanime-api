<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    /**
     * Get today's broadcast schedule from Jikan API
     *
     * @param string|null $day Day of the week (optional, defaults to current day)
     * @return array
     */
    public function getTodaySchedule(?string $day = null): array
    {
        $day = $day ?? date('l');
        $broadcastUrl = 'https://api.jikan.moe/v4/schedules/' . $day;
        
        try {
            $response = Http::timeout(10)->get($broadcastUrl);
            
            if ($response->successful()) {
                $broadcastData = $response->json();
                $broadcast = $broadcastData['data'] ?? [];
                
                // Add custom URL for each broadcast item
                foreach ($broadcast as $key => $value) {
                    $broadcast[$key]['url'] = 'https://coanime.net/ecma/titulos/'
                        . Str::slug($value['type']) . '/'
                        . Str::slug($value['title']);
                }
                
                return $broadcast;
            }
            
            Log::warning('Jikan API returned unsuccessful response', [
                'url' => $broadcastUrl,
                'status' => $response->status()
            ]);
            
            return [];
        } catch (Exception $e) {
            Log::error('Error fetching broadcast schedule from Jikan API', [
                'url' => $broadcastUrl,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get broadcast schedule for a specific day (lowercase)
     *
     * @param string|null $day Day of the week in lowercase (optional)
     * @return array
     */
    public function getScheduleByDay(?string $day = null): array
    {
        $day = $day ?? strtolower(date('l'));
        $broadcastUrl = 'https://api.jikan.moe/v4/schedules/' . $day;
        
        try {
            $response = Http::timeout(10)->get($broadcastUrl);
            
            if ($response->successful()) {
                $broadcastData = $response->json();
                return $broadcastData['data'] ?? [];
            }
            
            return [];
        } catch (Exception $e) {
            Log::error('Error fetching broadcast schedule from Jikan API', [
                'url' => $broadcastUrl,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
}
