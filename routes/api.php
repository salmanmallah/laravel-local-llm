<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::middleware('api')->group(function () {
    Route::post('/chat/send', [ChatController::class, 'sendMessage']);
    Route::match(['get', 'post'], '/chat/send-stream', [ChatController::class, 'sendStreamMessage']);
    Route::get('/chat/model-status', [ChatController::class, 'getModelStatus']);
});
