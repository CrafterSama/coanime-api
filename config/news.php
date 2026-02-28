<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fuentes de noticias de anime/manga
    |--------------------------------------------------------------------------
    |
    | Configuración de las distintas fuentes externas que se van a scrapear.
    | Aquí se definen las URLs base y los selectores CSS necesarios para
    | obtener el listado y el detalle de cada noticia.
    |
    */

    'sources' => [
        'animecorner' => [
            'name' => 'Anime Corner',
            'base_url' => 'https://animecorner.me',
            'list_url' => 'https://animecorner.me/category/news/',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'timeout' => 10,
            'list' => [
                'item' => 'article',
                'title' => 'h2.entry-title a',
                'excerpt' => '.entry-excerpt',
                'date_text' => '.entry-date',
                'date_attr' => '.entry-date',
                'date_attr_name' => 'datetime',
                'image' => '.entry-thumb img',
                'link' => 'h2.entry-title a',
            ],
            'detail' => [
                'content' => '.entry-content, .post-content, .single-content',
            ],
        ],

        'otakuusa' => [
            'name' => 'Otaku USA Magazine',
            'base_url' => 'https://otakuusamagazine.com',
            'list_url' => 'https://otakuusamagazine.com/anime-latest-news/',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'timeout' => 10,
            'list' => [
                'item' => '.post',
                'title' => 'h2 a, h3 a, .entry-title a',
                'excerpt' => '.entry-summary, .excerpt',
                'date_attr' => '.entry-date, time',
                'date_attr_name' => 'datetime',
                'image' => 'img',
                'link' => 'h2 a, h3 a, .entry-title a',
            ],
            'detail' => [
                'content' => '.entry-content, .post-content, .single-content',
            ],
        ],

        // Ejemplo de noticia: https://myanimelist.net/news/73917655
        'myanimelist' => [
            'name' => 'MyAnimeList',
            'base_url' => 'https://myanimelist.net',
            'list_url' => 'https://myanimelist.net/news',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'timeout' => 10,
            'list' => [
                'item' => '.news-unit',
                'title' => '.title a',
                'excerpt' => '.text',
                'date_text' => '.info',
                'image' => '.image img',
                'link' => '.title a',
                // Fallback si MAL cambia el HTML: enlaces directos a /news/ID
                'item_fallback_link' => 'a[href*="/news/"]',
            ],
            'detail' => [
                'content' => '.news-container, .content-left, .content .news-container, [class*="news-container"], [class*="news-content"]',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Límites y opciones generales del scrapper
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'per_source' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo de categorías externas a internas
    |--------------------------------------------------------------------------
    |
    | Estos mapas se usan para tratar de alinear las categorías que vienen de
    | las fuentes externas con las categorías internas del proyecto.
    | Se pueden ir afinando con el tiempo.
    |
    */

    'category_map' => [
        // Ejemplos genéricos, se deben ajustar según las categorías reales
        'japan' => 'japon',
        'anime' => 'anime',
        'manga' => 'manga',
        'light novel' => 'light-novel',
        'manhwa' => 'corea',
        'manhua' => 'china',
        'games' => 'videojuegos',
        'live-action' => 'live-action',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Bigjpg
    |--------------------------------------------------------------------------
    */

    'bigjpg' => [
        'api_key' => env('BIGJPG_API_KEY'),
        'endpoint' => 'https://bigjpg.com/api/task/',
        // Valores por defecto; se pueden sobreescribir en código si hace falta.
        'defaults' => [
            'style' => 'art',
            'noise' => '3',
            'x2' => '1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Traducción (Google Translate)
    |--------------------------------------------------------------------------
    */

    'translation' => [
        'target_locale' => 'es',
    ],
];

