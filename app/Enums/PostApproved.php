<?php

declare(strict_types=1);

namespace App\Enums;

enum PostApproved: string
{
    case YES = 'yes';
    case NO = 'no';

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
            self::YES => 'Aprobado',
            self::NO => 'No Aprobado',
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
     * Check if the post is approved
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this === self::YES;
    }

    /**
     * Check if the post is not approved
     *
     * @return bool
     */
    public function isNotApproved(): bool
    {
        return $this === self::NO;
    }

    /**
     * Get color class for UI (Bootstrap/Tailwind compatible)
     *
     * @return string
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::YES => 'text-green-600 bg-green-50',
            self::NO => 'text-red-600 bg-red-50',
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
            self::YES => 'check-circle',
            self::NO => 'x-circle',
        };
    }
}
