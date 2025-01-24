<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinanceInvDocumentController extends Controller
{
    public function streamFile($folder, $filename)
    {
        $relativePath = "public/{$folder}/{$filename}";

        if (!Storage::disk('public')->exists($relativePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Get the absolute path to the file in storage/app/public/<folder>/<filename>.
        $absolutePath = storage_path("app/public/{$relativePath}");

        // Use response()->file(...) to show/stream the file inline instead of forcing download.
        return response()->file($absolutePath);
    }
}
 // Route::get('files/{filename}', [FinanceInvDocumentController::class, 'streamFile'])->middleware('auth');

 // URL On Frontend
 //URL: http://your-domain/files/invoices/INVOICE_{inv_no}.pdf
 // URL: http://your-domain/files/faktur/FAKTURPAJAK_{inv_no}.pdf
 // URL: http://your-domain/files/suratjalan/SURATJALAN_{inv_no}.pdf
 // URL: http://your-domain/files/po/PO_{inv_no}.pdf
