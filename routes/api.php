<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [ChatbotController::class, 'handleMessage'])->middleware(['web', 'auth', 'throttle:30,1']);
