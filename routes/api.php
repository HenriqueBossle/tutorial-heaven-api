<?php

use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');


Route::prefix('v1')->group(function () {
    Route::get('/tags/steps', [TagController::class, 'getFormSteps']);
    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('articles', ArticleController::class)
            ->only(['index', 'show']);

        Route::apiResource('articles', ArticleController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('can:create,App\Models\Article');
    });
});