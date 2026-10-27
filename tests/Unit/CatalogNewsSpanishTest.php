<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CatalogNewsEditorial;
use Tests\TestCase;

class CatalogNewsSpanishTest extends TestCase
{
    public function test_detects_spanish_copy(): void
    {
        $this->assertTrue(CatalogNewsEditorial::isSpanish(
            '¡Phantom Busters revela su elenco principal!',
            'El anime ya tiene caras confirmadas.',
            '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>',
            "'Phantom Busters' Announces Main Cast, Staff",
            'The official website for Phantom Busters...'
        ));
    }

    public function test_rejects_english_copy(): void
    {
        $this->assertFalse(CatalogNewsEditorial::isSpanish(
            "'Phantom Busters' Announces Main Cast, Staff",
            'The official website for Phantom Busters...',
            '<p>The official website announced the main cast.</p>',
            "'Phantom Busters' Announces Main Cast, Staff",
            'The official website for Phantom Busters...'
        ));
    }
}
