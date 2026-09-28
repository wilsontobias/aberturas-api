<?php

use App\Http\Controllers\ProvinceController;
use Illuminate\Support\Facades\Route;

// Rutas públicas (no piden token)
Route::get('/provinces', [ProvinceController::class, 'index']);