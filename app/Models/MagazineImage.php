<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MagazineImage extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'magazines_image';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'magazine_id'];

    public function magazine()
    {
        return $this->belongsTo(Magazine::class, 'magazine_id', 'id');
    }
}
