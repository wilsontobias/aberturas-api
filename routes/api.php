<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\ProvinceController;
use Illuminate\Support\Facades\Route;

// Rutas públicas (no piden token)
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/localities', [LocalityController::class, 'index']);
Route::get('/localities/{id}', [LocalityController::class, 'show']);

// Autenticación
Route::post('/register', [AuthController::class, 'register']);