<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/datasets/upload', [DatasetController::class, 'upload']);
    Route::post('/datasets/{id}/parse', [DatasetController::class, 'parse']);
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
});

