<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'countries';

    public function event()
    {
        return $this->hasOne(Event::class, 'iso3', 'country_code');
    }

    public function person()
    {
        return $this->hasOne(People::class, 'iso3', 'country_code');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'iso3', 'country_code');
    }
}
