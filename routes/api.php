<?php

use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
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
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');

    // Seguimiento y Envíos
    Route::patch('/order-items/{id}/status', [OrderItemController::class, 'updateStatus'])->whereNumber('id')->middleware('permission:update_item_status');
    Route::get('/orders/{id}/tracking', [OrderController::class, 'tracking'])->whereNumber('id');
    Route::patch('/orders/{id}/ship-date', [OrderController::class, 'updateShipDate'])->whereNumber('id')->middleware('permission:edit_ship_date');

    // Pagos
    Route::post('/orders/{id}/payments', [PaymentController::class, 'store'])->whereNumber('id');
    Route::get('/orders/{id}/payments', [PaymentController::class, 'index'])->whereNumber('id');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->whereNumber('id');
    Route::patch('/payments/{id}/verify', [PaymentController::class, 'verify'])->whereNumber('id')->middleware('permission:verify_payments');
    Route::post('/orders/{id}/payments/cash', [PaymentController::class, 'registerCashBalance'])->whereNumber('id')->middleware('permission:register_balance_payment');

    // Solo administradores (el SuperAdmin pasa siempre)
    Route::middleware('role:Administrator')->group(function () {
        Route::post('/localities', [LocalityController::class, 'store']);
        Route::put('/localities/{id}', [LocalityController::class, 'update'])->whereNumber('id');
        Route::delete('/localities/{id}', [LocalityController::class, 'destroy'])->whereNumber('id');

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

        Route::put('/users/{id}/admin', [AdminRoleController::class, 'grantAdmin'])->whereNumber('id');
        Route::delete('/users/{id}/admin', [AdminRoleController::class, 'revokeAdmin'])->whereNumber('id');
        Route::put('/users/{id}/owner', [AdminRoleController::class, 'grantOwner'])->whereNumber('id');
        Route::delete('/users/{id}/owner', [AdminRoleController::class, 'revokeOwner'])->whereNumber('id');

        Route::get('/users/{id}/permissions', [UserPermissionController::class, 'show']);
        Route::put('/users/{id}/permissions', [UserPermissionController::class, 'update']);
    });
});