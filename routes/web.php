<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $baseUrl = url('/');
    
    return response()->json([
        'message' => 'Welcome to the Coanime.net API',
        'status' => 'online',
        'version' => '1.0.0',
        'documentation' => [
            'authentication' => [
                'login' => $baseUrl . '/login',
                'register' => $baseUrl . '/register',
                'logout' => $baseUrl . '/logout',
                'forgot_password' => $baseUrl . '/forgot-password',
                'reset_password' => $baseUrl . '/reset-password',
                'verify_email' => $baseUrl . '/verify-email/{id}/{hash}',
                'send_verification' => $baseUrl . '/email/verification-notification',
            ],
            'external' => [
                'home' => [
                    'home' => $baseUrl . '/external/home',
                    'get_titles' => $baseUrl . '/external/get-titles',
                    'change_images_path' => $baseUrl . '/external/change-images-path',
                ],
                'articles' => [
                    'list' => $baseUrl . '/external/articles',
                    'list_japan' => $baseUrl . '/external/articles-japan',
                    'show' => $baseUrl . '/external/articles/{slug}',
                    'by_category' => $baseUrl . '/external/categories/articles/{category}',
                    'by_tag' => $baseUrl . '/external/articles/tags/{tag}',
                    'search' => $baseUrl . '/external/posts-search',
                ],
                'categories' => [
                    'list' => $baseUrl . '/external/categories',
                    'show' => $baseUrl . '/external/categories/{category}',
                ],
                'tags' => [
                    'home_by_tag' => $baseUrl . '/external/home/tags/{tag}',
                    'articles_by_tag' => $baseUrl . '/external/articles/tags/{tag}',
                ],
                'titles' => [
                    'list' => $baseUrl . '/external/titles',
                    'upcoming' => $baseUrl . '/external/titles/upcoming',
                    'by_type' => $baseUrl . '/external/titles/{type}',
                    'show' => $baseUrl . '/external/titles/{type}/{slug}',
                    'posts' => $baseUrl . '/external/titles/{type}/{slug}/posts',
                    'animes' => $baseUrl . '/external/animes',
                    'user_title_list' => $baseUrl . '/external/user/title-list',
                    'update_statistics' => $baseUrl . '/external/titles/{title_id}/{statistics_id}/stats',
                    'update_rates' => $baseUrl . '/external/titles/{title_id}/{rate_id}/rates',
                ],
                'statistics' => [
                    'by_user' => $baseUrl . '/external/statistics/{title}/{user}',
                    'rates_by_user' => $baseUrl . '/external/rates/{title}/{user}',
                ],
                'search' => [
                    'titles' => $baseUrl . '/external/search/titles/{name}',
                    'people' => $baseUrl . '/external/search/people/{name}',
                    'magazine' => $baseUrl . '/external/search/magazine/{name}',
                    'companies' => $baseUrl . '/external/search/companies/{name}',
                ],
                'genres' => [
                    'list' => $baseUrl . '/external/genres',
                    'show' => $baseUrl . '/external/genres/{slug}',
                ],
                'events' => [
                    'list' => $baseUrl . '/external/events',
                    'by_country' => $baseUrl . '/external/events/country/{slug}',
                    'show' => $baseUrl . '/external/events/{slug}',
                ],
                'people' => [
                    'list' => $baseUrl . '/external/people',
                    'by_country' => $baseUrl . '/external/people/country/{slug}',
                    'show' => $baseUrl . '/external/people/{slug}',
                ],
                'magazine' => [
                    'list' => $baseUrl . '/external/magazine',
                    'by_demography' => $baseUrl . '/external/magazine/demography/{slug}',
                    'show' => $baseUrl . '/external/magazine/{slug}',
                ],
                'companies' => [
                    'list' => $baseUrl . '/external/companies',
                    'by_country' => $baseUrl . '/external/companies/country/{slug}',
                    'show' => $baseUrl . '/external/companies/{slug}',
                ],
                'profile' => [
                    'show' => $baseUrl . '/external/profile/{slug}',
                    'posts' => $baseUrl . '/external/profile/{id}/posts',
                    'titles' => $baseUrl . '/external/profile/{id}/titles',
                    'companies' => $baseUrl . '/external/profile/{id}/companies',
                    'magazine' => $baseUrl . '/external/profile/{id}/magazine',
                    'people' => $baseUrl . '/external/profile/{id}/people',
                    'events' => $baseUrl . '/external/profile/{id}/events',
                ],
                'utilities' => [
                    'random_image' => $baseUrl . '/external/random-image',
                    'random_image_by_title' => $baseUrl . '/external/random-image-title/{slug}',
                    'ecma' => $baseUrl . '/external/ecma',
                    'vote' => $baseUrl . '/external/vote',
                    'add_titles_season' => $baseUrl . '/external/add-titles-season',
                    'add_titles_alphabetic' => $baseUrl . '/external/add-titles-alphabetic',
                ],
            ],
            'internal' => [
                '_note' => 'Requires authentication',
                'dashboard' => $baseUrl . '/internal/dashboard',
                'posts' => [
                    'list' => $baseUrl . '/internal/posts',
                    'show' => $baseUrl . '/internal/posts/{id}',
                    'create' => $baseUrl . '/internal/posts',
                    'update' => $baseUrl . '/internal/posts/{id}',
                    'delete' => $baseUrl . '/internal/posts/{id}/delete',
                ],
                'titles' => [
                    'list' => $baseUrl . '/internal/titles',
                    'create_form' => $baseUrl . '/internal/titles/create',
                    'show' => $baseUrl . '/internal/titles/{id}',
                    'create' => $baseUrl . '/internal/titles',
                    'update' => $baseUrl . '/internal/titles/{id}',
                ],
                'people' => [
                    'list' => $baseUrl . '/internal/people',
                    'show' => $baseUrl . '/internal/people/{id}',
                    'create' => $baseUrl . '/internal/people',
                    'update' => $baseUrl . '/internal/people/{id}',
                ],
                'magazine' => [
                    'list' => $baseUrl . '/internal/magazine',
                    'show' => $baseUrl . '/internal/magazine/{id}',
                    'create' => $baseUrl . '/internal/magazine',
                    'update' => $baseUrl . '/internal/magazine/{id}',
                ],
                'events' => [
                    'list' => $baseUrl . '/internal/events',
                    'show' => $baseUrl . '/internal/events/{id}',
                    'create' => $baseUrl . '/internal/events',
                    'update' => $baseUrl . '/internal/events/{id}',
                ],
                'companies' => [
                    'list' => $baseUrl . '/internal/companies',
                    'show' => $baseUrl . '/internal/companies/{id}',
                    'create' => $baseUrl . '/internal/companies',
                    'update' => $baseUrl . '/internal/companies/{id}',
                ],
                'users' => [
                    'list' => $baseUrl . '/internal/users',
                    'me' => $baseUrl . '/internal/me',
                    'update_me' => $baseUrl . '/internal/me',
                ],
                'upload' => [
                    'images' => $baseUrl . '/internal/upload-images',
                ],
            ],
            'api' => [
                '_note' => 'Routes under /api prefix',
                'user' => $baseUrl . '/api/user',
                'jsonapi' => [
                    '_note' => 'JSON:API specification endpoints',
                    'posts' => $baseUrl . '/api/external/posts',
                    'users' => $baseUrl . '/api/external/users',
                    'tags' => $baseUrl . '/api/external/tags',
                    'categories' => $baseUrl . '/api/external/categories',
                    'titles' => $baseUrl . '/api/external/titles',
                ],
            ],
        ],
    ]);
});

require __DIR__.'/auth.php';
