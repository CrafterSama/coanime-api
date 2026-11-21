<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\NewsContentCleanup;
use Tests\TestCase;

class NewsContentCleanupTest extends TestCase
{
    public function test_strips_attribution_and_mal_rewrite(): void
    {
        $html = '<p>Hola</p><p>Fuente: MyAnimeList (Tenrai)</p><p>Fin [Written by MAL Rewrite]</p>';
        $out = NewsContentCleanup::stripNoise($html);

        $this->assertStringContainsString('Hola', $out);
        $this->assertStringContainsString('Fin', $out);
        $this->assertStringNotContainsString('MyAnimeList', $out);
        $this->assertStringNotContainsString('Tenrai', $out);
        $this->assertStringNotContainsString('MAL Rewrite', $out);
    }

    public function test_strips_spanish_rewrite_marker(): void
    {
        $out = NewsContentCleanup::stripPlain('Texto [Escrito por MAL Rewrite] más');
        $this->assertSame('Texto más', $out);
    }

    public function test_does_not_break_myanimelist_hrefs(): void
    {
        $html = '<p><a href="https://myanimelist.net/anime/1/x">x</a></p>';
        $this->assertStringContainsString('myanimelist.net/anime/1/x', NewsContentCleanup::stripNoise($html));
    }

    public function test_rewrites_youtube_iframe_no_referrer_policy(): void
    {
        $html = '<iframe src="https://www.youtube-nocookie.com/embed/abc" referrerpolicy="no-referrer" allowfullscreen></iframe>';
        $out = NewsContentCleanup::stripNoise($html);

        $this->assertStringContainsString('referrerpolicy="strict-origin-when-cross-origin"', $out);
        $this->assertStringNotContainsString('no-referrer', $out);
    }
}
