<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class TitleRate extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'title_rates';
    protected $fillable = ['user_id', 'rate_id', 'title_id'];
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function titles()
    {
        return $this->belongsTo(Title::class, 'title_id', 'id');
    }

    public function rates()
    {
        return $this->belongsTo(Rate::class, 'rate_id', 'id');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
