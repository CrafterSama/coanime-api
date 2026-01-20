<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class People extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'people';

    protected $dates = ['deleted_at', 'birthday', 'falldown_date', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'japanese_name', 'areas_skills_hobbies', 'bio', 'city_id', 'country_code', 'slug', 'birthday', 'falldown', 'falldown_date', 'approved', 'image', 'user_id'];

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
     * Configuración de logs de actividad para el modelo People.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'japanese_name', 'slug', 'user_id', 'bio', 'areas_skills_hobbies', 'city_id', 'country_code', 'birthday', 'falldown', 'falldown_date', 'approved', 'image'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "People {$eventName}: {$name}";
            });
    }
}