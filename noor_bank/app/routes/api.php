<?php

use App\Http\Controllers\ReceiveController;
use Illuminate\Support\Facades\Route;

Route::post('/transfer/receive', [ReceiveController::class, 'receive']);

// Routes pour la rotation des clés
Route::post('/keys/update', [App\Http\Controllers\KeyUpdateController::class, 'update']);
Route::get('/keys/status', [App\Http\Controllers\KeyUpdateController::class, 'status']);
