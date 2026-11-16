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

class Event extends Model implements HasMedia
{
    use SoftDeletes;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events';

    protected $dates = ['deleted_at', 'date_start', 'date_end', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'user_id', 'description', 'date_start', 'date_end', 'slug', 'country_code', 'city_id', 'address', 'image'];

    /**
     * Register media collections for Event model (single cover image).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp']);
    }

    /**
     * Register media conversions for Event model.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->sharpen(10)
            ->performOnCollections('default');
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
     * Get image URL - from Spatie Media first, then legacy column for backward compatibility.
     */
    public function getImageAttribute($value)
    {
        $media = $this->getFirstMedia('default');
        if ($media) {
            return $media->getUrl();
        }
        if (empty($value)) {
            return null;
        }
        if (str_starts_with((string) $value, 'http')) {
            return $value;
        }

        return rtrim(config('app.url'), '/').'/storage/images/events/'.ltrim($value, '/');
    }

    /**
     * Configuración de logs de actividad para el modelo Event.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'user_id', 'description', 'date_start', 'date_end', 'country_code', 'city_id', 'address', 'image'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "Event {$eventName}: {$name}";
            });
    }
}
