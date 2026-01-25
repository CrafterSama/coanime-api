<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Magazine extends Model implements HasMedia
{
    use SoftDeletes;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'magazines';

    protected $dates = ['deleted_at', 'foundation_date', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'user_id', 'about', 'foundation_date', 'slug', 'type_id', 'release_id', 'country_code', 'website'];

    public function scopeSearch($query, $name)
    {
        if ($name && strlen($name) > 1) {
            return $query->where('name', 'like', '%'.$name.'%');
        } else {
            return $query->where('name', 'like', $name.'%');
        }
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso3');
    }

    public function type()
    {
        return $this->belongsTo(MagazineType::class);
    }

    public function release()
    {
        return $this->belongsTo(MagazineRelease::class);
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function image()
    {
        return $this->hasOne(MagazineImage::class);
    }

    /**
     * Register media collections for Magazine model
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->useDisk('s3')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Register media conversions for Magazine model
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(400)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(800)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1600)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');
    }

    /**
     * Get cover image URL - compatible with old code
     * Falls back to old 'image' relationship if media doesn't exist
     * Returns original URL if media is a placeholder
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        if ($media) {
            // If it's a placeholder, return the original URL
            if ($media->getCustomProperty('is_placeholder', false)) {
                return $media->getCustomProperty('original_url', $this->image?->name);
            }
            return $media->getUrl();
        }

        // Fallback to old relationship
        return $this->image?->name;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        return $media ? $media->getUrl('thumb') : null;
    }

    /**
     * Configuración de logs de actividad para el modelo Magazine.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'user_id', 'about', 'foundation_date', 'type_id', 'release_id', 'country_code', 'website'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "Magazine {$eventName}: {$name}";
            });
    }
}