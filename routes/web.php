<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\UserController;

Route::view('/', 'app');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::prefix('api')->middleware('auth')->group(function (): void {
    Route::get('/session', [AuthController::class, 'session']);
    Route::get('/dashboard', DashboardController::class);

    Route::get('/products', [InventoryController::class, 'products']);
    Route::middleware('role:ADMIN,ADMIN_BARANG')->group(function (): void {
        Route::post('/products', [InventoryController::class, 'storeProduct']);
        Route::put('/products/{product}', [InventoryController::class, 'updateProduct']);
        Route::delete('/products/{product}', [InventoryController::class, 'deleteProduct']);
        Route::post('/products/{product}/stock', [InventoryController::class, 'adjustStock']);

        Route::get('/categories', [InventoryController::class, 'categories']);
        Route::post('/categories', [InventoryController::class, 'storeCategory']);
        Route::put('/categories/{category}', [InventoryController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [InventoryController::class, 'deleteCategory']);

        Route::get('/distributors', [InventoryController::class, 'distributors']);
        Route::post('/distributors', [InventoryController::class, 'storeDistributor']);
        Route::put('/distributors/{distributor}', [InventoryController::class, 'updateDistributor']);
        Route::delete('/distributors/{distributor}', [InventoryController::class, 'deleteDistributor']);
    });

    Route::middleware('role:ADMIN,KASIR')->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/sales', [SalesController::class, 'index']);
        Route::post('/sales', [SalesController::class, 'store']);
        Route::get('/reports', ReportController::class);
    });

    Route::middleware('role:ADMIN')->group(function (): void {
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
        Route::delete('/sales/{sale}', [SalesController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/inventory-logs', [InventoryLogController::class, 'index']);
    });
});
