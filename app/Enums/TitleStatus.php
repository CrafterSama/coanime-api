<?php

declare(strict_types=1);

namespace App\Enums;

enum TitleStatus: string
{
    case ESTRENO = 'Estreno';
    case FINALIZADO = 'Finalizado';
    case EN_EMISION = 'En emisión';

    /**
     * Get all values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get label for display
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ESTRENO => 'Estreno',
            self::FINALIZADO => 'Finalizado',
            self::EN_EMISION => 'En emisión',
        };
    }

    /**
     * Get options for select dropdowns
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $carry, self $case) => $carry + [$case->value => $case->label()],
            []
        );
    }

    /**
     * Try to get enum from value, return null if not found
     *
     * @param string|null $value
     * @return self|null
     */
    public static function tryFromValue(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Check if status is Estreno
     *
     * @return bool
     */
    public function isEstreno(): bool
    {
        return $this === self::ESTRENO;
    }

    /**
     * Check if status is Finalizado
     *
     * @return bool
     */
    public function isFinalizado(): bool
    {
        return $this === self::FINALIZADO;
    }

    /**
     * Check if status is En emisión
     *
     * @return bool
     */
    public function isEnEmision(): bool
    {
        return $this === self::EN_EMISION;
    }

    /**
     * Check if title is currently active (En emisión or Estreno)
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this === self::EN_EMISION || $this === self::ESTRENO;
    }

    /**
     * Get color class for UI (Bootstrap/Tailwind compatible)
     *
     * @return string
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::ESTRENO => 'text-purple-600 bg-purple-50',
            self::FINALIZADO => 'text-gray-600 bg-gray-50',
            self::EN_EMISION => 'text-green-600 bg-green-50',
        };
    }

    /**
     * Get icon name for UI
     *
     * @return string
     */
    public function icon(): string
    {
        return match ($this) {
            self::ESTRENO => 'star',
            self::FINALIZADO => 'check-circle',
            self::EN_EMISION => 'play-circle',
        };
    }
}
