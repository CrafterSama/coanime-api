<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostVote extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_vote';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    protected $fillable = ['post_id', 'user_id', 'status'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function users()
    {
        return $this->hasOne(User::class);
    }
}
