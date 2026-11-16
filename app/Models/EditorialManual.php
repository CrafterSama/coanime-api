<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class EditorialManual extends Model
{
    protected $fillable = ['version', 'payload'];

    protected $casts = [
        'payload' => 'array',
        'version' => 'integer',
    ];

    public static function active(): ?self
    {
        return static::query()->orderByDesc('version')->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function publish(array $payload): self
    {
        $next = (int) (static::query()->max('version') ?? 0) + 1;

        return static::query()->create([
            'version' => $next,
            'payload' => $payload,
        ]);
    }

    public static function defaultPayload(): array
    {
        return [
            'medium_name' => 'Coanime',
            'declaration' => 'Coanime: vivimos las historias que contamos. Medio digital de anime, manga y cultura otaku que informa con rigor, comunica con entusiasmo y acerca cada historia a su comunidad. Hablamos como aficionados que investigan, no como espectadores que repiten rumores.',
            'tone' => 'Cercano, informativo y natural en español latinoamericano. Evitar regionalismos excesivamente locales.',
            'title_rules' => 'Titulares atractivos, claros y verificables. Identificar la franquicia. Evitar clickbait engañoso. Mantener equilibrio SEO/legibilidad.',
            'content_rules' => 'Mantener significado, citas y datos. No inventar hechos ni spoilers. Adaptar expresiones. Parecer escrito por un editor de Coanime en español.',
            'fidelity_rules' => 'Rigor antes que viralidad. No presentar rumores como confirmaciones. Conservar atribuciones. Diferenciar confirmado de especulación.',
            'html_rules' => 'Conservar HTML, enlaces, imágenes, embeds y estructura. Usar <p> y <h4>. No eliminar multimedia.',
            'profile' => [
                'cercania' => 9,
                'informativo' => 9,
                'entusiasmo' => 7,
                'profesionalismo' => 8,
                'humor' => 3,
                'sensacionalismo' => 0,
            ],
            'glossary' => [
                ['term' => 'anime', 'do_not_translate' => true, 'official_es' => null],
                ['term' => 'manga', 'do_not_translate' => true, 'official_es' => null],
                ['term' => 'seiyuu', 'do_not_translate' => false, 'official_es' => 'seiyū'],
            ],
        ];
    }

    public static function systemPrompt(): string
    {
        $payload = static::defaultPayload();
        try {
            $manual = static::active();
            if (is_array($manual?->payload)) {
                $payload = $manual->payload;
            }
        } catch (Throwable) {
            // ponytail: unit tests sin tabla; fallback al payload default
        }

        $glossaryLines = [];
        foreach ($payload['glossary'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['term'])) {
                continue;
            }
            $term = (string) $row['term'];
            if (($row['do_not_translate'] ?? false) === true) {
                $glossaryLines[] = "- No traducir: {$term}";
            } elseif (! empty($row['official_es'])) {
                $glossaryLines[] = "- {$term} → {$row['official_es']}";
            }
        }
        $glossary = $glossaryLines === [] ? '- (sin entradas)' : implode("\n", $glossaryLines);

        $profile = $payload['profile'] ?? [];
        $profileText = is_array($profile)
            ? json_encode($profile, JSON_UNESCAPED_UNICODE)
            : '{}';

        return trim(<<<PROMPT
Eres la redacción de {$payload['medium_name']} (español latinoamericano).
Declaración: {$payload['declaration']}
Tono: {$payload['tone']}
Titulares: {$payload['title_rules']}
Contenido: {$payload['content_rules']}
Fidelidad: {$payload['fidelity_rules']}
HTML: {$payload['html_rules']}
Perfil editorial (orientativo): {$profileText}
Glosario (prioridad sobre preferencias del modelo):
{$glossary}
No inventes fechas, staff ni reparto.
No menciones MyAnimeList, MAL, Tenrai ni firmas de rewrite; el texto debe parecer original de Coanime.
Responde SOLO JSON válido según el esquema pedido.
PROMPT);
    }
}
