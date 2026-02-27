<?php

declare(strict_types=1);

namespace App\Services\News\Scrapers;

use App\DataTransferObjects\NewsArticleData;
use Illuminate\Support\Collection;

interface NewsScraperInterface
{
    /**
     * Identificador de la fuente (coincide con la clave de config('news.sources')).
     */
    public function getSourceKey(): string;

    /**
     * Obtiene el listado de noticias más recientes normalizado en NewsArticleData.
     *
     * @param  int  $limit  Máximo de noticias a devolver.
     * @return Collection<int, NewsArticleData>
     */
    public function fetchLatest(int $limit = 10): Collection;
}

