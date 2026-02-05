<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\ItineraryDayController;
use App\Http\Controllers\ItineraryItemController;
use App\Http\Controllers\TripForkController;
use App\Http\Controllers\TripPostController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\AiPlanController;


Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/trips', [TripController::class, 'index']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::get('/trips/{trip}', [TripController::class, 'show']);
    Route::put('/trips/{trip}', [TripController::class, 'update']);
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']);

    Route::post('/trips/{trip}/days', [ItineraryDayController::class, 'store']);
    Route::put('/days/{day}', [ItineraryDayController::class, 'update']);
    Route::delete('/days/{day}', [ItineraryDayController::class, 'destroy']);

    Route::post('/days/{day}/items', [ItineraryItemController::class, 'store']);
    Route::put('/items/{item}', [ItineraryItemController::class, 'update']);
    Route::delete('/items/{item}', [ItineraryItemController::class, 'destroy']);

    Route::post('/trips/{trip}/fork', [TripForkController::class, 'store']);

    Route::get('/posts', [TripPostController::class, 'index']);
    Route::post('/posts', [TripPostController::class, 'store']);
    Route::get('/posts/{post}', [TripPostController::class, 'show']);
    Route::delete('/posts/{post}', [TripPostController::class, 'destroy']);

    Route::post('/posts/{post}/like', [LikeController::class, 'toggle']);

    Route::post('/ai/plan', [AiPlanController::class, 'generate']);

    Route::post('/ai/plan-and-apply', [AiPlanController::class, 'planAndApply']);
});