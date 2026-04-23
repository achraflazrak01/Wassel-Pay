<?php

use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/transfer/send', [TransferController::class, 'send']);
Route::get('/transfer/status/{uuid}', [TransferController::class, 'status']);

// Routes pour la rotation des clés
Route::post('/keys/update', [App\Http\Controllers\KeyUpdateController::class, 'update']);
Route::get('/keys/status', [App\Http\Controllers\KeyUpdateController::class, 'status']);
