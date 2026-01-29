<?php

declare(strict_types=1);

namespace App\Enums;

enum PostDraft: string
{
    case DRAFT = '1';
    case PUBLISHED = '0';

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
            self::DRAFT => 'Borrador',
            self::PUBLISHED => 'Publicado',
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
     * Check if it's a draft
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if it's published
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * Toggle between draft and published
     *
     * @return self
     */
    public function toggle(): self
    {
        return $this === self::DRAFT ? self::PUBLISHED : self::DRAFT;
    }

    /**
     * Get color class for UI (Bootstrap/Tailwind compatible)
     *
     * @return string
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::DRAFT => 'text-yellow-600 bg-yellow-50',
            self::PUBLISHED => 'text-blue-600 bg-blue-50',
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
            self::DRAFT => 'edit',
            self::PUBLISHED => 'check',
        };
    }
}
