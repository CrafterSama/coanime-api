# Editorial EN→ES con Gemini

La Editorial de Coanime localiza News de Tenrai (inglés) a español latinoamericano con identidad Coanime.

## Flujo

1. Cron diario `news:ingest-tenrai --limit=3` (08:00) o `POST /internal/news/ingest`
2. `CatalogNewsEditorial` pide a Gemini la nota en ES
3. Si Gemini falla o no hay key → fallback Google Translate
4. Se guarda el `Post` en español (`mal_news_id`, slug `mal-news-{id}`)

No se generan versiones en otros idiomas.

## Variables de entorno

```env
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.8-flash
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
EDITORIAL_ENABLED=true
```

`EDITORIAL_LLM_*` (OpenAI) quedó deprecado y ya no se usa.

## API Key

1. Entrá a [Google AI Studio](https://aistudio.google.com/apikey)
2. Creá una API key
3. Ponela en `GEMINI_API_KEY` del host
4. No commits de la key

Modelo default: `gemini-3.8-flash` (cuota free según Google; `gemini-2.5-flash` ya no acepta usuarios nuevos). No hay auto-cambio a modelos de pago.

## Config editorial (admin)

- `GET /internal/editorial` — manual activo (o defaults)
- `GET /internal/editorial/versions` — historial
- `POST /internal/editorial` — publica nueva versión (requiere rol `administrator`)

Seed: `php artisan db:seed --class=EditorialManualSeeder`

## Ops

- Cron existente: `* * * * * php artisan schedule:run` (incluye ingest horario)
- Diagnóstico: `php artisan news:probe {malId}`
- Reingesta ES: `php artisan news:republish-es`
- Status: `GET /internal/news/editorial` (JWT, user id 1)
