<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\ChatMessageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Diario
    Route::get('/diary',          [DiaryEntryController::class, 'index']);
    Route::post('/diary',         [DiaryEntryController::class, 'store']);
    Route::get('/diary/{entry}',  [DiaryEntryController::class, 'show']);
    Route::delete('/diary/{entry}', [DiaryEntryController::class, 'destroy']);

    // Chat
    Route::get('/chat',  [ChatMessageController::class, 'index']);
    Route::post('/chat', [ChatMessageController::class, 'sendMessage']);
});
