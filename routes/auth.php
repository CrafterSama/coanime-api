<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\EncyclopediaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\MagazineController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PostVoteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
                ->middleware(['auth', 'signed', 'throttle:6,1'])
                ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware(['auth', 'throttle:6,1'])
                ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth')
                ->name('logout');

Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

// ** Api Endpoints **
Route::prefix('external/')->group(function () {
  Route::get('home', [PostController::class, 'index'])->name('home');
  Route::get('get-titles', [TitleController::class, 'getAllTitles']);
  Route::get('change-images-path', [PostController::class, 'changeImagesPath']);

  // ** Get Endpoints **
  Route::get('articles', [PostController::class, 'posts'])->name('api.articles');  
  Route::get('articles/{slug}', [PostController::class, 'showApi'])->name('api.articles.show');

  Route::get('categories', [PostController::class, 'categories'])->name('api.categories');
  Route::get('categories/{category}', [PostController::class, 'showAllByCategory'])->name('api.home.category');
  Route::get('categories/articles/{category}', [PostController::class, 'postsByCategory'])->name('api.articles.category');

  Route::get('tags/{tag}', [PostController::class, 'ShowAllByTag'])->name('api.articles.tag');

  Route::get('ecma', [EncyclopediaController::class, 'index']);

  Route::get('titles', [TitleController::class, 'apiTitles']);
  Route::get('titles/{type}', [TitleController::class, 'apiTitlesByType']);
  Route::get('titles/{type}/{slug}', [TitleController::class, 'apiShowTitle']);
  Route::get('titles/{type}/{slug}/posts', [TitleController::class, 'postsTitle']);

  Route::get('search/titles/{name}', [TitleController::class, 'apiSearchTitles']);
  Route::get('search/people/{name}', [PeopleController::class, 'apiIndex']);
  Route::get('search/magazine/{name}', [MagazineController::class, 'apiIndex']);
  Route::get('search/companies/{name}', [CompanyController::class, 'apiIndex']);

  Route::get('genres/{slug}', [TitleController::class, 'apiAllByGenre']);

  Route::get('events', [EventController::class, 'index']);
  Route::get('events/{slug}', [EventController::class, 'apiShow']);
  
  Route::get('people', [PeopleController::class, 'apiIndex']);
  Route::get('people/{slug}', [PeopleController::class, 'apiShow']);

  Route::get('magazine', [MagazineController::class, 'apiIndex']);
  Route::get('magazine/{slug}', [MagazineController::class, 'apiShow']);

  Route::get('companies', [CompanyController::class, 'apiIndex']);
  Route::get('companies/{slug}', [CompanyController::class, 'apiShow']);

  Route::get('profile/{slug}', [UserController::class, 'apiProfile']);
  Route::get('profile/{id}/posts', [UserController::class, 'postsProfile']);
  Route::get('profile/{id}/titles', [UserController::class, 'titlesProfile']);
  Route::get('profile/{id}/companies', [UserController::class, 'companiesProfile']);
  Route::get('profile/{id}/magazine', [UserController::class, 'magazineProfile']);
  Route::get('profile/{id}/people', [UserController::class, 'peopleProfile']);
  Route::get('profile/{id}/events', [UserController::class, 'eventsProfile']);

  Route::get('random-image', [PostController::class, 'getRandomPostImage']);
  Route::get('random-image-title/{slug}', [PostController::class, 'getRandomPostImageByTitle']);

  // ** Posts Endpoints **

  Route::post('vote', [PostVoteController::class, 'vote']);
});

Route::middleware(['auth'])->group(function () {
  Route::prefix('internal/')->group(function () {
    // ** Auth Posts Endpoints **
    Route::get('posts', [PostController::class, 'posts'])->name('posts');
    Route::get('posts-dashboard', [PostController::class, 'postsDashboard'])->name('posts-dashboard');
    Route::get('posts/{id}', [PostController::class, 'show']);
    Route::put('posts/{id}', [PostController::class, 'update']);
    Route::post('posts', [PostController::class, 'store']);
    
    // ** Auth Upload Image Endpoints **
    Route::post('upload-images', [ImageController::class, 'store']);

    // ** Auth Titles Endpoints **
    Route::get('titles', [TitleController::class, 'index'])->name('titles');
    Route::get('titles/create', [TitleController::class, 'create']);
    Route::get('titles/{id}', [TitleController::class, 'show']);
    Route::put('titles/{id}', [TitleController::class, 'update']);
    Route::post('titles', [TitleController::class, 'store']);
    
    // ** Auth People Endpoints **
    Route::get('people', [PeopleController::class, 'index'])->name('people');
    Route::get('people/{id}', [PeopleController::class, 'show']);
    Route::put('people/{id}', [PeopleController::class, 'update']);
    Route::post('people', [PeopleController::class, 'store']);
    
    // ** Auth Magazine Endpoints **
    Route::get('magazine', [MagazineController::class, 'index'])->name('magazine');
    Route::get('magazine/{id}', [MagazineController::class, 'show']);
    Route::put('magazine/{id}', [MagazineController::class, 'update']);
    Route::post('magazine', [MagazineController::class, 'store']);
    
    // ** Auth Events Endpoints **
    Route::get('events', [EventController::class, 'index'])->name('events');
    Route::get('events/{id}', [EventController::class, 'show']);
    Route::put('events/{id}', [EventController::class, 'show']);
    Route::post('events', [EventController::class, 'store']);
    
    // ** Auth Company Endpoints **
    Route::get('companies', [CompanyController::class, 'index'])->name('companies');
    Route::get('companies/{id}', [CompanyController::class, 'show']);
    Route::put('companies/{id}', [CompanyController::class, 'update']);
    Route::post('companies', [CompanyController::class, 'store']);

    // ** Auth User Endpoints **
    Route::get('users', [UserController::class, 'index'])->name('users');
    Route::get('me', [UserController::class, 'me'])->name('me');
    Route::put('me', [UserController::class, 'updateMe'])->name('me.update');
  });
});