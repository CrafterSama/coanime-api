<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cities';

    public function event()
    {
        return $this->belongsTo(Event::class, 'city_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function person()
    {
        return $this->hasOne(People::class, 'city_id', 'id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'city_id', 'id');
    }
}
