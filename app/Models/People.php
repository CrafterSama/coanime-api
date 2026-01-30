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

class People extends Model implements HasMedia
{
    use SoftDeletes;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'people';

    protected $dates = ['deleted_at', 'birthday', 'falldown_date', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'japanese_name', 'areas_skills_hobbies', 'about', 'city_id', 'country_code', 'slug', 'birthday', 'falldown', 'falldown_date', 'approved', 'image', 'user_id'];

    /**
     * Register media collections for People model (single cover/avatar).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp']);
    }

    /**
     * Register media conversions for People model.
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
        if ($name && strlen($name) > 1) {
            return $query->where('name', 'like', '%'.$name.'%');
        } else {
            return $query->where('name', 'like', $name.'%');
        }
    }

    public static function name($id)
    {
        $people = People::find($id);
        $fnmame = $people->first_name;
        $lname = $people->last_name;
        $name = $fnmame.' '.$lname;

        return $name;
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
     * Alias for API/frontend: bio is stored in DB column "about".
     */
    public function getBioAttribute()
    {
        return $this->attributes['about'] ?? null;
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

        return rtrim(config('app.url'), '/').'/storage/images/encyclopedia/people/'.ltrim($value, '/');
    }

    /**
     * Configuración de logs de actividad para el modelo People.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'japanese_name', 'slug', 'user_id', 'about', 'areas_skills_hobbies', 'city_id', 'country_code', 'birthday', 'falldown', 'falldown_date', 'approved', 'image'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "People {$eventName}: {$name}";
            });
    }
}