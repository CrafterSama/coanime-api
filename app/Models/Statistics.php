<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Statistics extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'statistics';

    protected $fillable = ['name', 'slug'];
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    public function titleStatistics()
    {
        return $this->belongsTo(TitleStatistics::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
