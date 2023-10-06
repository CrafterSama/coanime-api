<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Genre extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'genre';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function titles()
    {
        return $this->belongsToMany(Title::class);
    }
}
