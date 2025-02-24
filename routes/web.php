<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\TestPrintController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-print', [TestPrintController::class, 'printReceipt'])->name('test.print');
