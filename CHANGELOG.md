# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## 2026-10-27

### feat: editorial Coanime para News de Tenrai

- **Fecha:** 2026-10-27
- **Branch:** feature/CACMS9-test-cloud-relevants-news
- Reescribe título, bajada y cuerpo al tono Coanime 2024 vía LLM OpenAI-compatible (`EDITORIAL_LLM_*`)
- Byline Redacción Coanime; fallback a Google Translate si no hay key o el LLM falla; cache 24 h

### feat: relevants de home con News de Tenrai

- **Fecha:** 2026-10-27
- **Branch:** feature/CACMS9-test-cloud-relevants-news
- Completa `relevants` de `/external/home` con News de Tenrai si no hay 3 posts locales de 7 días
- El detalle `/external/articles/mal-news-{id}` sirve el artículo Tenrai como post local (sin salir de Coanime)

### feat: catálogo genérico de títulos (Tenrai)

- **Fecha:** 2026-10-27
- **Branch:** feature/CACMS8-change-versions-and-jikan
- Reemplaza el scrape `jikan-me/jikan` por `TitleCatalog` HTTP (`CATALOG_URL`, default Tenrai)
- Broadcast, enrich y season sync usan schema v4; artisan `titles:sync-season`; se quitan Jikan y browser-kit

### chore: versionar leftovers JsonApiResource nativos
- **Fecha:** 2026-10-27
- **Branch:** feature/CACMS8-change-versions-and-jikan
- Sube controladores, resources y paginación JSON:API nativo de L13 que quedaron locales
- Las rutas `/api/external` siguen en laravel-json-api; este código aún no está cableado

## 2026-10-26

### feat: Laravel 13, PHP 8.4 y scraper MAL (jikan-me/jikan)
- **Fecha:** 2026-10-26
- **Branch:** feature/CACMS8-change-versions-and-jikan
- Sube PHP a ^8.4.4 y Laravel 11→13 (tinker ^3, phpunit ^12, boost ^2, laravel-json-api ^5.3)
- Reemplaza el cliente REST `jikan/jikan-php` (api.jikan.moe cerrado) por el parser `jikan-me/jikan` que scrapea myanimelist.net
- Broadcast, search y season sync dejan de depender de Jikan público; CSRF usa PreventRequestForgery
