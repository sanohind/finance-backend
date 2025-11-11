<?php

/**
 * Script untuk menganalisis perhitungan tax_base_amount pada invoice 47/X/25-0945
 * Script ini akan membandingkan perhitungan menggunakan approve_qty vs receipt_amount
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================================\n";
echo "ANALISIS PERHITUNGAN TAX BASE AMOUNT - INVOICE 47/X/25-0945\n";
echo "=================================================================\n\n";

// Search for the invoice
$invoiceNumber = '47/X/25-0945';
$invHeader = DB::connection('mysql')
    ->table('inv_header')
    ->where('inv_no', $invoiceNumber)
    ->first();

if (!$invHeader) {
    echo "❌ Invoice {$invoiceNumber} tidak ditemukan!\n";
    echo "Mencari invoice dengan pattern serupa...\n\n";
    
    // Try to find similar invoices
    $similarInvoices = DB::connection('mysql')
        ->table('inv_header')
        ->where('inv_no', 'LIKE', '%47%X%25%')
        ->orWhere('inv_no', 'LIKE', '%0945%')
        ->get();
    
    if ($similarInvoices->count() > 0) {
        echo "Invoice serupa yang ditemukan:\n";
        foreach ($similarInvoices as $inv) {
            echo "- {$inv->inv_no} (ID: {$inv->inv_id})\n";
        }
    } else {
        echo "Tidak ada invoice serupa yang ditemukan.\n";
    }
    
    echo "\nMenampilkan 5 invoice terbaru sebagai contoh:\n";
    $recentInvoices = DB::connection('mysql')
        ->table('inv_header')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['inv_id', 'inv_no', 'tax_base_amount', 'total_dpp', 'created_at']);
    
    foreach ($recentInvoices as $inv) {
        echo "- {$inv->inv_no} (ID: {$inv->inv_id}, Tax Base: " . number_format($inv->tax_base_amount, 0, ',', '.') . ")\n";
    }
    
    echo "\nSilakan jalankan script ini dengan invoice ID yang valid:\n";
    echo "php test_invoice_47_calculation.php [invoice_id]\n";
    exit(1);
}

echo "✓ Invoice ditemukan: {$invHeader->inv_no}\n";
echo "  Invoice ID: {$invHeader->inv_id}\n";
echo "  BP Code: {$invHeader->bp_code}\n";
echo "  Status: {$invHeader->status}\n";
echo "  Created: {$invHeader->created_at}\n\n";

// Get all invoice lines for this invoice
$invLines = DB::connection('mysql')
    ->table('transaction_invoice')
    ->join('inv_line', 'transaction_invoice.inv_line_id', '=', 'inv_line.inv_line_id')
    ->where('transaction_invoice.inv_id', $invHeader->inv_id)
    ->select(
        'inv_line.inv_line_id',
        'inv_line.po_no',
        'inv_line.item_no',
        'inv_line.item_desc',
        'inv_line.approve_qty',
        'inv_line.actual_receipt_qty',
        'inv_line.receipt_amount',
        'inv_line.receipt_unit_price'
    )
    ->get();

if ($invLines->count() === 0) {
    echo "❌ Tidak ada invoice lines yang terkait dengan invoice ini!\n";
    exit(1);
}

echo "─────────────────────────────────────────────────────────────────\n";
echo "DETAIL INVOICE LINES\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$method1Total = 0; // approve_qty * receipt_unit_price (Current method)
$method2Total = 0; // receipt_amount (Alternative method)

echo sprintf(
    "%-8s %-15s %-10s %-10s %-12s %-12s %-12s\n",
    "Line ID",
    "Item No",
    "Approve",
    "Actual",
    "Unit Price",
    "Method 1",
    "Receipt Amt"
);
echo str_repeat("─", 95) . "\n";

foreach ($invLines as $index => $line) {
    $method1Calc = $line->approve_qty * $line->receipt_unit_price;
    $method2Calc = $line->receipt_amount;
    
    $method1Total += $method1Calc;
    $method2Total += $method2Calc;
    
    echo sprintf(
        "%-8s %-15s %-10s %-10s %-12s %-12s %-12s\n",
        $line->inv_line_id,
        substr($line->item_no, 0, 15),
        $line->approve_qty,
        $line->actual_receipt_qty,
        number_format($line->receipt_unit_price, 0, ',', '.'),
        number_format($method1Calc, 0, ',', '.'),
        number_format($method2Calc, 0, ',', '.')
    );
    
    // Show difference if exists
    $lineDiff = $method2Calc - $method1Calc;
    if (abs($lineDiff) > 0.01) {
        echo sprintf(
            "%8s → Selisih: %s (Qty diff: %d)\n",
            "",
            number_format($lineDiff, 0, ',', '.'),
            $line->actual_receipt_qty - $line->approve_qty
        );
    }
}

echo str_repeat("─", 95) . "\n";
echo sprintf(
    "%-44s TOTAL: %-12s %-12s\n",
    "",
    number_format($method1Total, 0, ',', '.'),
    number_format($method2Total, 0, ',', '.')
);
echo str_repeat("═", 95) . "\n\n";

// Calculate discrepancy
$discrepancy = $method2Total - $method1Total;
$percentageDiff = ($discrepancy / $method2Total) * 100;

echo "─────────────────────────────────────────────────────────────────\n";
echo "PERBANDINGAN HASIL PERHITUNGAN\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

echo "1. METODE SAAT INI (approve_qty × receipt_unit_price):\n";
echo "   Total: Rp " . number_format($method1Total, 0, ',', '.') . "\n\n";

echo "2. METODE ALTERNATIF (sum of receipt_amount):\n";
echo "   Total: Rp " . number_format($method2Total, 0, ',', '.') . "\n\n";

echo "3. DATA DARI DATABASE (tax_base_amount):\n";
echo "   Total: Rp " . number_format($invHeader->tax_base_amount, 0, ',', '.') . "\n";
echo "   Total DPP: Rp " . number_format($invHeader->total_dpp, 0, ',', '.') . "\n\n";

echo "─────────────────────────────────────────────────────────────────\n";
echo "ANALISIS PERBEDAAN\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

echo "Selisih Absolute: Rp " . number_format(abs($discrepancy), 0, ',', '.') . "\n";
echo "Persentase Selisih: " . number_format(abs($percentageDiff), 2) . "%\n\n";

if (abs($discrepancy) > 0.01) {
    echo "❌ PERBEDAAN TERDETEKSI!\n\n";
    
    echo "Penyebab perbedaan:\n";
    $qtyDiffLines = [];
    foreach ($invLines as $line) {
        if ($line->approve_qty != $line->actual_receipt_qty) {
            $qtyDiffLines[] = $line;
        }
    }
    
    if (count($qtyDiffLines) > 0) {
        echo "- Terdapat " . count($qtyDiffLines) . " baris dengan perbedaan antara approve_qty dan actual_receipt_qty\n";
        echo "- receipt_amount dihitung berdasarkan actual_receipt_qty\n";
        echo "- Sistem saat ini menghitung tax_base_amount berdasarkan approve_qty\n\n";
        
        echo "Detail baris yang berbeda:\n";
        foreach ($qtyDiffLines as $line) {
            $diff = $line->actual_receipt_qty - $line->approve_qty;
            $amountDiff = $diff * $line->receipt_unit_price;
            echo "  • Line {$line->inv_line_id}: ";
            echo "Approve={$line->approve_qty}, Actual={$line->actual_receipt_qty} ";
            echo "(+{$diff} unit, Rp " . number_format($amountDiff, 0, ',', '.') . ")\n";
        }
    }
} else {
    echo "✓ TIDAK ADA PERBEDAAN\n";
    echo "Kedua metode menghasilkan nilai yang sama.\n";
}

echo "\n─────────────────────────────────────────────────────────────────\n";
echo "REKOMENDASI SOLUSI\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

echo "OPSI A: Gunakan receipt_amount (Metode 2)\n";
echo "Pro:\n";
echo "  ✓ Sesuai dengan perhitungan manual user\n";
echo "  ✓ Mencerminkan jumlah yang benar-benar diterima\n";
echo "  ✓ Konsisten dengan data dari sistem source\n";
echo "Con:\n";
echo "  ✗ Pembayaran mungkin lebih tinggi jika ada overdelivery\n\n";

echo "OPSI B: Tetap gunakan approve_qty (Metode 1)\n";
echo "Pro:\n";
echo "  ✓ Kontrol pembayaran lebih ketat\n";
echo "  ✓ Hanya bayar sesuai yang disetujui\n";
echo "Con:\n";
echo "  ✗ Tidak sesuai dengan jumlah aktual yang diterima\n";
echo "  ✗ Perlu rekonsiliasi manual dengan supplier\n\n";

echo "SARAN: ";
if (abs($percentageDiff) < 5) {
    echo "Gunakan OPSI A (receipt_amount)\n";
    echo "Karena:\n";
    echo "  - Perbedaan masih dalam range wajar (< 5%)\n";
    echo "  - Lebih mencerminkan transaksi aktual\n";
    echo "  - Mengurangi kompleksitas rekonsiliasi\n";
} else {
    echo "Review business process terlebih dahulu\n";
    echo "Karena:\n";
    echo "  - Perbedaan cukup signifikan (>= 5%)\n";
    echo "  - Perlu klarifikasi policy pembayaran\n";
    echo "  - Konsultasikan dengan finance & procurement\n";
}

echo "\n─────────────────────────────────────────────────────────────────\n";
echo "FILE YANG PERLU DIMODIFIKASI (jika memilih OPSI A):\n";
echo "─────────────────────────────────────────────────────────────────\n\n";
echo "File: app/Http/Controllers/Api/Finance/FinanceInvHeaderController.php\n";
echo "Method: store()\n";
echo "Line: ~82\n\n";
echo "PERUBAHAN:\n";
echo "Dari:\n";
echo "  \$total_dpp += \$invLine->approve_qty * \$invLine->receipt_unit_price;\n\n";
echo "Menjadi:\n";
echo "  \$total_dpp += \$invLine->receipt_amount;\n\n";

echo "=================================================================\n";
echo "SELESAI\n";
echo "=================================================================\n";
