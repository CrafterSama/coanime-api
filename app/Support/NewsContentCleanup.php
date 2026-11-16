<?php

declare(strict_types=1);

namespace App\Support;

final class NewsContentCleanup
{
    public static function stripNoise(string $html): string
    {
        $html = preg_replace('/\[(?:Written by MAL Rewrite|Escrito por MAL Rewrite)\]/iu', '', $html) ?? $html;
        $html = preg_replace('/\(\s*Source:\s*MAL\s+News\s*\)/iu', '', $html) ?? $html;
        // Only attribution paragraphs — never touch href URLs that contain myanimelist.net
        $html = preg_replace(
            '/<p[^>]*>\s*(?:Fuente\s*:)?\s*(?:MyAnimeList|MAL|Tenrai)[^<]*<\/p>/iu',
            '',
            $html
        ) ?? $html;

        return trim($html);
    }

    public static function stripPlain(string $text): string
    {
        $text = preg_replace('/\[(?:Written by MAL Rewrite|Escrito por MAL Rewrite)\]/iu', '', $text) ?? $text;
        $text = preg_replace('/\(\s*Source:\s*MAL\s+News\s*\)/iu', '', $text) ?? $text;
        $text = preg_replace('/(?:Fuente\s*:\s*)?(?:MyAnimeList(?:\s*\(\s*Tenrai\s*\))?|\bTenrai\b)/iu', '', $text) ?? $text;

        return trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
    }
}
