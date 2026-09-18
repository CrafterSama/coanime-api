<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait MergesParamsPayload
{
    /**
     * Flatten nested `params` into the request root when present.
     * Flat payloads (without `params`) are left unchanged.
     */
    protected function prepareForValidation(): void
    {
        $params = $this->input('params');

        if (is_array($params)) {
            $this->merge($params);
        }
    }
}
