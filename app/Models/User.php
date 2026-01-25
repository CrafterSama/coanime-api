<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostApproved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use InteractsWithMedia;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'bio', 'nick', 'twitter', 'facebook', 'pinterest', 'instagram', 'devianart', 'youtube', 'tiktok', 'behance', 'tumblr', 'website', 'genre', 'birthday', 'slug', 'profile_photo_path', 'profile_cover_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class)->orderBy('id', 'desc');
    }

    public function events()
    {
        return $this->hasMany(Event::class)->orderBy('id', 'desc');
    }

    public function magazine()
    {
        return $this->hasMany(Magazine::class)->orderBy('id', 'desc');
    }

    public function people()
    {
        return $this->hasMany(People::class)->orderBy('id', 'desc');
    }

    public function posts()
    {
        return $this->hasMany(Post::class)
            ->publishedAndApproved()
            ->whereRaw('TIMESTAMP(postponed_to) <= NOW()')
            ->orWhere('postponed_to', null)
            ->orderBy('id', 'desc');
    }

    public function titles()
    {
        return $this->hasMany(Title::class)->orderBy('id', 'desc');
    }

    public function titleStatistics()
    {
        return $this->hasOne(TitleStatistics::class)->with('titles', 'statistics');
    }

    public function rates()
    {
        return $this->hasOne(TitleRate::class);
    }

    public function isAdmin()
    {
        if ($this->hasRole('administrator')) {
            return true;
        }
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Configuración de logs de actividad para el modelo User.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'slug', 'profile_photo_path', 'profile_cover_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Usuario {$eventName}");
    }

    /**
     * Scope para filtrar usuarios con más de la mitad de su perfil completado.
     * 
     * Evalúa los siguientes campos del perfil:
     * - name, email, bio, website
     * - profile_photo_path, profile_cover_path
     * - twitter, facebook, instagram, youtube, pinterest, tiktok
     * - slug
     * 
     * Total: 13 campos. Más de la mitad = 7 o más campos completos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCompleteProfile($query)
    {
        $profileFields = [
            'name',
            'email',
            'bio',
            'website',
            'profile_photo_path',
            'profile_cover_path',
            'twitter',
            'facebook',
            'instagram',
            'youtube',
            'pinterest',
            'tiktok',
            'slug',
        ];

        $totalFields = count($profileFields);
        $minimumFields = (int) ceil($totalFields / 2) + 1; // Más de la mitad

        // Construir la expresión SQL de forma más mantenible
        $caseExpressions = array_map(function ($field) {
            return "(CASE WHEN {$field} IS NOT NULL AND {$field} != '' THEN 1 ELSE 0 END)";
        }, $profileFields);

        $sqlExpression = '(' . implode(' + ', $caseExpressions) . ') > ' . ($minimumFields - 1);

        return $query->whereRaw($sqlExpression);
    }
}
