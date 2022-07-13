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

Route::middleware('guest')->group(function () {
  Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
  Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
  Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
  Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
  
  // ** Api Endpoints
  Route::prefix('/api/v1/')->group(function () {
    // ** Get Endpoints 
    Route::get('home', [PostController::class, 'apiPosts'])->name('home');

    Route::get('article/{slug}', [PostController::class, 'showApi']);
    Route::get('articles', [PostController::class, 'posts']);
    Route::get('articles/{category}', [PostController::class, 'postsByCategory']);
    Route::get('articles/{tag}', [PostController::class, 'postsByTag']);

    Route::get('ecma', [EncyclopediaController::class, 'api']);

    Route::get('titles', [TitleController::class, 'apiTitles']);
    Route::get('titles/{type}', [TitleController::class, 'apiTitlesByType']);
    Route::get('titles/{type}/{slug}', [TitleController::class, 'apiShowTitle']);
    Route::get('titles/{type}/{slug}/posts', [TitleController::class, 'postsTitle']);

    Route::get('search/titles/{name}', [TitleController::class, 'apiSearchTitles']);
    Route::get('search/people/{name}', [PeopleController::class, 'apiIndex']);
    Route::get('search/magazine/{name}', [MagazineController::class, 'apiIndex']);
    Route::get('search/companies/{name}', [CompanyController::class, 'apiIndex']);

    Route::get('genres/{slug}', [TitleController::class, 'apiAllByGenre']);

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

    //** Posts Endpoints

    Route::post('post-image-upload', [PostController::class, 'imageUpload']);
    Route::post('vote', [PostVoteController::class, 'vote']);
  });
});