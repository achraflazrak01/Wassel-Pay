<?php

use App\Http\Controllers\TransferController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
Route::post('/transfers/store', [TransferController::class, 'store'])->name('transfers.store');
Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
Route::get('/transfers/{uuid}', [TransferController::class, 'show'])->name('transfers.show');

// API routes
Route::post('/api/transfer/send', [TransferController::class, 'send']);
Route::get('/api/transfer/status/{uuid}', [TransferController::class, 'status']);
Route::get('/api/stats', [DashboardController::class, 'stats']);

// Routes Crypto Dashboard
Route::get('/crypto', [App\Http\Controllers\CryptoDashboardController::class, 'index'])->name('crypto.dashboard');
Route::post('/crypto/force-rotation', [App\Http\Controllers\CryptoDashboardController::class, 'forceRotation'])->name('crypto.force-rotation');
Route::get('/crypto/download-share/{role}', [App\Http\Controllers\CryptoDashboardController::class, 'downloadShare'])->name('crypto.download-share');
Route::post('/crypto/split-key', [App\Http\Controllers\CryptoDashboardController::class, 'splitKey'])->name('crypto.split-key');
Route::post('/crypto/verify-shares', [App\Http\Controllers\CryptoDashboardController::class, 'verifyShares'])->name('crypto.verify-shares');
