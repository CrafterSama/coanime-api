<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TitleImage extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'titles_image';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'title_id', 'thumbnail'];

    public function titles()
    {
        return $this->belongsTo(Title::class, 'title_id', 'id');
    }
}
