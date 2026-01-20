<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Magazine extends Model
{
    use SoftDeletes;
    use LogsActivity;

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