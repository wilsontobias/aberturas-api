<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\UserController;
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

    // Pedidos
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:create_orders');
    Route::get('/orders/my', [OrderController::class, 'myOrders'])->middleware('permission:view_own_orders');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:view_all_orders');
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->whereNumber('id')->middleware('permission:update_item_status');
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');

    // Pagos
    Route::post('/orders/{id}/payments', [PaymentController::class, 'store'])->whereNumber('id');
    Route::get('/orders/{id}/payments', [PaymentController::class, 'index'])->whereNumber('id');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->whereNumber('id');
    Route::patch('/payments/{id}/verify', [PaymentController::class, 'verify'])->whereNumber('id')->middleware('permission:verify_payments');

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

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::patch('/users/{id}/type', [UserController::class, 'changeType']);

        Route::get('/users/{id}/permissions', [UserPermissionController::class, 'show']);
        Route::put('/users/{id}/permissions', [UserPermissionController::class, 'update']);
    });
});