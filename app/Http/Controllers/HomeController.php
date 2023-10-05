<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\Ratings;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Title;
use App\Models\TitleType;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $events = Event::with('users')->orderBy('id', 'desc')->paginate(9);
        $titles = Title::with('images', 'rating', 'type', 'genres')->orderBy('id', 'desc')->paginate(6);
        $people = People::with('users')->orderBy('id', 'desc')->paginate(8);
        $magazine = Magazine::with('type', 'image', 'release', 'users')->orderBy('id', 'desc')->paginate(6);
        $companies = Company::with('users')->orderBy('id', 'desc')->paginate(8);
        $posts = Post::with('users', 'categories', 'titles')->where('draft', '0')->where('approved', 'yes')->orderBy('postponed_to', 'desc')->simplePaginate(12);

        return view('dashboard.home', compact('titles', 'people', 'magazine', 'companies', 'events', 'posts'));
    }

    public function posts(Request $request)
    {
        $posts = Post::search($request->name)->with('users', 'categories', 'titles', 'tags')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.posts.home', compact('posts'));
    }

    public function titles(Request $request)
    {
        $titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.titles.home', compact('titles'));
    }

    public function people(Request $request)
    {
        $people = People::search($request->name)->with('users')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.people.home', compact('people'));
    }

    public function magazine(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'users')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.magazine.home', compact('magazine'));
    }

    public function companies(Request $request)
    {
        $companies = Company::search($request->name)->with('users')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.companies.home', compact('companies'));
    }

    public function events(Request $request)
    {
        $events = Event::search($request->name)->with('users')->orderBy('id', 'desc')->paginate(20);

        return view('dashboard.events.home', compact('events'));
    }

    public function settings()
    {
        $settings = Settings::all();
        $types = TitleType::all();
        $genres = Genre::all();
        $roles = Role::all();
        $ratings = Ratings::all();
        $categories = Category::all();

        return view('dashboard.settings.home', compact('types', 'genres', 'settings', 'roles', 'ratings', 'categories'));
    }
}
