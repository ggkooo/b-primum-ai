<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'Primum AI API is running',
        'version' => '1.0.0',
        'message' => 'Welcome to the health diagnosis API.'
    ]);
});
