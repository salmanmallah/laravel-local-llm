<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('index');
});

// API Routes for Chat
Route::post('/api/chat/send', [ChatController::class, 'sendMessage']);
Route::get('/api/chat/model-status', [ChatController::class, 'getModelStatus']);
