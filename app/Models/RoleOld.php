<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
