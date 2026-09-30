<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\ProvinceController;
use Illuminate\Support\Facades\Route;

// Rutas públicas (no piden token)
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/localities', [LocalityController::class, 'index']);
Route::get('/localities/{id}', [LocalityController::class, 'show']);

// Autenticación (públicas)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (piden token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});