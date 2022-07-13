<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events';
    protected $dates = ['deleted_at', 'date_start', 'date_end', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'user_id', 'description', 'date_start', 'date_end', 'slug', 'country_code', 'city_id', 'address', 'image'];

    public function scopeSearch($query, $name)
    {
        return $query->where('name', 'like', '%' . $name . '%');
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
}
