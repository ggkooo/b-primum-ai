<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DatasetController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/datasets/upload', [DatasetController::class, 'upload']);
Route::post('/datasets/{id}/parse', [DatasetController::class, 'parse']);

