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
        'base_url' => $baseUrl,
        'rate_limiting' => [
            'api' => '60 requests per minute',
            'login' => '5 attempts per email/IP',
            'note' => 'Rate limits are applied per IP address or authenticated user',
        ],
        'authentication' => [
            'type' => 'JWT Token Authentication',
            'header' => 'Authorization: Bearer {token}',
            'note' => 'Internal routes require authentication. Use /login to obtain a token. Tokens expire after 60 minutes by default.',
            'login_response' => [
                'access_token' => 'JWT token string',
                'token_type' => 'bearer',
                'expires_in' => 'Token expiration time in seconds',
                'user' => 'Authenticated user object with roles',
            ],
        ],
        'response_format' => [
            'success' => [
                'code' => 200,
                'message' => 'Success message',
                'data' => 'Response data',
            ],
            'error' => [
                'code' => 'HTTP status code (400, 404, 500, etc.)',
                'message' => 'Error message',
                'error' => 'Detailed error information',
            ],
        ],
        'common_query_parameters' => [
            'name' => 'Search query parameter (string)',
            'category' => 'Category slug filter (string)',
            'page' => 'Pagination page number (integer)',
            'per_page' => 'Items per page (integer, default varies)',
        ],
        'documentation' => [
            'authentication' => [
                'login' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/login',
                    'description' => 'Authenticate user and receive JWT access token',
                    'parameters' => ['email' => 'string (required)', 'password' => 'string (required)', 'remember' => 'boolean (optional)'],
                    'response' => [
                        'access_token' => 'JWT token string',
                        'token_type' => 'bearer',
                        'expires_in' => 'Token expiration time in seconds (default: 3600)',
                        'user' => 'Authenticated user object with roles',
                    ],
                ],
                'register' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/register',
                    'description' => 'Register a new user account and receive JWT access token',
                    'parameters' => [
                        'name' => 'string (required, max:255)',
                        'email' => 'string (required, unique)',
                        'password' => 'string (required, min:8, mixed case, numbers, symbols)',
                        'password_confirmation' => 'string (required)',
                    ],
                    'response' => [
                        'access_token' => 'JWT token string',
                        'token_type' => 'bearer',
                        'expires_in' => 'Token expiration time in seconds (default: 3600)',
                        'user' => 'Newly registered user object with roles',
                    ],
                ],
                'logout' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/logout',
                    'description' => 'Logout authenticated user',
                    'auth_required' => true,
                ],
                'forgot_password' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/forgot-password',
                    'description' => 'Request password reset email',
                    'parameters' => ['email' => 'string (required)'],
                ],
                'reset_password' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/reset-password',
                    'description' => 'Reset user password with token',
                ],
                'verify_email' => [
                    'method' => 'GET',
                    'url' => $baseUrl . '/verify-email/{id}/{hash}',
                    'description' => 'Verify user email address',
                    'auth_required' => true,
                ],
                'send_verification' => [
                    'method' => 'POST',
                    'url' => $baseUrl . '/email/verification-notification',
                    'description' => 'Resend email verification',
                    'auth_required' => true,
                ],
            ],
            'external' => [
                'note' => 'All external endpoints are publicly accessible without authentication',
                'home' => [
                    'home' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/home',
                        'description' => 'Get homepage data including relevant articles, news, broadcast schedule, and upcoming titles',
                        'query_params' => ['category' => 'string (optional)', 'name' => 'string (optional)'],
                    ],
                    'get_titles' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/get-titles',
                        'description' => 'Get all titles',
                    ],
                    'change_images_path' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/change-images-path',
                        'description' => 'Utility endpoint to migrate image paths (admin use)',
                    ],
                ],
                'articles' => [
                    'list' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/articles',
                        'description' => 'Get paginated list of articles',
                        'query_params' => ['category' => 'string (optional)', 'name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'list_japan' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/articles-japan',
                        'description' => 'Get articles related to Japan',
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'show' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/articles/{slug}',
                        'description' => 'Get single article by slug',
                    ],
                    'by_category' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/categories/articles/{category}',
                        'description' => 'Get articles filtered by category slug',
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'by_tag' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/articles/tags/{tag}',
                        'description' => 'Get articles filtered by tag slug',
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'search' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/posts-search',
                        'description' => 'Search articles',
                        'query_params' => ['name' => 'string (required)', 'category' => 'integer (optional)', 'page' => 'integer (optional)'],
                    ],
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
                    'list' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/titles',
                        'description' => 'Get paginated list of all titles',
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'upcoming' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/titles/upcoming',
                        'description' => 'Get list of upcoming titles',
                    ],
                    'by_type' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/titles/{type}',
                        'description' => 'Get titles filtered by type (anime, manga, etc.)',
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'show' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/titles/{type}/{slug}',
                        'description' => 'Get single title details by type and slug',
                    ],
                    'posts' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/titles/{type}/{slug}/posts',
                        'description' => 'Get posts related to a specific title',
                    ],
                    'animes' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/animes',
                        'description' => 'Get anime titles from external API',
                    ],
                    'user_title_list' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/user/title-list',
                        'description' => 'Get user\'s title list',
                    ],
                    'update_statistics' => [
                        'method' => 'POST',
                        'url' => $baseUrl . '/external/titles/{title_id}/{statistics_id}/stats',
                        'description' => 'Update title statistics',
                    ],
                    'update_rates' => [
                        'method' => 'POST',
                        'url' => $baseUrl . '/external/titles/{title_id}/{rate_id}/rates',
                        'description' => 'Update title rating',
                    ],
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
                    'random_image' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/random-image',
                        'description' => 'Get a random post image',
                        'query_params' => ['width' => 'integer (optional, default: 1920)'],
                    ],
                    'random_image_by_title' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/random-image-title/{slug}',
                        'description' => 'Get a random image for a specific title',
                    ],
                    'ecma' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/ecma',
                        'description' => 'Get encyclopedia data',
                    ],
                    'vote' => [
                        'method' => 'POST',
                        'url' => $baseUrl . '/external/vote',
                        'description' => 'Vote on a post',
                    ],
                    'add_titles_season' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/add-titles-season',
                        'description' => 'Add titles by season (admin utility)',
                    ],
                    'add_titles_alphabetic' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/external/add-titles-alphabetic',
                        'description' => 'Add titles alphabetically (admin utility)',
                    ],
                ],
            ],
            'internal' => [
                '_note' => 'All internal endpoints require authentication (Bearer token)',
                'dashboard' => [
                    'method' => 'GET',
                    'url' => $baseUrl . '/internal/dashboard',
                    'description' => 'Get dashboard statistics for authenticated user',
                    'auth_required' => true,
                ],
                'posts' => [
                    'list' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/internal/posts',
                        'description' => 'Get paginated list of posts (dashboard view)',
                        'auth_required' => true,
                        'query_params' => ['name' => 'string (optional)', 'page' => 'integer (optional)'],
                    ],
                    'show' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/internal/posts/{id}',
                        'description' => 'Get single post for editing',
                        'auth_required' => true,
                    ],
                    'create' => [
                        'method' => 'POST',
                        'url' => $baseUrl . '/internal/posts',
                        'description' => 'Create a new post',
                        'auth_required' => true,
                        'body_params' => [
                            'title' => 'string (required, max:255)',
                            'excerpt' => 'string (required, max:255)',
                            'content' => 'string (required)',
                            'category_id' => 'integer (required)',
                            'image' => 'string (required, max:255)',
                            'postponed_to' => 'datetime (optional, format: Y-m-d H:i:s)',
                            'tag_id' => 'array (optional)',
                            'title_id' => 'integer (optional)',
                        ],
                    ],
                    'update' => [
                        'method' => 'PUT',
                        'url' => $baseUrl . '/internal/posts/{id}',
                        'description' => 'Update existing post',
                        'auth_required' => true,
                        'body_params' => [
                            'title' => 'string (required, max:255)',
                            'content' => 'string (required)',
                            'category_id' => 'integer (required)',
                            'file' => 'file (optional, max:2048, mimes:jpg,jpeg,gif,bmp,png)',
                        ],
                    ],
                    'delete' => [
                        'method' => 'PUT',
                        'url' => $baseUrl . '/internal/posts/{id}/delete',
                        'description' => 'Soft delete a post',
                        'auth_required' => true,
                    ],
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
                    'images' => [
                        'method' => 'POST',
                        'url' => $baseUrl . '/internal/upload-images',
                        'description' => 'Upload image to S3 storage',
                        'auth_required' => true,
                        'body_params' => [
                            'model' => 'string (required)',
                            'file' => 'file (required, image, max:2048, mimes:jpeg,png,jpg,gif,svg,webp)',
                        ],
                    ],
                ],
            ],
            'api' => [
                '_note' => 'Routes under /api prefix',
                'user' => [
                    'method' => 'GET',
                    'url' => $baseUrl . '/api/user',
                    'description' => 'Get authenticated user information with roles',
                    'auth_required' => true,
                    'header' => 'Authorization: Bearer {token}',
                ],
                'activity_logs' => [
                    'list' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/api/external/activity-logs',
                        'description' => 'Get paginated list of activity logs',
                        'query_params' => ['page' => 'integer (optional)', 'per_page' => 'integer (optional)'],
                    ],
                    'stats' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/api/external/activity-logs/stats',
                        'description' => 'Get activity logs statistics',
                    ],
                    'show' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/api/external/activity-logs/{id}',
                        'description' => 'Get single activity log by ID',
                    ],
                    'by_user' => [
                        'method' => 'GET',
                        'url' => $baseUrl . '/api/external/activity-logs/user/{userId}',
                        'description' => 'Get activity logs for a specific user',
                    ],
                ],
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