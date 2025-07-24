<?php

use App\Jobs\SyncManualJob;
use Illuminate\Http\Request;
use App\Models\ERP\InvReceipt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Local2\LocalDataController;
use App\Http\Controllers\Api\Finance\FinanceNewsController;
use App\Http\Controllers\Api\Local2\InvoiceReceiptController;
use App\Http\Controllers\Api\Finance\FinanceInvLineController;
use App\Http\Controllers\Api\Finance\FinanceDashboardController;
use App\Http\Controllers\Api\Finance\FinanceInvHeaderController;
use App\Http\Controllers\Api\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Api\Finance\FinanceInvDocumentController;
use App\Http\Controllers\Api\Admin\SuperAdminInvDocumentController;
use App\Http\Controllers\Api\SupplierFinance\SupplierNewsController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvLineController;
use App\Http\Controllers\Api\SupplierFinance\SupplierDashboardController;
use App\Http\Controllers\Api\SupplierFinance\SupplierInvHeaderController;

Route::post('/login', [AuthController::class, 'login']);

// Route for sync data from second database
Route::get('local2/sync-inv-line', [LocalDataController::class, 'syncInvLine'])->middleware('auth:sanctum');

Route::get('/stream/{type}/{filename}', [FinanceInvDocumentController::class, 'stream']);

Route::get('sync', [InvoiceReceiptController::class, 'copyInvLines']);

Route::get('syncnow', function () {
    dispatch(new SyncManualJob());
    return json_encode('Selesai');
});

Route::get('tes1', function () {
    $currentYear = now()->year;
    // dd($currentYear);
            $currentMonth = now()->endOfMonth()->format('Y-m-d');
            $oneMonthBefore = now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
// dd("$currentMonth $oneMonthBefore");
            // Get da6a from ERP
            $sqlsrvData = InvReceipt::whereYear('payment_doc_date', $currentYear)
                ->whereBetween('payment_doc_date', [$oneMonthBefore, $currentMonth])
                ->get();
    // $sqlsrvData = InvReceipt::where('payment_doc_date', '2025-06-05')->get();
    return json_encode($sqlsrvData);
});

// Admin routes
Route::middleware(['auth:sanctum', 'userRole:1'])->prefix('super-admin')->group(function () {
    // Dashboard
    Route::get('dashboard', [SuperAdminDashboardController::class, 'dashboard']);
    Route::get('active-user', [SuperAdminDashboardController::class, 'detailActiveUser']);
    Route::post('logout-user', [SuperAdminDashboardController::class, 'logoutByTokenId']);

    // Route for sync data from second database
    Route::get('sync-inv-line', [LocalDataController::class, 'syncInvLine']);
    Route::get('business-partners', [UserController::class, 'getBusinessPartner']);

    // User management
    Route::get('index', [UserController::class, 'index']);
    Route::post('store', [UserController::class, 'store']);
    Route::get('edit/{id}', [UserController::class, 'edit']);
    Route::put('update/{id}', [UserController::class, 'update']);
    Route::delete('delete/{id}', [UserController::class, 'destroy']);
    Route::patch('status/{id}', [UserController::class, 'updateStatus']);

    // Document streaming
    // Route::get('files/{folder}/{filename}', [SuperAdminInvDocumentController::class, 'streamFile']);

    Route::post('logout', [AuthController::class, 'logout']);
});

// Finance routes
Route::middleware(['auth:sanctum', 'userRole:2'])->prefix('finance')->group(function () {
    // Dashboard
    Route::get('dashboard', [FinanceDashboardController::class, 'dashboard']);

    // Business Partners
    Route::get('business-partners', [UserController::class, 'getBusinessPartner']);

    // Invoice management
    Route::get('inv-header', [FinanceInvHeaderController::class, 'getInvHeader']);
    Route::post('inv-header/store', [FinanceInvHeaderController::class, 'store']);
    Route::get('inv-header/bp-code/{bp_code}', [FinanceInvHeaderController::class, 'getInvHeaderByBpCode']);
    Route::put('inv-header/{inv_id}', [FinanceInvHeaderController::class, 'update']);
    Route::put('inv-header/in-process/{inv_id}', [FinanceInvHeaderController::class, 'updateStatusToInProcess']);
    Route::get('inv-header/detail/{inv_id}', [FinanceInvHeaderController::class, 'getInvHeaderDetail']);
    Route::post('inv-header/upload-payment', [FinanceInvHeaderController::class, 'uploadPaymentDocuments']);
    Route::post('inv-header/revertInvoices', [FinanceInvHeaderController::class, 'revertToReadyToPayment']);
    Route::patch('inv-header/revertInvoiceInProcess/{inv_id}', [FinanceInvHeaderController::class, 'revertToInProcess']);

    // News
    Route::get('news', [FinanceNewsController::class, 'index']);
    Route::post('news/store', [FinanceNewsController::class, 'store']);
    Route::get('news/edit/{id}', [FinanceNewsController::class, 'edit']);
    Route::put('news/update/{id}', [FinanceNewsController::class, 'update']);
    Route::delete('news/delete/{id}', [FinanceNewsController::class, 'destroy']);
    Route::get('news/document/{filename}', [FinanceNewsController::class, 'streamDocument'])->middleware('auth');

    //Document
    // Route::get('/stream/{type}/{filename}', [FinanceInvHeaderController::class, 'stream']);

    // PPh
    Route::get('pph', [FinanceInvHeaderController::class, 'getPph']);
    Route::get('ppn', [FinanceInvHeaderController::class, 'getPpn']);

    // Invoice lines
    Route::get('inv-line/{bp_code}', [FinanceInvLineController::class, 'getInvLineTransaction']);
    Route::get('inv-line/{inv_no}', [FinanceInvLineController::class, 'getInvLine']);
    Route::get('inv-line/outstanding/{bp_code}', [FinanceInvLineController::class, 'getOutstandingInvLine']);
    Route::get('inv-line/invoice/{bp_code}', [FinanceInvLineController::class, 'getUninvoicedInvLineTransaction']);

    // Document streaming
    Route::get('files/{folder}/{filename}', [FinanceInvDocumentController::class, 'streamFile']);

    // User profile management
    Route::get('edit/profile', [UserController::class, 'profile']);
    Route::put('update/profile', [UserController::class, 'updatePersonal']);

    Route::post('logout', [AuthController::class, 'logout']);
});

// Supplier routes
Route::middleware(['auth:sanctum', 'userRole:3'])->prefix('supplier-finance')->group(function () {
    // Dashboard
    Route::get('dashboard', [SupplierDashboardController::class, 'dashboard']);

    // Business Partners
    Route::get('business-partners', [SupplierDashboardController::class, 'getBusinessPartner']);

    // Invoice management
    Route::get('inv-header', [SupplierInvHeaderController::class, 'getInvHeader']);
    Route::post('inv-header/store', [SupplierInvHeaderController::class, 'store']);
    Route::put('inv-header/reject/{inv_id}', [SupplierInvHeaderController::class, 'rejectInvoice']);

    // News
    Route::get('news', [SupplierNewsController::class, 'index']);
    Route::get('news/document/{filename}', [SupplierNewsController::class, 'streamDocument'])->middleware('auth');

    // Document
    Route::get('/stream/{type}/{filename}', [SupplierInvHeaderController::class, 'stream']);

    Route::get('ppn', [SupplierInvHeaderController::class, 'getPpn']);

    // Invoice lines
    Route::get('inv-line', [SupplierInvLineController::class, 'getInvLineTransaction']);
    Route::get('inv-line/{inv_no}', [SupplierInvLineController::class, 'getInvLine']);
    Route::get('inv-line/outstanding', [SupplierInvLineController::class, 'getOutstandingInvLine']);
    Route::get('inv-line/supinvoice', [SupplierInvLineController::class, 'getUninvoicedInvLineTransaction']);

    // User profile management
    Route::get('edit/profile', [UserController::class, 'profile']);
    Route::put('update/profile', [UserController::class, 'updatePersonal']);

    Route::post('logout', [AuthController::class, 'logout']);
});

// Route for sync data from second database
