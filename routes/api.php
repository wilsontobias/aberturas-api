<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

// Rutas públicas (no piden token)
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/localities', [LocalityController::class, 'index']);
Route::get('/localities/{id}', [LocalityController::class, 'show']);
Route::get('/categories', [ProductCategoryController::class, 'index']);
Route::get('/materials', [MaterialController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// Autenticación (públicas)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (piden token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Solo administradores (el SuperAdmin pasa siempre)
    Route::middleware('role:Administrator')->group(function () {
        Route::post('/categories', [ProductCategoryController::class, 'store']);
        Route::put('/categories/{id}', [ProductCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [ProductCategoryController::class, 'destroy']);

        Route::post('/materials', [MaterialController::class, 'store']);
        Route::put('/materials/{id}', [MaterialController::class, 'update']);
        Route::delete('/materials/{id}', [MaterialController::class, 'destroy']);

        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
                Route::patch('/products/{id}/stock', [ProductController::class, 'updateStock']);

        Route::get('/users/{id}/permissions', [UserPermissionController::class, 'show']);
        Route::put('/users/{id}/permissions', [UserPermissionController::class, 'update']);
    });
});