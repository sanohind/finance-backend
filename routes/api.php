<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Api\Admin\SuperAdminInvHeaderController;
use App\Http\Controllers\Api\Admin\SuperAdminInvLineController;
use App\Http\Controllers\Api\Admin\SuperAdminInvDocumentController;
use App\Http\Controllers\Api\Finance\FinanceDashboardController;
use App\Http\Controllers\Api\Finance\FinanceInvHeaderController;
use App\Http\Controllers\Api\Finance\FinanceInvLineController;
use App\Http\Controllers\Api\Finance\FinanceInvDocumentController;
use App\Http\Controllers\Api\SupplierFinance\SupplierDashboardController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvHeaderController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvLineController;
use App\Http\Controllers\Api\Local2\LocalDataController;

Route::post('/login', [AuthController::class, 'login']);

// Route for sync data from second database
Route::get('local2/sync-inv-line', [LocalDataController::class, 'syncInvLine'])->middleware('auth:sanctum');

// Admin routes
Route::middleware(['auth:sanctum', 'userRole:1'])->prefix('super-admin')->group(function () {
    // Dashboard
    Route::get('dashboard', [SuperAdminDashboardController::class, 'dashboard']);

    // Route for sync data from second database
    Route::get('sync-inv-line', [LocalDataController::class, 'syncInvLine']);
    Route::get('business-partners', [UserController::class, 'getBusinessPartner']);

    // User management
    Route::get('index', [UserController::class, 'index']);
    Route::post('store', [UserController::class, 'store']);
    Route::get('edit/{id}', [UserController::class, 'edit']);
    Route::put('update/{id}', [UserController::class, 'update']);
    Route::delete('delete/{id}', [UserController::class, 'destroy']);
    Route::put('status/{id}/{status}', [UserController::class, 'updateStatus']);

    // Invoice management
    Route::get('inv-header', [SuperAdminInvHeaderController::class, 'getInvHeader']);
    Route::get('inv-header/bp-code/{bp_code}', [SuperAdminInvHeaderController::class, 'getInvHeaderByBpCode']);
    Route::post('inv-header/store', [SuperAdminInvHeaderController::class, 'store']);
    Route::put('inv-header/{inv_no}', [SuperAdminInvHeaderController::class, 'update']);

    // Invoice lines
    Route::get('inv-line', [SuperAdminInvLineController::class, 'getAllInvLine']);
    Route::get('inv-line/{inv_no}', [SuperAdminInvLineController::class, 'getInvLine']);

    // Document streaming
    Route::get('files/{folder}/{filename}', [SuperAdminInvDocumentController::class, 'streamFile']);

    Route::post('logout', [AuthController::class, 'logout']);
});

// Finance routes
Route::middleware(['auth:sanctum', 'userRole:2'])->prefix('finance')->group(function () {
    // Dashboard
    Route::get('dashboard', [FinanceDashboardController::class, 'dashboard']);

    // Invoice management
    Route::get('inv-header', [FinanceInvHeaderController::class, 'getInvHeader']);
    Route::get('inv-header/bp-code/{bp_code}', [FinanceInvHeaderController::class, 'getInvHeaderByBpCode']);
    Route::put('inv-header/{inv_no}', [FinanceInvHeaderController::class, 'update']);
    Route::put('inv-header/{inv_no}/in-process', [FinanceInvHeaderController::class, 'updateStatusToInProcess']);

    // Invoice lines
    Route::get('inv-line/{inv_no}', [FinanceInvLineController::class, 'getInvLine']);

    // Document streaming
    Route::get('files/{folder}/{filename}', [FinanceInvDocumentController::class, 'streamFile']);

    Route::post('logout', [AuthController::class, 'logout']);
});

// Supplier routes
Route::middleware(['auth:sanctum', 'userRole:3'])->prefix('supplier-finance')->group(function () {
    // Dashboard
    Route::get('dashboard', [SupplierDashboardController::class, 'dashboard']);

    // Invoice management
    Route::get('inv-header', [SupplierInvHeaderController::class, 'getInvHeader']);
    Route::post('inv-header/store', [SupplierInvHeaderController::class, 'store']);

    // Invoice lines
    Route::get('inv-line', [SupplierInvLineController::class, 'getInvLineTransaction']);
    Route::get('inv-line/{inv_no}', [SupplierInvLineController::class, 'getInvLine']);

    Route::post('logout', [AuthController::class, 'logout']);
});
