<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class TestPrintController extends Controller
{
    public function printReceipt()
    {
        $pdf = PDF::loadView('printreceipt');
        $pdf->setPaper('A5', 'portrait');
        return $pdf->download('invoice_receipt.pdf');
    }
}
