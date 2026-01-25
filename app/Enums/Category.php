<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Category as CategoryModel;

enum Category: int
{
    // Categorías principales identificadas en el código
    case CATEGORY_1 = 1;
    case CATEGORY_2 = 2;
    case CATEGORY_3 = 3;
    case CATEGORY_4 = 4;
    case CATEGORY_5 = 5;
    case CATEGORY_6 = 6;
    case CATEGORY_7 = 7;
    case CATEGORY_8 = 8;
    case CATEGORY_9 = 9;
    case CATEGORY_10 = 10;
    case CATEGORY_11 = 11;
    case CATEGORY_12 = 12;
    case CATEGORY_13 = 13;

    /**
     * Get all category IDs as array
     *
     * @return array<int>
     */
    public static function ids(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get default categories used in most queries
     * Based on: [1, 2, 3, 4, 5, 6, 7, 8, 9, 11]
     *
     * @return array<int>
     */
    public static function defaultIds(): array
    {
        return [
            self::CATEGORY_1->value,
            self::CATEGORY_2->value,
            self::CATEGORY_3->value,
            self::CATEGORY_4->value,
            self::CATEGORY_5->value,
            self::CATEGORY_6->value,
            self::CATEGORY_7->value,
            self::CATEGORY_8->value,
            self::CATEGORY_9->value,
            self::CATEGORY_11->value,
        ];
    }

    /**
     * Get extended default categories
     * Based on: [1, 2, 3, 4, 5, 6, 7, 8, 11, 12, 13]
     *
     * @return array<int>
     */
    public static function extendedDefaultIds(): array
    {
        return [
            self::CATEGORY_1->value,
            self::CATEGORY_2->value,
            self::CATEGORY_3->value,
            self::CATEGORY_4->value,
            self::CATEGORY_5->value,
            self::CATEGORY_6->value,
            self::CATEGORY_7->value,
            self::CATEGORY_8->value,
            self::CATEGORY_11->value,
            self::CATEGORY_12->value,
            self::CATEGORY_13->value,
        ];
    }

    /**
     * Get Category model instance from database
     *
     * @return CategoryModel|null
     */
    public function model(): ?CategoryModel
    {
        return CategoryModel::find($this->value);
    }

    /**
     * Get category name from database
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return CategoryModel::where('id', $this->value)->value('name');
    }

    /**
     * Get category slug from database
     *
     * @return string|null
     */
    public function slug(): ?string
    {
        return CategoryModel::where('id', $this->value)->value('slug');
    }

    /**
     * Find category by slug from database
     *
     * @param string $slug
     * @return self|null
     */
    public static function fromSlug(string $slug): ?self
    {
        $id = CategoryModel::where('slug', $slug)->value('id');
        
        if ($id === null) {
            return null;
        }

        return self::tryFrom($id);
    }

    /**
     * Get all categories from database
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function allFromDatabase()
    {
        return CategoryModel::orderBy('name', 'asc')->get();
    }

    /**
     * Get categories for select dropdown
     *
     * @return array<int, string>
     */
    public static function forSelect(): array
    {
        return CategoryModel::pluck('name', 'id')->toArray();
    }

    /**
     * Check if a category ID exists in the enum
     *
     * @param int $id
     * @return bool
     */
    public static function exists(int $id): bool
    {
        return self::tryFrom($id) !== null;
    }

    /**
     * Get category name or return default if not found
     *
     * @param int $id
     * @param string $default
     * @return string
     */
    public static function getName(int $id, string $default = 'Unknown'): string
    {
        $enum = self::tryFrom($id);
        return $enum?->name() ?? $default;
    }

    /**
     * Get category slug or return null if not found
     *
     * @param int $id
     * @return string|null
     */
    public static function getSlug(int $id): ?string
    {
        $enum = self::tryFrom($id);
        return $enum?->slug();
    }

    /**
     * Get all category IDs that are in the default list
     *
     * @return array<int>
     */
    public static function getDefaultCategoryIds(): array
    {
        return self::defaultIds();
    }

    /**
     * Check if category is in default list
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return in_array($this->value, self::defaultIds());
    }

    /**
     * Check if category is in extended default list
     *
     * @return bool
     */
    public function isExtendedDefault(): bool
    {
        return in_array($this->value, self::extendedDefaultIds());
    }
}
