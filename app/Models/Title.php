<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Title extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'broad_finish', 'broad_time', 'created_at', 'updated_at'];

    protected $fillable = ['name', 'user_id', 'episodies', 'sinopsis', 'slug', 'type_id', 'other_titles', 'trailer_url', 'status', 'rating_id', 'broad_time', 'broad_finish', 'updated_by', 'just_year'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'titles';

    public function scopeSearch($query, $name)
    {
        return $query->where('name', 'like', '%'.$name.'%')
            ->orWhere('other_titles', 'like', '%'.$name.'%')
            ->orWhere('sinopsis', 'like', '%'.$name.'%');
    }

    public static function scopeTitles($query, $name)
    {
        return $query->where('name', 'like', $name.'%')
            ->orWhere('other_titles', 'like', $name.'%');
    }

    /*public function scopeByGenre($genre, $query) {
        return $query->whereHas('Genre', function ($q) use ($genre) {
            $q->where('genre_id', $genre->id);
        });
    }*/

    public function images(): HasOne
    {
        return $this->hasOne(TitleImage::class);
    }

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Ratings::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TitleType::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(TitleStatistics::class);
    }

    public function rates(): HasOne
    {
        return $this->hasOne(TitleRate::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->orderBy('id', 'desc');
    }

    public function relateds(): HasMany
    {
        return $this->hasMany(Related::class);
    }

    public function getBroadTimeAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    public function getBroadFinishAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Configuración de logs de actividad para el modelo Title.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'user_id', 'type_id', 'other_titles', 'sinopsis', 'status', 'rating_id', 'broad_time', 'broad_finish', 'updated_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $name = $this->name ?? 'Sin nombre';
                return "Title {$eventName}: {$name}";
            });
    }
}