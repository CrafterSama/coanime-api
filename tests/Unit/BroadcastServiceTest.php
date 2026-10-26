<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BroadcastService;
use App\Support\MalJikan;
use PHPUnit\Framework\TestCase;

class BroadcastServiceTest extends TestCase
{
    public function test_day_maps_to_schedule_getter(): void
    {
        $this->assertSame('getMonday', MalJikan::dayGetter('Monday'));
        $this->assertSame('getSunday', MalJikan::dayGetter('sunday'));
        $this->assertNull(MalJikan::dayGetter('funday'));
    }

    public function test_coanime_url_uses_slugged_type_and_title(): void
    {
        $this->assertSame(
            'https://coanime.net/ecma/titulos/tv/one-piece',
            BroadcastService::coanimeUrl('TV', 'One Piece')
        );
    }
}
