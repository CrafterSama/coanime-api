<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware(['auth'])->group(function () {
    Route::get('/user', [UserController::class, 'user']);
});*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('roles');
});

JsonApiRoute::server('v1')->prefix('external')->resources(function ($server) {
    $server->resource('posts', JsonApiController::class)->readOnly();
    $server->resource('users', JsonApiController::class)->readOnly();
    $server->resource('tags', JsonApiController::class)->readOnly();
    $server->resource('categories', JsonApiController::class)->readOnly();
    $server->resource('titles', JsonApiController::class)->readOnly();
});