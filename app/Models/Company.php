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

class Company extends Model implements HasMedia
{
    use SoftDeletes;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';

    protected $dates = ['deleted_at', 'foundation_date', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'user_id', 'about', 'foundation_date', 'slug', 'country_code', 'website'];

    protected $appends = ['image'];

    /**
     * Register media collections for Company model (single logo/cover).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp']);
    }

    /**
     * Register media conversions for Company model.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->keepOriginalAspectRatio()
            ->sharpen(10)
            ->performOnCollections('default');
    }

    /**
     * Get image URL from Spatie Media (for API/frontend).
     *
     * @param  mixed  $value  Legacy DB value (unused; Company has no image column).
     * @return string|null
     */
    public function getImageAttribute($value = null)
    {
        $media = $this->getFirstMedia('default');
        if ($media) {
            return $media->getUrl();
        }

        return null;
    }

    public function scopeSearch($query, $name)
    {
        return $query->where('name', 'like', '%'.$name.'%');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso3');
    }

    /**
     * Configuración de logs de actividad para el modelo Company.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'user_id', 'about', 'foundation_date', 'country_code', 'website'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "Company {$eventName}: {$name}";
            });
    }
}
