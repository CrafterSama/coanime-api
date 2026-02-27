<?php

declare(strict_types=1);

namespace App\Services\Images;

class ImageSelector
{
    /**
     * Dado un array de URLs de imágenes, devuelve la que tenga mayor resolución.
     * Si no se puede determinar el tamaño de ninguna, devuelve la primera válida.
     */
    public function pickBest(array $urls): ?string
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return null;
        }

        $best = null;
        $bestArea = 0;

        foreach ($urls as $url) {
            try {
                $size = @getimagesize($url);
                if ($size === false || ! isset($size[0], $size[1])) {
                    if ($best === null) {
                        $best = $url;
                    }
                    continue;
                }
                $area = (int) $size[0] * (int) $size[1];
                if ($area > $bestArea) {
                    $bestArea = $area;
                    $best = $url;
                }
            } catch (\Throwable) {
                if ($best === null) {
                    $best = $url;
                }
            }
        }

        return $best;
    }
}

