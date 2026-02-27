<?php

declare(strict_types=1);

namespace App\Services\Translations;

use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GoogleTranslateService
{
    public function __construct(
        private readonly ?GoogleTranslate $translator = null,
    ) {
    }

    public function translate(string $text, string $target = null): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $targetLocale = $target ?: (string) config('news.translation.target_locale', 'es');

        try {
            $translator = $this->translator ?? new GoogleTranslate();
            $translator->setTarget($targetLocale);

            return (string) $translator->translate($trimmed);
        } catch (\Throwable $e) {
            Log::warning('GoogleTranslateService: error translating text', [
                'message' => $e->getMessage(),
            ]);

            return $text;
        }
    }
}

