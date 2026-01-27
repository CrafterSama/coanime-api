<?php

declare(strict_types=1);

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

    /**
     * Events in this country (for filter lists: countries that have events).
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'country_code', 'iso3');
    }

    /**
     * Companies in this country (for filter lists: countries that have companies).
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'country_code', 'iso3');
    }

    /**
     * People in this country (for filter lists: countries that have people).
     */
    public function people()
    {
        return $this->hasMany(People::class, 'country_code', 'iso3');
    }

    /**
     * Magazines in this country (for filter lists: countries that have magazines).
     */
    public function magazines()
    {
        return $this->hasMany(Magazine::class, 'country_code', 'iso3');
    }
}
