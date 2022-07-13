<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Title;
use App\Models\TitleType;
use App\Models\Genre;
use App\Models\Ratings;
use App\Models\Settings;
use App\Models\Category;
use App\Models\Role;
use App\Models\Event;
use App\Models\People;
use App\Models\Magazine;
use App\Models\Company;
use App\Models\Post;
use App\Models\User;

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
     * @return \Illuminate\Http\Response
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

    // Google Analytics

    public function getAnalyticsSummary(Request $request){

        $from_date = date('Y-m-d', strtotime($request->get('from_date','7 days ago')));

        $to_date = date('Y-m-d',strtotime($request->get('to_date', $request->get('from_date','today'))));

        $gAData = $this->gASummary($from_date,$to_date);

        return $gAData;
    }

    //to get the summary of google analytics.

    private function gASummary($date_from, $date_to) {

        $service_account_email = 'analytics-api@coanime.iam.gserviceaccount.com';

        // Create and configure a new client object.

        $client = new \Google_Client();

        $client->setApplicationName('{ Coanime.net }');

        $analytics = new \Google_Service_Analytics($client);

        $cred = new \Google_Auth_AssertionCredentials($service_account_email, array(\Google_Service_Analytics::ANALYTICS_READONLY),'{344c2dd40bdb046d587beb9d43085b9c521b2e12}');

        $client->setAssertionCredentials($cred);

        if($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($cred);
        }

        $optParams = ['dimensions' => 'ga:date','sort'=>'-ga:date'];

        $results = $analytics->data_ga->get('ga:{View ID}', $date_from, $date_to,'ga:sessions,ga:users,ga:pageviews,ga:bounceRate,ga:hits,ga:avgSessionDuration', $optParams);

        $rows = $results->getRows();

        $rows_re_align = [];

        foreach($rows as $key=>$row) {
            foreach($row as $k=>$d) {
                $rows_re_align[$k][$key] = $d;
            }
        }

        $optParams = array('dimensions' => 'rt:medium');

        try {
            $results1 = $analytics->data_realtime->get('ga:{View ID}','rt:activeUsers', $optParams);
            // Success.
        } catch (apiServiceException $e) {
            // Handle API service exceptions.
            $error = $e->getMessage();
        }
        $active_users = $results1->totalsForAllResults;

        return ['data' => $rows_re_align ,'summary' => $results->getTotalsForAllResults(),'active_users' => $active_users['rt:activeUsers']];
    }
}
