<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinanceInvDocumentController extends Controller
{
    public function stream($type, $filename)
    {
        $allowedTypes = ['invoices', 'faktur', 'suratjalan', 'po', 'receipts'];
        if (!in_array($type, $allowedTypes)) {
            abort(404);
        }
        $path = $type . '/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        return response()->file(storage_path('app/public/' . $path));
    }
}
 // Route::get('files/{filename}', [FinanceInvDocumentController::class, 'streamFile'])->middleware('auth');

 // URL On Frontend
 //URL: http://your-domain/files/invoices/INVOICE_{inv_no}.pdf
 // URL: http://your-domain/files/faktur/FAKTURPAJAK_{inv_no}.pdf
 // URL: http://your-domain/files/suratjalan/SURATJALAN_{inv_no}.pdf
 // URL: http://your-domain/files/po/PO_{inv_no}.pdf
