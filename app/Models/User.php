<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use InteractsWithMedia;

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
        return $this->hasMany(Post::class)->where('approved', 'yes')->whereRaw('TIMESTAMP(postponed_to) <= NOW()')->orWhere('postponed_to', null)->orderBy('id', 'desc');
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
}
