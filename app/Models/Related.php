<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Related extends Model
{

    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'relateds';
    protected $fillable = ['title_id', 'related_id', 'type'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    /**
     * Many to Many Relationship to the posts
     */
    public function titles()
    {
        return $this->belongsTo(Title::class, 'title_id', 'id');
    }
}