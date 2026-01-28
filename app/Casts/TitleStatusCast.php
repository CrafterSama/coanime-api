<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\TitleStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class TitleStatusCast implements CastsAttributes
{
    /**
     * Cast the given value to TitleStatus. Uses tryFrom to avoid 500 errors
     * when the DB contains invalid or legacy status values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): TitleStatus
    {
        if ($value === null || $value === '') {
            return TitleStatus::FINALIZADO;
        }

        return TitleStatus::tryFrom((string) $value) ?? TitleStatus::FINALIZADO;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            return TitleStatus::FINALIZADO->value;
        }

        if ($value instanceof TitleStatus) {
            return $value->value;
        }

        $status = TitleStatus::tryFrom((string) $value);

        return $status !== null ? $status->value : TitleStatus::FINALIZADO->value;
    }
}
