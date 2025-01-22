<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Finance\FinanceInvHeaderController;
use App\Http\Controllers\Api\Finance\FinanceInvLineController;
use App\Http\Controllers\Api\Finance\FinanceInvDocumentController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvHeaderController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvLineController;

Route::post('/login', [AuthController::class, 'login']);

// Admin routes
Route::middleware(['auth:sanctum', 'role:1'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/store', [UserController::class, 'store']);
    Route::get('/users/{id}/edit', [UserController::class, 'edit']);
    Route::put('/users/update/{id}', [UserController::class, 'update']);
    Route::delete('/users/delete/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/status/{status}', [UserController::class, 'updateStatus']);
});

// Finance routes
Route::middleware(['auth:sanctum', 'role:2'])->group(function () {
    Route::get('/finance/inv-header', [FinanceInvHeaderController::class, 'getInvHeader']);
    Route::put('/finance/inv-header/{inv_no}', [FinanceInvHeaderController::class, 'update']);
    Route::get('/finance/inv-line/{inv_no}', [FinanceInvLineController::class, 'getInvLine']);
    Route::get('/files/{filename}', [FinanceInvDocumentController::class, 'streamFile']);
});

// Supplier routes
Route::middleware(['auth:sanctum', 'role:3'])->group(function () {
    Route::get('/supplier/inv-header', [SupplierInvHeaderController::class, 'getInvHeader']);
    Route::post('/supplier/inv-header/store', [SupplierInvHeaderController::class, 'store']);
    Route::get('/supplier/inv-line', [SupplierInvLineController::class, 'getInvLineTransaction']);
    Route::get('/supplier/inv-line/{inv_no}', [SupplierInvLineController::class, 'getInvLine']);
});
