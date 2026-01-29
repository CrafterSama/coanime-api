<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\TitleStatusCast;
use App\Enums\TitleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Title extends Model implements HasMedia
{
    use SoftDeletes;
    use LogsActivity;
    use InteractsWithMedia;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'broad_finish', 'broad_time', 'created_at', 'updated_at'];

    protected $fillable = ['name', 'user_id', 'episodies', 'sinopsis', 'slug', 'type_id', 'other_titles', 'trailer_url', 'status', 'rating_id', 'broad_time', 'broad_finish', 'updated_by', 'just_year'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TitleStatusCast::class,
    ];

    /**
     * Accessors to append to array/JSON (cover uses Spatie media; fallback to images).
     *
     * @var array<int, string>
     */
    protected $appends = ['cover_image_url'];

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

    /**
     * Scope to filter titles by status
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param TitleStatus $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, TitleStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter titles that are Estreno
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEstreno($query)
    {
        return $query->where('status', TitleStatus::ESTRENO->value);
    }

    /**
     * Scope to filter titles that are Finalizado
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFinalizado($query)
    {
        return $query->where('status', TitleStatus::FINALIZADO->value);
    }

    /**
     * Scope to filter titles that are En emisión
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnEmision($query)
    {
        return $query->where('status', TitleStatus::EN_EMISION->value);
    }

    /**
     * Scope to filter active titles (Estreno, En emisión or Publicándose)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            TitleStatus::ESTRENO->value,
            TitleStatus::EN_EMISION->value,
            TitleStatus::PUBLICANDOSE->value,
        ]);
    }

    /**
     * Whether this title is missing MAL-enrichable info (cover, sinopsis, trailer, genres, etc.).
     * Used by TitleObserver to dispatch EnrichTitleFromMalJob. Requires type_id.
     */
    public function hasMissingMalInfo(): bool
    {
        if ($this->type_id === null) {
            return false;
        }

        if ($this->getFirstMedia('cover') === null) {
            return true;
        }

        $synopsis = trim((string) ($this->sinopsis ?? ''));
        $placeholders = [
            'Sinopsis no disponible',
            'Sinopsis no disponible.',
            'Pendiente de agregar sinopsis...',
            'Sinopsis en Proceso',
        ];
        if ($synopsis === '' || in_array($synopsis, $placeholders, true)) {
            return true;
        }

        if ($this->trailer_url === null || $this->trailer_url === '') {
            return true;
        }

        if ($this->genres()->count() === 0) {
            return true;
        }

        if ($this->rating_id === null || $this->rating_id === 7) {
            return true;
        }

        if ($this->episodies === null || (int) $this->episodies === 0) {
            return true;
        }

        $zero = '0000-00-00 00:00:00';
        if ($this->broad_time === null || $this->broad_time === $zero) {
            return true;
        }
        if ($this->broad_finish === null || $this->broad_finish === $zero) {
            return true;
        }

        return false;
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
     * Register media collections for Title model
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->useDisk('s3')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Register media conversions for Title model
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(450)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(900)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1800)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('cover');
    }

    /**
     * Get cover image URL - compatible with old code
     * Falls back to old 'images' relationship if media doesn't exist
     * Returns original URL if media is a placeholder
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        if ($media) {
            // If it's a placeholder, return the original URL
            if ($media->getCustomProperty('is_placeholder', false)) {
                return $media->getCustomProperty('original_url', $this->images?->name);
            }
            return $media->getUrl();
        }

        // Fallback to old relationship
        return $this->images?->name;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        if ($media) {
            return $media->getUrl('thumb');
        }

        // Fallback to old relationship
        return $this->images?->thumbnail ?? $this->images?->name;
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