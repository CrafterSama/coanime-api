<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Category as CategoryEnum;
use App\Enums\PostApproved;
use App\Enums\PostDraft;
use App\Enums\TitleStatus;
use App\Http\Requests\PostStoreRequest;
use App\Http\Requests\PostUpdateRequest;
use App\Models\Category;
use App\Models\Event;
use App\Models\Helper;
use App\Models\Magazine;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Title;
use App\Models\TitleImage;
use App\Models\User;
use App\Services\BroadcastService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Image;
use Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $categoryId = Category::where('slug', $request->category)->pluck('id')->first();
            $categories = $request->category ? [$categoryId] : CategoryEnum::defaultIds();

            $relevants = $request->category
            ? Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->publishedAndApproved()
                ->whereIn('category_id', $categories)
                ->where(function($query) {
                    $query->where('postponed_to', '<=', Carbon::now())
                          ->orWhereNull('postponed_to');
                })
                ->withImage()
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get()
            : Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->whereIn('category_id', $categories)
                ->whereBetween('postponed_to', [Carbon::now()->subMonth(24), Carbon::now()])
                ->withImage()
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->where('view_counter', '>', 5)
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $news = Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('users', 'categories', 'titles', 'tags')
                ->whereIn('category_id', $categories)
                ->withImage()
                ->where(function($query) {
                    $query->where('postponed_to', '<=', Carbon::now())
                          ->orWhereNull('postponed_to');
                })
                ->publishedAndApproved()
                ->orderBy('postponed_to', 'desc')
                ->take(5)->get();

            $keywords = [];
            foreach ($news as $p) {
                foreach ($p->tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            $keywords = implode(', ', $keywords);

            $broadcastService = new BroadcastService();
            $broadcast = $broadcastService->getTodaySchedule();

            $upcoming = Title::with('images', 'type')->where('broad_time', '>=', Carbon::now())->where('status', TitleStatus::ESTRENO->value)->orderBy('broad_time', 'asc')->take(15)->get();

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias de anime y manga, enciclopedia de anime y manga',
                'description' => 'Encuentra las noticias de anime, manga, video juegos y más`.',
                'image' => $news[0]->image,
                'keywords' => $keywords,
                'relevants' => $relevants,
                'broadcast' => $broadcast,
                'upcoming' => $upcoming,
                'result' => $news,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error to obtain data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all items of the resource by tags.
     *
     * @param  string  $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllByTag(Request $request, $tag)
    {
        try {
            $tag_id = Tag::where('slug', '=', $tag)->pluck('id');
            $categories = CategoryEnum::defaultIds();

            /* $posts = Post::whereHas('tags', function ($q) use ($tag_id) {
                $q->where('tag_id', $tag_id);
            })->with('users', 'categories', 'tags')->orderBy('postponed_to', 'desc')->simplePaginate(); */

            $relevants = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->whereHas('tags', function ($q) use ($tag_id) {
                    $q->where('tag_id', $tag_id);
                })
                ->with('categories', 'tags', 'users')
                ->whereIn('category_id', $categories)
                ->whereBetween('postponed_to', [Carbon::now()->subDays(3), Carbon::now()])
                ->withImage()
                ->publishedAndApproved()
                ->where('view_counter', '>', 5)
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $news = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->whereHas('tags', function ($q) use ($tag_id) {
                    $q->where('tag_id', $tag_id);
                })
                ->with('users', 'categories', 'titles', 'tags')
                ->whereIn('category_id', $categories)
                ->withImage()
                ->where(function($query) {
                    $query->where('postponed_to', '<=', Carbon::now())
                          ->orWhereNull('postponed_to');
                })
                ->where('approved', PostApproved::YES->value)
                ->where('draft', PostDraft::PUBLISHED->value)
                ->orderBy('postponed_to', 'desc')
                ->take(4)->get();

            $keywords = [];
            foreach ($news as $p) {
                foreach ($p->tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            $keywords = implode(', ', $keywords);

            $broadcastService = new BroadcastService();
            $broadcast = $broadcastService->getScheduleByDay();

            $upcoming = Title::with('images', 'type')->where('broad_time', '>=', Carbon::now())->where('status', TitleStatus::ESTRENO->value)->orderBy('broad_time', 'asc')->take(10)->get();

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias de anime y manga, enciclopedia de anime y manga',
                'description' => 'Encuentra las noticias de anime, manga, video juegos y más`.',
                'image' => $news[0]->image,
                'keywords' => $keywords,
                'relevants' => $relevants,
                'broadcast' => $broadcast,
                'upcoming' => $upcoming,
                'result' => $news,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error to obtain data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * All the Articles
     */
    public function posts(Request $request)
    {
        $categoryId = Category::where('slug', $request->category)->pluck('id')->first();
        $categories = $request->category ? [$categoryId] : CategoryEnum::defaultIds();
        $news = Post::search($request->name)
            ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
            ->with('users', 'categories', 'titles', 'tags')
            ->where('approved', PostApproved::YES->value)
            ->where('draft', PostDraft::PUBLISHED->value)
            ->whereIn('category_id', $categories)
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->withImage()
            ->orderBy('postponed_to', 'desc')
            ->take(4)->get();

        $ids = [];
        foreach ($news as $p) {
            $ids[] = $p->id;
        }

        $posts = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->whereNotIn('id', $ids)
            ->where('approved', PostApproved::YES->value)
            ->where('draft', PostDraft::PUBLISHED->value)
            ->whereIn('category_id', $categories)
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->withImage()
            ->orderBy('postponed_to', 'desc')
            ->paginate(15);

        return $posts;
    }

    /**
     * All the Articles
     */
    public function postsByTags(Request $request, $tag)
    {
        $tag_id = Tag::where('slug', '=', $tag)->pluck('id');
        $categories = CategoryEnum::defaultIds();
        $news = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
            ->whereHas('tags', function ($q) use ($tag_id) {
                $q->where('tag_id', $tag_id);
            })
            ->with('users', 'categories', 'titles', 'tags')
            ->whereIn('category_id', $categories)
            ->withImage()
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->where('approved', PostApproved::YES->value)
            ->where('draft', PostDraft::PUBLISHED->value)
            ->orderBy('postponed_to', 'desc')
            ->take(4)->get();

        $ids = [];
        foreach ($news as $p) {
            $ids[] = $p->id;
        }

        $posts = Post::search($request->name)
            ->whereHas('tags', function ($q) use ($tag_id) {
                $q->where('tag_id', $tag_id);
            })
            ->with('users', 'categories', 'titles', 'tags')
            ->whereNotIn('id', $ids)
            ->where('approved', PostApproved::YES->value)
            ->where('draft', PostDraft::PUBLISHED->value)
            ->whereIn('category_id', $categories)
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->withImage()
            ->orderBy('postponed_to', 'desc')
            ->paginate(15);

        return $posts;
    }

    public function postsJapan(Request $request)
    {
        $news = Post::search($request->name)
            ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
            ->with('users', 'categories', 'titles', 'tags')
            ->where('approved', 'yes')
            ->withImage()
            ->where('draft', '0')
            ->whereNotIn('category_id', [CategoryEnum::CATEGORY_10->value])
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->orderBy('postponed_to', 'desc')
            ->take(4)->get();

        $ids = [];
        foreach ($news as $p) {
            $ids[] = $p->id;
        }

        return Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->whereNotIn('id', $ids)
            ->where('approved', PostApproved::YES->value)
            ->where('draft', PostDraft::PUBLISHED->value)
            ->whereIn('category_id', [1])
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->withImage()
            ->orderBy('postponed_to', 'desc')
            ->paginate(8);
    }

    public function postsDashboard(Request $request)
    {
        $query = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->publishedAndApproved();

        // Filters
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('tag_id') && $request->tag_id) {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'postponed_to');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'desc';
        
        // Allowed sort columns
        $allowedSortColumns = ['postponed_to', 'created_at', 'updated_at', 'title', 'view_counter'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('postponed_to', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min(max((int) $perPage, 1), 100); // Between 1 and 100

        $posts = $query->paginate($perPage);

        // Get filter options (solo si se solicita con ?include_filters=1)
        // Debug: verificar qué parámetros llegan
        \Log::info('Posts Dashboard Request', [
            'all_params' => $request->all(),
            'include_filters' => $request->get('include_filters'),
            'includeFilters' => $request->get('includeFilters'),
            'has_include_filters' => $request->has('include_filters'),
        ]);
        
        $includeFilters = $request->get('include_filters') ?? $request->get('includeFilters');
        if ($includeFilters == '1' || $includeFilters == 1 || $includeFilters === true || $includeFilters === 'true') {
            $categories = Category::orderBy('name', 'asc')->get(['id', 'name']);
            $users = \App\Models\User::whereHas('posts', function($q) {
                $q->where('approved', PostApproved::YES->value)
                  ->where('draft', PostDraft::PUBLISHED->value)
                  ->where(function($query) {
                      $query->where('postponed_to', '<=', Carbon::now())
                            ->orWhereNull('postponed_to');
                  });
            })
            ->withCompleteProfile()
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
            $tags = \App\Models\Tag::whereHas('posts', function($q) {
                $q->where('approved', PostApproved::YES->value)
                  ->where('draft', PostDraft::PUBLISHED->value)
                  ->where(function($query) {
                      $query->where('postponed_to', '<=', Carbon::now())
                            ->orWhereNull('postponed_to');
                  });
            })->orderBy('name', 'asc')->get(['id', 'name']);

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $posts,
                'filters' => [
                    'categories' => $categories,
                    'users' => $users,
                    'tags' => $tags,
                ],
            ], 200);
        }

        // Si no se solicitan filtros, devolver solo los posts sin filtros
        return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $posts,
            ], 200);
    }

    public function apiSearchPosts(Request $request)
    {
        $categories = $request->category ? [$request->category] : CategoryEnum::extendedDefaultIds();
        if ($request->name && $posts = Post::search($request->name)
            ->select('title', 'slug', 'category_id', 'image', 'postponed_to')
            ->with('categories')
            ->whereIn('category_id', $categories)
            ->where('posts.approved', PostApproved::YES->value)
            ->where('posts.draft', PostDraft::PUBLISHED->value)
            ->where('postponed_to', '<=', Carbon::now())
            ->orderBy('postponed_to', 'desc')
            ->paginate()) {
            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $posts,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => 'Not Found',
                'error' => NO_RESULTS,
            ], 404);
        }
    }

    /**
     * All the Categories
     */
    public function categories(Request $request)
    {
        try {
            $categories = Category::all();

            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Success'),
                'categories' => $categories,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => Helper::errorMessage('Error to obtain data'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * All the Articles by Category
     */
    public function postsByCategory(Request $request)
    {
        $carbon = new Carbon();
        $category = Category::where('slug', $request->category)->pluck('id')->first();
        //dd($category);
        $posts = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->withImage()
            ->where(function($query) {
                $query->where('postponed_to', '<=', Carbon::now())
                      ->orWhereNull('postponed_to');
            })
            ->where('category_id', $category)
            /*->where('approved', 'yes')
            ->where('draft', '0')*/
            ->orderBy('postponed_to', 'desc')
            ->paginate(15);

        return $posts;
    }

    /**
     * All the results for homePage from the API
     */
    public function apiPosts(Request $request)
    {
        try {
            $relevants = Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->where('approved', PostApproved::YES->value)
                ->where('draft', PostDraft::PUBLISHED->value)
                ->where('category_id', '!=', CategoryEnum::CATEGORY_10->value)
                ->where('view_counter', '>', 5)
                ->whereBetween('postponed_to', [Carbon::now()->subMonths(36), Carbon::now()])
                ->orWhere('postponed_to', null)
                ->withImage()
                /*->orWhere('image', '!=', 'https://coanime.net/images/posts/')*/
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $posts = Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('users', 'categories', 'titles', 'tags')
                ->where('approved', PostApproved::YES->value)
                ->where('draft', PostDraft::PUBLISHED->value)
                ->where('category_id', '!=', CategoryEnum::CATEGORY_10->value)
                ->where(function($query) {
                    $query->where('postponed_to', '<=', Carbon::now())
                          ->orWhereNull('postponed_to');
                })
                ->withImage()
                /*->orWhere('image', '!=', 'https://coanime.net/images/posts/')*/
                ->orderBy('postponed_to', 'desc')
                ->paginate(4);

            $events = Event::select('city_id', 'country_code', 'created_at', 'date_start', 'id', 'slug', 'image', 'name', 'user_id')->with('users', 'city', 'country')
                ->where('date_start', '>', Carbon::now())
                ->orderBy('date_start', 'asc')
                ->take(20)
                ->get();

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias y Enciclopedia de Cultura Japonesa, Manga y Anime',
                'description' => 'Tu Fuente de Información sobre Manga, Anime, Cultura Otaku con noticias mas relevantes y actuales del Medio y en tu idioma, subscribete.',
                'events' => $events,
                'relevants' => $relevants,
                'result' => $posts,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'code' => 404,
                'message' => 'Error, Not Found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function getRandomPostImage(Request $request, $width = 1920)
    {
        $directory = 'https://coanime.net/images/posts/';

        $images = glob($directory.'*-'.$width.'w.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $randomImage = basename($images[array_rand($images)]);

        $publicPath = 'https://coanime.net/images/posts/';

        return response()->json(['random_image' => $publicPath.$randomImage], 200);
    }

    public function getRandomPostImageByTitle(Request $request, $slug)
    {
        $tag_id = Tag::where('slug', '=', $slug)->pluck('id');
        $arrayImages = [];

        if ($tag_id->count() > 0) {
            $query = Post::getByTitle($tag_id);
            if ($query->count() > 0) {
                $posts = $query->get();

                foreach ($posts as $post) {
                    array_push($arrayImages, $post->image);
                }

                //$randomImage = basename($arrayImages[array_rand($arrayImages)]);
                $randomImage = $arrayImages[array_rand($arrayImages)];

                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'success',
                        'text' => 'Imagen encontrada',
                    ],
                    'url' => $randomImage,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => [
                        'type' => 'error',
                        'text' => NO_RESULTS,
                    ],
                ], 404);
            }
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => NO_RESULTS,
                ],
            ], 404);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        $currentUser = Auth::user()->id;
        $data = new Post();
        $data->user_id = $currentUser;
        $data->category_id = 1;
        $data->draft = PostDraft::DRAFT->value;
        $data->postponed_to = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        $id = $data->id;

        return redirect()->to('/dashboard/posts/'.$data->id.'/edit');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PostStoreRequest $request)
    {

        $data = new Post();
        $data = $request->all();
        $currentUser = Auth::user()->id;
        $data['user_id'] = $currentUser;
        $data['slug'] = Str::slug($data['title']);

        if ($request->slug) {
            $data['slug'] = $request->slug;
        } else {
            if (Post::where('slug', 'like', $data['slug'])->count() > 0) {
                $data['slug'] = Str::slug($data['title']).'1';
            }
            $data['slug'] = Str::slug($data['title']);
        }

        if ($request->postponed_to == '') {
            $data['postponed_to'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        // Remove image from data array - we'll handle it separately with Media Library
        $imageUrl = $data['image'] ?? null;
        unset($data['image']);

        try {
            if ($data = Post::create($data)) {
                // Handle image upload via Media Library
                if ($request->hasFile('image')) {
                    $data->addMediaFromRequest('image')
                        ->usingName("Post {$data->id} - {$data->title}")
                        ->toMediaCollection('featured-image');
                } elseif ($imageUrl) {
                    // If image is a URL (from ImageController), add from URL
                    try {
                        $data->addMediaFromUrl($imageUrl)
                            ->usingName("Post {$data->id} - {$data->title}")
                            ->toMediaCollection('featured-image');
                    } catch (\Exception $e) {
                        \Log::warning("Could not add image from URL for post {$data->id}: " . $e->getMessage());
                    }
                }

                if (! empty($request['title_id'])) {
                    $data->titles()->sync([$request['title_id']]);
                }

                if (count($request['tag_id']) > 0 || $request['tag_id'] != null) {
                    $tags = [];
                    $tagData = [];
                    foreach ($request['tag_id'] as $key => $value) {
                        if (is_numeric($value)) {
                            $tags[] = $value;
                        } else {
                            $tagData['name'] = strtolower($value);
                            $tagData['slug'] = Str::slug($value);
                            $checkTag = Tag::where('slug', $tagData['slug']);
                            if ($checkTag->count() > 0) {
                                $tags[] = $checkTag->pluck('id')->first();
                            } else {
                                //dd($tagData);
                                if (is_object($tagData)) {
                                    $tagData = Tag::create($tagData->toArray());
                                } else {
                                    $tagData = Tag::create($tagData);
                                }
                                $tags[] = $tagData->id;
                            }
                        }
                    }
                    if ($tags) {
                        $data->tags()->sync($tags);
                    }
                }

                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Post Created Successfully'),
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'code' => 422,
                    'message' => Helper::errorMessage('Post Not Created'),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 422,
                'message' => Helper::errorMessage('Error, Internal Server Error . '.$e->getMessage()),
            ]);
        }
    }

    public function checkTags()
    {
        $posts = Post::all();

        foreach ($posts as $post) {
            $string = $post->slug;
            $results = '';
            $postTags = explode('-', $string);
            $excludedWords = ['la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en'];
            $results = array_diff($postTags, $excludedWords);
            $tags = '';
            $tagData = collect();

            foreach ($results as $key => $result) {
                $data = [];
                $tagId = Tag::where('slug', Str::slug($result))->pluck('id');
                if ($tagId->count() > 0) {
                    $tags = $tagId->first();
                } else {
                    $data['name'] = $result;
                    $data['slug'] = Str::slug($result);
                    $data = Tag::create($data);
                    $tags = $data->pluck('id')->first();
                }
                $tagData->push($tags);
            }

            $post->tags()->sync($tagData->toArray());

            echo implode(',', $results).'<br />';
        }
    }

    /**
     * Get all items of the resource by Category.
     *
     * @param  string  $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllByCategory(Request $request, $category)
    {
        try {
            $category_id = Category::where('slug', '=', $category)->pluck('id')->first();
            $category_name = Category::where('slug', '=', $category)->pluck('name')->first();

            $relevants = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->where('approved', PostApproved::YES->value)
                ->where('draft', PostDraft::PUBLISHED->value)
                ->whereNotIn('category_id', [CategoryEnum::CATEGORY_10->value])
                ->where('view_counter', '>', 50)
                ->where('category_id', $category_id)
                ->whereBetween('postponed_to', [Carbon::now()->subMonths(12), Carbon::now()])
                ->orWhere('postponed_to', null)
                ->withImage()
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $news = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('users', 'categories', 'titles', 'tags')
                ->withImage()
                ->where('approved', PostApproved::YES->value)
                ->where('draft', PostDraft::PUBLISHED->value)
                ->whereNotIn('category_id', [CategoryEnum::CATEGORY_10->value])
                ->where('category_id', $category_id)
                ->where(function($query) {
                    $query->where('postponed_to', '<=', Carbon::now())
                          ->orWhereNull('postponed_to');
                })
                ->orderBy('postponed_to', 'desc')
                ->take(4)
                ->get();

            $keywords = [];
            foreach ($news as $p) {
                foreach ($p->tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }

            $keywords = implode(', ', $keywords);

            $broadcastService = new BroadcastService();
            $broadcast = $broadcastService->getTodaySchedule();

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias relacionadas con '.$category_name,
                'description' => 'Posts y noticias relacionados con la categoria '.$category_name,
                'keywords' => $keywords,
                'broadcast' => $broadcast,
                /*'events' => $events,*/
                'relevants' => $relevants,
                /*'videos' => $videos,*/
                'result' => $news,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all the genre.
     * return
     */
    public function showAllTags()
    {
        $tags = Tag::withCount('posts')->whereHas('posts', function ($q) {
            $q->where('post_id', '!=', null);
        })->orderBy('name', 'asc')->simplePaginate(100);
        //dd($tags);
        return response()->json([
            'code' => 200,
            'message' => 'Success!!',
            'tags' => $tags,
        ], 200);
        //return view('tags.home', compact('tags'));
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function page($slug)
    {
        $id = Post::where('slug', $slug)->pluck('id');
        if ($id->count() > 0) {
            $post = Post::with('users', 'categories', 'titles')->find($id);
            $post = collect($post);
            $post->increment('view_counter');
            //dd($post);

            return response()->json([
                'code' => 200,
                'message' => 'Success!!',
                'post' => $post,
            ], 200);
        //return view('pages.details', compact('post'));
        } else {
            return response()->json([
                'code' => 404,
                'message' => 'Not Found!!',
            ], 404);
            //return view('errors.404');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug)
    {
        $categories = Category::orderBy('name', 'asc')->get();
        if (Post::where('slug', 'like', $slug)->pluck('id')->count() > 0) {
            $post = Post::with('users', 'categories', 'titles', 'tags', 'votes')->whereSlug($slug)->firstOrFail();

            $keywords = [];

            foreach ($post->tags as $t) {
                $keywords[] = $t->name;
            }

            $keywords = implode(',', $keywords);

            if ($keywords == '') {
                $excludedWords = ['la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en'];
                $keywords = array_diff(explode('-', $post->slug), $excludedWords);
                $keywords = implode(',', $keywords);
            }

            $post->increment('view_counter');

            if ($post->votes->count() === 0) {
                $votes = '';
            } else {
                if (Auth::guest()) {
                    $votes = '';
                } else {
                    $postVotes = $post->votes;
                    if ($post->votes->count() === 1) {
                        $postVotes = $post->votes[0];
                    }
                    if ($postVotes->user_id === Auth::user()->id) {
                        $votes = $postVotes->status;
                    } else {
                        $votes = '';
                    }
                }
            }

            $otherArticles = Post::select('id', 'title', 'category_id', 'slug', 'image')->with('categories')
                ->where('category_id', '=', $post->category_id)
                ->where('view_counter', '>', '100')
                ->whereNotIn('id', [$post->id])
                ->whereNotIn('image', ['https://coanime.net/images/posts/'])
                ->orderBy('postponed_to', 'desc')
                ->get();

            if ($otherArticles->count() > 2) {
                $otherArticles = $otherArticles->random(3);
                $newArticles = [];
                foreach ($otherArticles as $index) {
                    array_push($newArticles, $index);
                }
            }

            if ($post->titles->count() > 0) {
                $titleImage = TitleImage::where('title_id', $post->titles[0]->id)->get()->pluck('name');
                $tag_id = Tag::where('slug', '=', Str::slug($post->titles[0]->name))->get()->pluck('id');

                if ($tag_id->count() > 0) {
                    $postByTags = DB::table('post_tag')->where('tag_id', $tag_id)->whereNotIn('post_id', [$post->id])->orderBy('post_id', 'desc')->get()->pluck('post_id');

                    $relateds = [];

                    foreach ($postByTags as $item) {
                        $add = Post::select('id', 'title', 'slug', 'image')->find($item);
                        if ($add !== null) {
                            array_push($relateds, $add);
                        }
                    }
                    if (count($relateds) > 0) {
                        if (count($relateds) > 3) {
                            $relateds = array_slice($relateds, 3);
                        }
                    }
                } else {
                    $relatedTitle = Title::with('posts')->find($post->titles[0]->id);
                    $relateds = $relatedTitle->posts;
                }
            } else {
                $relateds = [];
                $titleImage = '';
            }

            return response()->json([
                'code' => 200,
                'message' => ['type' => 'success', 'text' => 'Post found'],
                'post' => $post,
                'titleImage' => $titleImage,
                'categories' => $categories,
                'relateds' => $relateds,
                'votes' => $votes,
                'keywords' => $keywords,
                'otherArticles' => $otherArticles,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => ['type' => 'error', 'text' => 'Post not found'],
            ], 404);
        }
    }

    /**
     * Display the Specific Resource in json format
     */
    public function showApi($slug)
    {
        $keywords = [];
        $relateds = [];
        $newArticles = [];

        if (Post::where('slug', '=', $slug)->pluck('id')->count() > 0) {
            $post = Post::with('users', 'categories', 'titles', 'tags')->whereSlug($slug)->firstOrFail();

            if ($post->tags->count() > 0) {
                foreach ($post->tags as $t) {
                    $keywords[] = $t->name;
                }
                $keywords = implode(',', $keywords);
            } else {
                $string = $post->slug;
                $postTags = explode('-', $string);
                $excludedWords = ['la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en'];
                $keywords = array_diff($postTags, $excludedWords);
                $keywords = implode(',', $keywords);
            }

            $post->increment('view_counter');

            $otherArticles = Post::select('id', 'title', 'category_id', 'slug', 'image')->with('categories')
                ->where('category_id', '=', $post->category_id)
                ->where('view_counter', '>', '100')
                ->withImage()
                ->whereNotIn('id', [$post->id])
                ->orderBy('postponed_to', 'desc')
                ->get();
            if ($otherArticles->count() > 2) {
                $otherArticles = $otherArticles->random(3);
                if ($otherArticles->count() > 0) {
                    foreach ($otherArticles as $index) {
                        array_push($newArticles, $index);
                    }
                }
            }

            if ($post->titles->count() > 0) {
                $tag_id = Tag::where('slug', '=', Str::slug($post->titles[0]->name))->get()->pluck('id');

                if ($tag_id->count() > 0) {
                    $postByTags = DB::table('post_tag')->where('tag_id', $tag_id)->whereNotIn('post_id', [$post->id])->orderBy('post_id', 'desc')->get()->pluck('post_id');

                    foreach ($postByTags as $item) {
                        array_push($relateds, Post::select('id', 'category_id', 'title', 'slug', 'image')->with('categories')->find($item));
                    }
                    if (count($relateds) > 0) {
                        if (count($relateds) > 3) {
                            $relateds = array_slice($relateds, 3);
                        }
                    }
                } else {
                    $relatedTitle = Title::with('posts')->find($post->titles[0]->id);
                    $relateds = $relatedTitle->posts;
                }
            } else {
                $relateds = [];
            }

            if (count(Helper::getVideoLink($post->content)) > 0) {
                $videoLinks[] = Helper::getVideoLink($post->content);
            } else {
                $videoLinks = [];
            }
            $post->content = Helper::bbcodeToHtmlSafe($post->content ?? '');

            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Post found'),
                'title' => 'Articulos - '.$post->categories->name.' - '.$post->title,
                'description' => $post->excerpt,
                'path_image' => $post->image,
                'tags' => $keywords,
                'result' => $post,
                'article_video_links' => $videoLinks,
                'other_articles' => $newArticles,
                'relateds' => $relateds,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage('Post not found'),
            ], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, $id)
    {
        if (empty($id)) {
            $currentUser = Auth::user()->id;
            $data = new Post();
            $data->user_id = $currentUser;
            $data->category_id = 1;
            $data->draft = PostDraft::DRAFT->value;
            $data->postponed_to = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
            $id = $data->id;
        }
        $post = Post::with('users', 'categories', 'titles', 'tags')->find($id);
        $categories = Category::pluck('name', 'id');
        $tags = Tag::pluck('name', 'id');
        $selected = $post->tags()->pluck('tag_id')->toArray();
        $post->content = Helper::bbcodeToHtmlSafe($post->content ?? '');

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'title' => POST_EDIT_TITLE,
            'description' => POST_EDIT_TITLE,
            'path_posts' => 'https://coanime.net/posts/',
            'path_image' => $post->image,
            'result' => $post,
            'categories' => $categories,
            'tags' => $tags,
            'selected' => $selected,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PostUpdateRequest $request, $id)
    {

        $data = Post::with('users', 'categories', 'titles', 'tags')->find($id);
        $post = $request->all();
        $currentUser = Auth::user()->id;
        $data['edited_by'] = $currentUser;

        if ($request->slug) {
            $data['slug'] = $request->slug;
        } else {
            if (Post::where('slug', 'like', $data['slug'])->count() > 0) {
                $data['slug'] = Str::slug($data['title']).'1';
            }
            $data['slug'] = Str::slug($data['title']);
        }

        if ($request->postponed_to === '') {
            $data['postponed_to'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        //$data['postponed_to'] = Carbon::createFromFormat('d-m-Y H:i', $request->postponed_to)->format('Y-m-d H:i:s');

        //dd($data['postponed_to']);

        $data['draft'] = PostDraft::PUBLISHED->value;

        // Handle image upload via Media Library
        if ($request->hasFile('image')) {
            // Remove old image from Media Library
            $data->clearMediaCollection('featured-image');
            
            // Add new image
            $data->addMediaFromRequest('image')
                ->usingName("Post {$data->id} - {$request->title}")
                ->toMediaCollection('featured-image');
            
            // Remove image from post array to avoid saving URL in old field
            unset($post['image']);
        } elseif ($request->has('image') && $request->image) {
            // If image is a URL (from ImageController), check if it's already in Media Library
            $existingMedia = $data->getFirstMedia('featured-image');
            if (!$existingMedia || $existingMedia->getUrl() !== $request->image) {
                // If URL is different, try to add from URL
                // Note: This assumes the URL is accessible
                try {
                    $data->clearMediaCollection('featured-image');
                    $data->addMediaFromUrl($request->image)
                        ->usingName("Post {$data->id} - {$request->title}")
                        ->toMediaCollection('featured-image');
                } catch (\Exception $e) {
                    // If URL is not accessible, keep the old behavior for backward compatibility
                    \Log::warning("Could not add image from URL for post {$data->id}: " . $e->getMessage());
                }
            }
            // Remove image from post array
            unset($post['image']);
        }

        if ($data->update($post)) {
            if (! empty($request['title_id'])) {
                $data->titles()->sync([$request['title_id']]);
            }
            if (count($request['tag_id']) > 0 || $request['tag_id'] != null) {
                $tags = [];
                $tagData = [];
                foreach ($request['tag_id'] as $key => $value) {
                    if (is_numeric($value)) {
                        $tags[] = $value;
                    } else {
                        $tagData['name'] = strtolower($value);
                        $tagData['slug'] = Str::slug($value);
                        $checkTag = Tag::where('slug', $tagData['slug']);
                        if ($checkTag->count() > 0) {
                            $tags[] = $checkTag->pluck('id')->first();
                        } else {
                            //dd($tagData);
                            if (is_object($tagData)) {
                                $tagData = Tag::create($tagData->toArray());
                            } else {
                                $tagData = Tag::create($tagData);
                            }
                            $tags[] = $tagData->id;
                        }
                    }
                }
                if ($tags) {
                    $data->tags()->sync($tags);
                }
            }

            return response()->json([
                'code' => 200,
                'message' => ['type' => 'success', 'text' => 'Post actualizado correctamente'],
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 500,
                'message' => 'Error',
                'title' => POST_EDIT_TITLE,
                'description' => POST_EDIT_TITLE,
                'path_posts' => 'https://coanime.net/posts/',
                'path_image' => $data->image,
                'result' => $data,
            ], 500);
        }
    }

    public function approved($id, Request $request)
    {
        $post = Post::find($id);
        $post->approved = $request['approved'];
        $post->save();

        return response()->json([
            'code' => 200,
            'message' => 'Success!! Post aprobado',
            'title' => POST_EDIT_TITLE,
            'description' => POST_EDIT_TITLE,
            'path_posts' => 'https://coanime.net/posts/',
            'path_image' => $post->image,
            'result' => $post,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $post = Post::find($id);

        if ($post->delete()) {
            return response()->json([
                'code' => 200,
                'message' => 'Success!! Post Eliminado',
                'title' => 'Coanime.net - Eliminar Post',
                'description' => 'El Post fue eliminado de forma satisfactoria',
                'result' => $post,
            ], 200);
        } else {
            return response()->json([
                'code' => 500,
                'message' => 'Error!! Post no Eliminado',
                'title' => 'Coanime.net - Eliminar Post',
                'description' => 'El Post no fue Eliminado',
                'result' => $post,
            ], 500);
        }
    }

    /**
     * Metho to show the deleted posts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyPosts(Request $request)
    {
        $posts = Post::search($request->name)->with('users', 'categories', 'titles', 'tags')->orderBy('id', 'desc')->onlyTrashed()->paginate(20);

        return response()->json([
            'code' => 200,
            'message' => 'Success!!',
            'title' => 'Coanime.net - Lista de Post Eliminados',
            'description' => 'Coanime.net - Post Eliminados que aun existen en el Sistema',
            'result' => $posts,
        ], 200);
    }

    /**
     * Show some Data in the initial Dashboard view.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard()
    {
        $userId = Auth::user()->id;
        $postsCount = Post::where('user_id', $userId)->count();
        $titlesCount = Title::where('user_id', $userId)->count();
        $mostVisitedPost = Post::where('user_id', $userId)->orderBy('view_counter', 'desc')->first();
        $mostPostsCategory = Category::withCount('posts')->orderBy('posts_count', 'desc')->first();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'title' => 'Coanime.net - Dashboard',
            'description' => 'Dashboard',
            'posts_count' => $postsCount,
            'titles_count' => $titlesCount,
            'most_visited_post' => $mostVisitedPost,
            'most_posts_category' => $mostPostsCategory,
        ], 200);
    }

    public function changeImagesPath()
    {
        $posts = Post::all();
        $titles = Title::with('images')->get();
        $users = User::all();
        $magazines = Magazine::all();

        foreach ($posts as $post) {
            $post->image = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $post->image);
            $post->image = str_replace('https://coanime.net/images/posts/', 'https://api.coanime.net/storage/images/posts', $post->image);
            $post->content = str_replace('https://coanime.net/images/', 'https://api.coanime.net/storage/images/', $post->content);
            $post->content = str_replace('https://images.coanime.net/images/', 'https://api.coanime.net/storage/images/', $post->content);
            $post->content = str_replace('http://images.coanime.net/images/', 'https://api.coanime.net/storage/images/', $post->content);
            $post->content = str_replace('https://coanime.net/images/posts/', 'https://api.coanime.net/storage/images/', $post->content);
            $post->save();
        }
        foreach ($titles as $title) {
            if ($title->images) {
                $data = TitleImage::find($title->images->id);
                $data->name = str_replace('https://coanime.net/images/encyclopedia/titles/https://coanime.net/', 'https://api.coanime.net/storage/', $data->name);
                $data->name = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $data->name);
                $data->thumbnail = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $data->thumbnail);
                $data->save();
            }
        }
        foreach ($users as $user) {
            $user->profile_photo_path = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $user->profile_photo_path);
            $user->save();
        }

        return response()->json([
            'code' => 200,
            'message' => 'Image URL for Posts Changed Successfully!!',
        ], 200);
    }
}