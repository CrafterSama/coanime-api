<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rates';
    protected $fillable = ['name', 'slug'];
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function titleRate()
    {
        return $this->belongsTo(TitleRate::class);
    }
}
