<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class People extends Model
{
    use SoftDeletes;
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
        if (strlen($name) > 1):
            return $query->where('name', 'like', '%' . $name . '%');
            else:
                return $query->where('name', 'like', $name . '%');
            endif;
    }

    public static function name($id)
    {
        $people = People::find($id);
        $fnmame = $people->first_name;
        $lname = $people->last_name;
        $name = $fnmame . ' ' . $lname;
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
}
