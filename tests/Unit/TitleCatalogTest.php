<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TitleCatalog;
use PHPUnit\Framework\TestCase;

class TitleCatalogTest extends TestCase
{
    public function test_day_filter_maps_weekdays(): void
    {
        $this->assertSame('monday', TitleCatalog::dayFilter('Monday'));
        $this->assertSame('sunday', TitleCatalog::dayFilter('sunday'));
        $this->assertNull(TitleCatalog::dayFilter('funday'));
    }

    public function test_helpers_read_v4_array(): void
    {
        $item = [
            'title' => 'One Piece',
            'type' => 'TV',
            'rating' => 'PG-13 - Teens 13 or older',
            'status' => 'Currently Airing',
            'episodes' => 1000,
            'chapters' => 1100,
            'aired' => ['from' => '1999-10-20T00:00:00+00:00', 'to' => null],
            'images' => [
                'jpg' => ['large_image_url' => 'https://cdn.example/a.jpg'],
                'webp' => ['large_image_url' => 'https://cdn.example/a.webp'],
            ],
        ];

        $this->assertSame('1999-10-20', TitleCatalog::dateFrom($item));
        $this->assertNull(TitleCatalog::dateTo($item));
        $this->assertSame('PG-13 - Teens 13 or older', TitleCatalog::rating($item));
        $this->assertSame('https://cdn.example/a.webp', TitleCatalog::coverUrl($item));
        $this->assertSame('Currently Airing', TitleCatalog::status($item));
        $this->assertSame(1000, TitleCatalog::episodes($item, false));
        $this->assertSame(1100, TitleCatalog::episodes($item, true));
        $this->assertSame('https://cdn.example/a.jpg', TitleCatalog::coverUrl([
            'images' => ['jpg' => ['large_image_url' => 'https://cdn.example/a.jpg']],
        ]));
    }
}
