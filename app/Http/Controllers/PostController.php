<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Company;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\PostVote;
use App\Models\Tag;
use App\Models\Title;
use App\Models\TitleImage;
use App\Models\TitleType;
use App\Models\User;
use App\Models\Helper;
use Carbon\Carbon;
use Jikan\JikanPHP\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Alert;
use Exception;
use Image;
use Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $categoryId = Category::where('slug', $request->category)->pluck('id')->first();
            $categories = $request->category ? [$categoryId] : [1,2,3,4,5,6,7,8,9,11];

            $relevants = $request->category
            ? Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->whereIn('category_id', $categories)
                ->where('postponed_to', '<=', Carbon::now())
                ->orWhere('postponed_to', null)
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get()
            : Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->whereIn('category_id', $categories)
                ->whereBetween('postponed_to', [Carbon::now()->subDays(3), Carbon::now()])
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
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
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
                ->where('postponed_to', '<=', Carbon::now())
                ->orWhere('postponed_to', null)
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->orderBy('postponed_to', 'desc')
                ->take(4)->get();

            $keywords = [];
            foreach ($news as $p) {
                foreach ($p->tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            $keywords = implode(', ', $keywords);

            $broadcastUrl = 'https://api.jikan.moe/v4/schedules/' . date("l");
            $json = file_get_contents($broadcastUrl);
            $broadcast = json_decode($json, true);

            $upcoming = Title::with('images', 'type')->where('broad_time', '>=', Carbon::now())->where('status', 'Estreno')->orderBy('broad_time', 'asc')->take(10)->get();

            return response()->json(array(
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias de anime y manga, enciclopedia de anime y manga',
                'description' => 'Encuentra las noticias de anime, manga, video juegos y más`.',
                'image' => $news[0]->image,
                'keywords' => $keywords,
                'relevants' => $relevants,
                'broadcast' => $broadcast['data'],
                'upcoming' => $upcoming,
                'result' => $news
            ), 200);
        } catch (Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => 'Error to obtain data',
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Get all items of the resource by tags.
     *
     * @param  str  $type
     * @return \Illuminate\Http\Response
     */
    public function showAllByTag(Request $request, $tag)
    {
        try {
            $tag_id = Tag::where('slug', '=', $tag)->pluck('id');
            $categories = [1,2,3,4,5,6,7,8,9,11];

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
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
                ->where('approved', 'yes')
                ->where('draft', '0')
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
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
                ->where('postponed_to', '<=', Carbon::now())
                ->orWhere('postponed_to', null)
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->orderBy('postponed_to', 'desc')
                ->take(4)->get();

            $keywords = [];
            foreach ($news as $p) {
                foreach ($p->tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            $keywords = implode(', ', $keywords);

            $broadcastUrl = 'https://api.jikan.moe/v4/schedules/' . strtolower(date("l"));
            $json = file_get_contents($broadcastUrl);
            $broadcast = json_decode($json, true);

            $upcoming = Title::with('images', 'type')->where('broad_time', '>=', Carbon::now())->where('status', 'Estreno')->orderBy('broad_time', 'asc')->take(10)->get();

            return response()->json(array(
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias de anime y manga, enciclopedia de anime y manga',
                'description' => 'Encuentra las noticias de anime, manga, video juegos y más`.',
                'image' => $news[0]->image,
                'keywords' => $keywords,
                'relevants' => $relevants,
                'broadcast' => $broadcast['data'],
                'upcoming' => $upcoming,
                'result' => $news
            ), 200);
        } catch (Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => 'Error to obtain data',
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * All the Articles
     *
     */
    public function posts(Request $request)
    {
        $categoryId = Category::where('slug', $request->category)->pluck('id')->first();
        $categories = $request->category ? [$categoryId] : [1,2,3,4,5,6,7,8,9,11];
        $news = Post::search($request->name)
        ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
        ->with('users', 'categories', 'titles', 'tags')
        ->where('approved', 'yes')
        ->where('draft', '0')
        ->whereIn('category_id', $categories)
        ->where('postponed_to', '<=', Carbon::now())
        ->orWhere('postponed_to', null)
        ->where('image', '!=', null)
        ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
        ->orderBy('postponed_to', 'desc')
        ->take(4)->get();

        $ids = [];
        foreach ($news as $p) {
            $ids[] = $p->id;
        }

        $posts = Post::search($request->name)
        ->with('users', 'categories', 'titles', 'tags')
        ->whereNotIn('id', $ids)
        ->where('approved', 'yes')
        ->where('draft', '0')
        ->whereIn('category_id', $categories)
        ->where('postponed_to', '<=', Carbon::now())
        ->orWhere('postponed_to', null)
        ->where('image', '!=', null)
        ->orderBy('postponed_to', 'desc')
        ->paginate(15);
        //dd($posts);
        return $posts;
    }

    /**
     * All the Articles
     *
     */
    public function postsByTags(Request $request, $tag)
    {
        $tag_id = Tag::where('slug', '=', $tag)->pluck('id');
        $categories = [1,2,3,4,5,6,7,8,9,11];
        $news = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
        ->whereHas('tags', function ($q) use ($tag_id) {
            $q->where('tag_id', $tag_id);
        })
        ->with('users', 'categories', 'titles', 'tags')
        ->whereIn('category_id', $categories)
        ->where('image', '!=', null)
        ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
        ->where('postponed_to', '<=', Carbon::now())
        ->orWhere('postponed_to', null)
        ->where('approved', 'yes')
        ->where('draft', '0')
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
        ->where('approved', 'yes')
        ->where('draft', '0')
        ->whereIn('category_id', $categories)
        ->where('postponed_to', '<=', Carbon::now())
        ->orWhere('postponed_to', null)
        ->where('image', '!=', null)
        ->orderBy('postponed_to', 'desc')
        ->paginate(15);

        return $posts;
    }

    public function postsJapan(Request $request)
    {
        $carbon = new Carbon();
        $news = Post::search($request->name)
            ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
            ->with('users', 'categories', 'titles', 'tags')
            ->where('approved', 'yes')
            ->where('image', '!=', null)
            ->where('draft', '0')
            ->whereNotIn('category_id', [10])
            ->where('postponed_to', '<=', Carbon::now())
            ->orWhere('postponed_to', null)
            ->orderBy('postponed_to', 'desc')
            ->take(4)->get();

        $ids = [];
        foreach ($news as $p) {
            $ids[] = $p->id;
        }

        $posts = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->whereNotIn('id', $ids)
            ->where('approved', 'yes')
            ->where('draft', '0')
            ->whereIn('category_id', [1])
            ->where('postponed_to', '<=', Carbon::now())
            ->orWhere('postponed_to', null)
            ->where('image', '!=', null)
            ->orderBy('postponed_to', 'desc')
            ->paginate(8);
        return $posts;
    }

    public function postsDashboard(Request $request)
    {
        $carbon = new Carbon();

        $posts = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->where('approved', 'yes')
            ->where('draft', '0')
            ->orderBy('postponed_to', 'desc')
            ->paginate();
        return $posts;
    }

    public function apiSearchPosts(Request $request)
    {
        $categories = $request->category ? [$request->category] : [1,2,3,4,5,6,7,8,11,12,13];
        if ($request->name && $posts = Post::search($request->name)
            ->select('title', 'slug', 'category_id', 'image', 'postponed_to')
            ->with('categories')
            ->whereIn('category_id', $categories)
            ->where('posts.approved', 'yes')
            ->where('posts.draft', '0')
            ->where('postponed_to', '<=', Carbon::now())
            ->orderBy('postponed_to', 'desc')
            ->paginate()) {
            return response()->json(array(
                'code' => 200,
                'message' => 'Success',
                'result' => $posts
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => 'Not Found',
                'error' => 'No se encontraron resultados'
            ), 404);
        }
    }

    /**
     * All the Categories
     *
     */
    public function categories(Request $request)
    {
        try {
            $categories = Category::all();
            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Success'),
                'categories' => $categories,
            ), 200);
        } catch (Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => Helper::errorMessage('Error to obtain data'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * All the Articles by Category
     *
     */
    public function postsByCategory(Request $request)
    {
        $carbon = new Carbon();
        $category = Category::where('slug', $request->category)->pluck('id')->first();
        //dd($category);
        $posts = Post::search($request->name)
            ->with('users', 'categories', 'titles', 'tags')
            ->where('image', '!=', null)
            ->where('postponed_to', '<=', Carbon::now())
            ->orWhere('postponed_to', null)
            ->where('category_id', $category)
            /*->where('approved', 'yes')
            ->where('draft', '0')*/
            ->orderBy('postponed_to', 'desc')
            ->paginate(15);
        return $posts;
    }

    /**
     * All the results for homePage from the API
     *
     */
    public function apiPosts(Request $request)
    {
        try {
            $relevants = Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->where('category_id', '!=', 10)
                ->where('view_counter', '>', 5)
                ->whereBetween('postponed_to', [Carbon::now()->subMonths(36), Carbon::now()])
                ->orWhere('postponed_to', null)
                ->where('image', '!=', null)
                /*->orWhere('image', '!=', 'https://coanime.net/images/posts/')*/
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $posts = Post::search($request->name)
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('users', 'categories', 'titles', 'tags')
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->where('category_id', '!=', 10)
                ->where('postponed_to', '<=', Carbon::now())
                ->orWhere('postponed_to', null)
                ->where('image', '!=', null)
                /*->orWhere('image', '!=', 'https://coanime.net/images/posts/')*/
                ->orderBy('postponed_to', 'desc')
                ->paginate(4);

            $events = Event::select('city_id', 'country_code', 'created_at', 'date_start', 'id', 'slug', 'image', 'name', 'user_id')->with('users', 'city', 'country')
                ->where('date_start', '>', Carbon::now())
                ->orderBy('date_start', 'asc')
                ->take(20)
                ->get();

            return response()->json(array(
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias y Enciclopedia de Cultura Japonesa, Manga y Anime',
                'description' => 'Tu Fuente de Información sobre Manga, Anime, Cultura Otaku con noticias mas relevantes y actuales del Medio y en tu idioma, subscribete.',
                'events' => $events,
                'relevants' => $relevants,
                'result' => $posts
            ), 200);
        } catch (Exception $e) {
            return response()->json(array(
                'code' => 404,
                'message' => 'Error, Not Found',
                'error' => $e->getMessage()
            ), 404);
        }
    }

    public function getRandomPostImage(Request $request, $width = 1920)
    {
        $directory = 'https://coanime.net/images/posts/';

        $images = glob($directory . '*-' . $width . 'w.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $randomImage = basename($images[array_rand($images)]);

        $publicPath = 'https://coanime.net/images/posts/';

        return response()->json(array('random_image' => $publicPath . $randomImage), 200);
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

                return response()->json(array(
                    'code' => 200,
                    'message' => array(
                        'type' => 'success',
                        'text' => 'Imagen encontrada',
                    ),
                    'url' => $randomImage
                ), 200);
            } else {
                return response()->json(array(
                    'code' => 404,
                    'message' => array(
                        'type' => 'error',
                        'text' => 'No se encontraron resultados',
                    ),
                ), 404);
            }
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => array(
                    'type' => 'error',
                    'text' => 'No se encontraron resultados',
                ),
            ), 404);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $currentUser = Auth::user()->id;
        $data = new Post();
        $data->user_id = $currentUser;
        $data->category_id = 1;
        $data->draft = 1;
        $data->postponed_to = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        $id = $data->id;

        return redirect()->to('/dashboard/posts/' . $data->id . '/edit');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|max:255',
            'excerpt' => 'required|max:255',
            'content' => 'required',
            'postponed_to' => 'date_format: "Y-m-d H:i:s"',
            'category_id' => 'required',
            'image' => 'required|max:255',
        ]);

        $data = new Post();
        $data = $request->all();
        $currentUser = Auth::user()->id;
        $data['user_id'] = $currentUser;
        $data['slug'] = Str::slug($data['title']);

        if ($request->slug) {
            $data['slug'] = $request->slug;
        } else {
            if (Post::where('slug', 'like', $data['slug'])->count() > 0) {
                $data['slug'] = Str::slug($data['title']) . '1';
            }
            $data['slug'] = Str::slug($data['title']);
        }


        if ($request->postponed_to == "") {
            $data['postponed_to'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        try {
            if ($data = Post::create($data)) {
                if (!empty($request['title_id'])) {
                    $data->titles()->sync([$request['title_id']]);
                }

                if (count($request['tag_id']) > 0 || $request['tag_id'] != null) {
                    $tags = array();
                    $tagData = array();
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
                return response()->json(array(
                    'code' => 200,
                    'message' => Helper::successMessage('Post Created Successfully'),
                    'data' => $data
                ));
            } else {
                return response()->json(array(
                    'code' => 422,
                    'message' => Helper::errorMessage('Post Not Created'),
                ));
            }
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 422,
                'message' => Helper::errorMessage('Error, Internal Server Error . ' . $e->getMessage()),
            ));
        }
    }

    public function checkTags()
    {
        $posts = Post::all();

        foreach ($posts as $post) {
            $string = $post->slug;
            $results = '';
            $postTags = explode("-", $string);
            $excludedWords = array('la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en');
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

            echo implode(',', $results) . '<br />';
        }
    }

    /**
     * Get all items of the resource by Category.
     *
     * @param  str  $type
     * @return \Illuminate\Http\Response
     */
    public function showAllByCategory(Request $request, $category)
    {
        try {
            $category_id = Category::where('slug', '=', $category)->pluck('id')->first();
            $category_name = Category::where('slug', '=', $category)->pluck('name')->first();

            $relevants = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('categories', 'tags', 'users')
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->whereNotIn('category_id', [10])
                ->where('view_counter', '>', 50)
                ->where('category_id', $category_id)
                ->whereBetween('postponed_to', [Carbon::now()->subMonths(12), Carbon::now()])
                ->orWhere('postponed_to', null)
                ->where('image', '!=', null)
                ->orderBy('view_counter', 'desc')
                ->take(3)
                ->get();

            $news = Post::select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at')
                ->with('users', 'categories', 'titles', 'tags')
                ->where('image', '!=', null)
                ->where('approved', 'yes')
                ->where('draft', '0')
                ->whereNotIn('category_id', [10])
                ->where('category_id', $category_id)
                ->where('postponed_to', '<=', Carbon::now())
                ->orWhere('postponed_to', null)
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

            $broadcastUrl = 'https://api.jikan.moe/v4/schedules/' . date("l");
            $json = file_get_contents($broadcastUrl);
            $broadcast = json_decode($json, true);

            return response()->json(array(
                'code' => 200,
                'message' => 'Success',
                'title' => 'Coanime.net - Noticias relacionadas con ' . $category_name,
                'description' => 'Posts y noticias relacionados con la categoria ' . $category_name,
                'keywords' => $keywords,
                'broadcast' => $broadcast['data'],
                /*'events' => $events,*/
                'relevants' => $relevants,
                /*'videos' => $videos,*/
                'result' => $news
            ), 200);
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Get all the genre.
     *
     */
    public function showAllTags()
    {
        $tags = Tag::withCount('posts')->whereHas('posts', function ($q) {
            $q->where('post_id', '!=', null);
        })->orderBy('name', 'asc')->simplePaginate(100);
        //dd($tags);
        return response()->json(array(
            'code' => 200,
            'message' => 'Success!!',
            'tags' => $tags,
        ), 200);
        //return view('tags.home', compact('tags'));
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\Response
     */
    public function page($slug)
    {
        $id = Post::where('slug', $slug)->pluck('id');
        if ($id->count() > 0) {
            $post = Post::with('users', 'categories', 'titles')->find($id);
            $post = collect($post);
            $post->increment('view_counter');
            //dd($post);

            return response()->json(array(
                'code' => 200,
                'message' => 'Success!!',
                'post' => $post,
            ), 200);
        //return view('pages.details', compact('post'));
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => 'Not Found!!',
            ), 404);
            //return view('errors.404');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        $categories = Category::orderBy('name', 'asc')->get();
        if (Post::where('slug', 'like', $slug)->pluck('id')->count() > 0) {
            $post = Post::with('users', 'categories', 'titles', 'tags', 'votes')->whereSlug($slug)->firstOrFail();

            $keywords = array();

            foreach ($post->tags as $t) {
                $keywords[] = $t->name;
            }

            $keywords = implode(',', $keywords);

            if ($keywords == '') {
                $excludedWords = array('la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en');
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
                $newArticles = array();
                foreach ($otherArticles as $index) {
                    array_push($newArticles, $index);
                }
            }


            if ($post->titles->count() > 0) {
                $titleImage = TitleImage::where('title_id', $post->titles[0]->id)->get()->pluck('name');
                $tag_id = Tag::where('slug', '=', Str::slug($post->titles[0]->name))->get()->pluck('id');

                if ($tag_id->count() > 0) {
                    $postByTags = DB::table('post_tag')->where('tag_id', $tag_id)->whereNotIn('post_id', [$post->id])->orderBy('post_id', 'desc')->get()->pluck('post_id');

                    $relateds = array();

                    foreach ($postByTags as $item) {
                        $add = Post::select('id', 'title', 'slug', 'image')->find($item);
                        if ($add !== null) :
                            array_push($relateds, $add);
                        endif;
                    }
                    //dd($relateds);
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

            return response()->json(array(
                'code' => 200,
                'message' => ['type' => 'success', 'text' => 'Post found'],
                'post' => $post,
                'titleImage' => $titleImage,
                'categories' => $categories,
                'relateds' => $relateds,
                'votes' => $votes,
                'keywords' => $keywords,
                'otherArticles' => $otherArticles,
            ), 200);
        //return view('web.details', compact('post', 'relateds', 'otherArticles', 'keywords', 'votes'));
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => ['type' => 'error', 'text' => 'Post not found'],
            ), 404);
            //return view('errors.404');
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
                $postTags = explode("-", $string);
                $excludedWords = array('la', 'el', 'lo', 'un', 'los', 'las', 'una', 'sus', 'su', 'de', 'del', 'a', 'ha', 'con', 'unos', 'unas', 'y', 'para', 'pero', 'le', 'cual', 'ellos', 'ellas', 'por', 'este', 'esta', 'han', 'ah', 'se', 'al', 'mas', 'nos', 'como', 'que', 'es', 'esto', 'asi', 'te', 'ya', 'en');
                $keywords = array_diff($postTags, $excludedWords);
                $keywords = implode(',', $keywords);
            }

            $post->increment('view_counter');

            $otherArticles = Post::select('id', 'title', 'category_id', 'slug', 'image')->with('categories')
                ->where('category_id', '=', $post->category_id)
                ->where('view_counter', '>', '100')
                ->where('image', '!=', null)
                ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
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

            if (count(Helper::getVideoLink($post->content))  > 0) {
                $videoLinks[] = Helper::getVideoLink($post->content);
            } else {
                $videoLinks = [];
            }

            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Post found'),
                'title' => 'Articulos - ' . $post->categories->name . ' - ' . $post->title,
                'description' => $post->excerpt,
                'path_image' => $post->image,
                'tags' => $keywords,
                'result' => $post,
                'article_video_links' => $videoLinks,
                'other_articles' => $newArticles,
                'relateds' => $relateds
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('Post not found'),
            ), 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        if (empty($id)) {
            $currentUser = Auth::user()->id;
            $data = new Post();
            $data->user_id = $currentUser;
            $data->save();
            $id = $data->id;
        }
        $post = Post::with('users', 'categories', 'titles', 'tags')->find($id);
        $categories = Category::pluck('name', 'id');
        $tags = Tag::pluck('name', 'id');
        $selected = $post->tags()->pluck('tag_id')->toArray();

        return response()->json(array(
            'code' => 200,
            'message' => 'Success',
            'title' => 'Coanime.net - Editar Post',
            'description' => 'Coanime.net - Editar Post',
            'path_posts' => 'https://coanime.net/posts/',
            'path_image' => $post->image,
            'result' => $post,
            'categories' => $categories,
            'tags' => $tags,
            'selected' => $selected,
        ), 200);
        //return view('dashboard.posts.create', compact('post', 'categories', 'tags', 'selected'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required',
            'file' => 'max:2048|mimes:jpg,jpeg,gif,bmp,png',
        ]);

        $data = Post::with('users', 'categories', 'titles', 'tags')->find($id);
        $post = $request->all();
        $currentUser = Auth::user()->id;
        $data['edited_by'] = $currentUser;

        if ($request->slug) {
            $data['slug'] = $request->slug;
        } else {
            if (Post::where('slug', 'like', $data['slug'])->count() > 0) {
                $data['slug'] = Str::slug($data['title']) . '1';
            }
            $data['slug'] = Str::slug($data['title']);
        }

        if ($request->postponed_to == "") {
            $data['postponed_to'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        $data['draft'] = 0;

        if ($data->update($post)) {
            if (!empty($request['title_id'])) {
                $data->titles()->sync([$request['title_id']]);
            }
            if (count($request['tag_id']) > 0 || $request['tag_id'] != null) {
                $tags = array();
                $tagData = array();
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

            return response()->json(array(
                'code' => 200,
                'message' => [ 'type' => 'success', 'text' => 'Post actualizado correctamente' ],
                'result' => $data,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 500,
                'message' => 'Error',
                'title' => 'Coanime.net - Editar Post',
                'description' => 'Coanime.net - Editar Post',
                'path_posts' => 'https://coanime.net/posts/',
                'path_image' => $data->image,
                'result' => $data,
            ), 500);
        }
    }

    public function approved($id, Request $request)
    {
        $post = Post::find($id);
        $post->approved = $request['approved'];
        $post->save();

        return response()->json(array(
            'code' => 200,
            'message' => 'Success!! Post aprobado',
            'title' => 'Coanime.net - Editar Post',
            'description' => 'Coanime.net - Editar Post',
            'path_posts' => 'https://coanime.net/posts/',
            'path_image' => $post->image,
            'result' => $post,
        ), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $post = Post::find($id);

        if ($post->delete()) {
            return response()->json(array(
                'code' => 200,
                'message' => 'Success!! Post Eliminado',
                'title' => 'Coanime.net - Eliminar Post',
                'description' => 'El Post fue eliminado de forma satisfactoria',
                'result' => $post,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 500,
                'message' => 'Error!! Post no Eliminado',
                'title' => 'Coanime.net - Eliminar Post',
                'description' => 'El Post no fue Eliminado',
                'result' => $post,
            ), 500);
        }
    }

    public function destroyPosts(Request $request)
    {
        $posts = Post::search($request->name)->with('users', 'categories', 'titles', 'tags')->orderBy('id', 'desc')->onlyTrashed()->paginate(20);

        return response()->json(array(
            'code' => 200,
            'message' => 'Success!!',
            'title' => 'Coanime.net - Lista de Post Eliminados',
            'description' => 'Coanime.net - Post Eliminados que aun existen en el Sistema',
            'result' => $posts,
        ), 200);
    }

    public function dashboard(Request $request)
    {
        $userId = Auth::user()->id;
        $postsCount = Post::where('user_id', $userId)->count();
        $titlesCount = Title::where('user_id', $userId)->count();
        $mostVisitedPost = Post::where('user_id', $userId)->orderBy('view_counter', 'desc')->first();
        $mostPostsCategory = Category::withCount('posts')->orderBy('posts_count', 'desc')->first();

        return response()->json(array(
            'code' => 200,
            'message' => 'success',
            'title' => 'Coanime.net - Dashboard',
            'description' => 'Dashboard',
            'posts_count' => $postsCount,
            'titles_count' => $titlesCount,
            'most_visited_post' => $mostVisitedPost,
            'most_posts_category' => $mostPostsCategory,
        ), 200);
    }


    public function changeImagesPath(Request $request)
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
        foreach($titles as $title) {
            if ($title->images) {
                $data = TitleImage::find($title->images->id);
                $data->name = str_replace('https://coanime.net/images/encyclopedia/titles/https://coanime.net/', 'https://api.coanime.net/storage/', $data->name);
                $data->name = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $data->name);
                $data->thumbnail = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $data->thumbnail);
                $data->save();
            }
        }
        foreach($users as $user) {
            $user->profile_photo_path = str_replace('https://coanime.net/', 'https://api.coanime.net/storage/', $user->profile_photo_path);
            $user->save();
        }

        return response()->json(array(
            'code' => 200,
            'message' => 'Image URL for Posts Changed Successfully!!',
        ), 200);
    }
}