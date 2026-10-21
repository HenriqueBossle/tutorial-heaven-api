<?php

use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return response()->json((new UserResource($request->user()->load('tags')))->toArray($request));
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::get('/tags/steps', [TagController::class, 'getFormSteps']);
Route::get('/tags', [TagController::class, 'index']);
Route::post('/tags', [TagController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class)
        ->only(['index', 'show', 'update', 'destroy']);

    Route::apiResource('articles', ArticleController::class)
        ->only(['index', 'show']);

    Route::apiResource('articles', ArticleController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('can:create,App\Models\Article');

    Route::get('/users/{id}/tags', [TagController::class, 'show']);

    Route::post('/onboarding/tags', [OnboardingController::class, 'syncTags']);
});
