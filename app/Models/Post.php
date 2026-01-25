<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Category as CategoryEnum;
use App\Enums\PostApproved;
use App\Enums\PostDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Post extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'created_at', 'updated_at', 'postponed_to'];

    protected $fillable = ['title', 'excerpt', 'content', 'category_id', 'user_id', 'slug', 'approved', 'draft', 'image', 'postponed_to', 'created_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'approved' => PostApproved::class,
        'draft' => PostDraft::class,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    public function scopeSearch($query, $name)
    {
        return $query->where('title', 'like', '%'.$name.'%')->with('tags', function ($q) use ($name) {
            $q->where('name', 'like', '%'.$name.'%');
        });
    }

    public function scopeNotPagesCategories($query, $category)
    {
        return $query->whereNotIn('category_id', [$category]);
    }

    /**
     * Scope to filter approved posts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', PostApproved::YES->value);
    }

    /**
     * Scope to filter not approved posts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotApproved($query)
    {
        return $query->where('approved', PostApproved::NO->value);
    }

    /**
     * Scope to filter published posts (not drafts)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('draft', PostDraft::PUBLISHED->value);
    }

    /**
     * Scope to filter draft posts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDrafts($query)
    {
        return $query->where('draft', PostDraft::DRAFT->value);
    }

    /**
     * Scope to filter published and approved posts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublishedAndApproved($query)
    {
        return $query->where('approved', PostApproved::YES->value)
                     ->where('draft', PostDraft::PUBLISHED->value);
    }

    /**
     * Scope to filter posts that are ready to be displayed
     * (approved, published, and not postponed or postponed date has passed)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReadyToDisplay($query)
    {
        return $query->where('approved', PostApproved::YES->value)
                     ->where('draft', PostDraft::PUBLISHED->value)
                     ->where(function($q) {
                         $q->where('postponed_to', '<=', now())
                           ->orWhereNull('postponed_to');
                     });
    }

    public function fullContent($id)
    {
        $post = Post::find($id);
        $intro = $post->intro;
        $content = $post->content;
        $post = $intro.$content;

        //dd($post);
        return $post;
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function categories()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id')->select(['id', 'name', 'slug']);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function titles()
    {
        return $this->belongsToMany(Title::class)->with('images', 'type', 'users', 'genres', 'posts');
    }

    public function votes()
    {
        return $this->hasMany(PostVote::class);
    }

    public function getVideoLinksAttribute(): array
    {
        $videoLinks = [];
        if ($this->category_id === CategoryEnum::CATEGORY_13->value) {
            $videoLinks = Helper::getVideoLink($this->content);
        }

        return $videoLinks;
    }

    public static function getByTitle($id)
    {
        return DB::table('posts')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->join('categories', 'categories.id', '=', 'posts.category_id')
            ->join('post_tag', 'post_tag.post_id', '=', 'posts.id')
            ->join('tags', 'post_tag.tag_id', '=', 'tags.id')
            ->where('tags.id', '=', $id)
            ->select(
                'posts.*',
                'tags.name as tag_name',
                'tags.slug as tag_slug',
                'tags.id as tag_id',
                'users.name as user_name',
                'users.slug as user_slug',
                'categories.name as category_name',
                'categories.slug as category_slug'
            );
    }

    /**
     * Configuración de logs de actividad para el modelo Post.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'excerpt', 'slug', 'category_id', 'user_id', 'approved', 'draft', 'image', 'postponed_to'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $title = $this->title ?? 'Sin título';
                return "Post {$eventName}: {$title}";
            });
    }

    /*public function toSearchableArray()
    {
        $array = $this->toArray();

        // If you want, apply the default transformations
        $array = $this->transform($array);

        // Apply custom treatment

        return $array;
    }*/
}
