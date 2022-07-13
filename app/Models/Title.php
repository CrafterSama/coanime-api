<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Title extends Model
{
    use SoftDeletes;

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
        return $query->where('name', 'like', $name . '%')
            ->orWhere('other_titles', 'like', $name . '%')
            ->orWhere('sinopsis', 'like', $name . '%');
    }

    public static function scopeTitles($query, $name)
    {
        return $query->where('name', 'like', $name . '%')
            ->orWhere('other_titles', 'like', $name . '%');
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

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->orderBy('id', 'desc');
    }

    public function relateds(): HasMany
    {
        return $this->hasMany(Related::class);
    }

    public function getFirstDateAttribute()
    {
        return $this->broad_time->format('d/m/Y');
    }

    public function getFirstDateYearAttribute()
    {
        return $this->broad_time->format('Y');
    }

    public function getLastDateAttribute()
    {
        return $this->broad_finish->format('d/m/Y');
    }

    public function getLastDateYearAttribute()
    {
        return $this->broad_finish->format('Y');
    }
}
