<?php

declare(strict_types=1);

namespace App\Helpers;

use BackedEnum;

/**
 * Helper class for working with Enums
 */
class EnumHelper
{
    /**
     * Get all enum values as array for validation rules
     *
     * @param string $enumClass
     * @return array
     */
    public static function values(string $enumClass): array
    {
        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not a BackedEnum");
        }

        return array_column($enumClass::cases(), 'value');
    }

    /**
     * Get enum options for select dropdowns
     *
     * @param string $enumClass
     * @return array
     */
    public static function options(string $enumClass): array
    {
        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not a BackedEnum");
        }

        if (method_exists($enumClass, 'options')) {
            return $enumClass::options();
        }

        // Fallback: use label() method if available
        $options = [];
        foreach ($enumClass::cases() as $case) {
            if (method_exists($case, 'label')) {
                $options[$case->value] = $case->label();
            } else {
                $options[$case->value] = $case->name;
            }
        }

        return $options;
    }

    /**
     * Try to get enum from value with null safety
     *
     * @param string $enumClass
     * @param mixed $value
     * @return BackedEnum|null
     */
    public static function tryFromValue(string $enumClass, mixed $value): ?BackedEnum
    {
        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not a BackedEnum");
        }

        if ($value === null) {
            return null;
        }

        return $enumClass::tryFrom($value);
    }
}
