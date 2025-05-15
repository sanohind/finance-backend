<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Receipt</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');

        @page {
            size: A5;
            margin: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 10px; /* Reduced padding */
            font-size: 11px; /* Slightly reduced font size */
        }

        .content-wrapper {
            min-height: 100vh; /* Use min-height to allow content to define height */
            display: flex;
            flex-direction: column;
        }

        .container {
            margin: 0 auto;
            width: 100%;
            max-width: 480px; /* Max width for A5, adjust as needed */
            padding: 10px; /* Reduced padding */
            background-color: #fff;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
        }

        .bottom-section {
            margin-top: auto; /* Pushes to bottom if content is short */
            padding-top: 15px; /* Reduced padding */
        }

        .header-title {
            font-size: 15px; /* Reduced size */
            font-weight: bold;
            margin-bottom: 5px; /* Reduced margin */
            text-align: center;
        }

        .header-title2 {
            font-size: 13px; /* Reduced size */
            font-weight: bold;
            margin-bottom: 5px; /* Reduced margin */
            text-align: center;
        }

        .logo {
            width: 70px; /* Smaller logo */
            margin-bottom: 5px; /* Reduced margin */
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        /* Modified row layout */
        .row {
            display: table;
            width: 100%;
            margin-bottom: 1px; /* Reduced margin */
            line-height: 1.3; /* Reduced line-height */
        }

        .label {
            display: table-cell;
            font-size: 11px; /* Adjusted font size */
            width: 35%;
            font-weight: bold;
            text-align: left;
            padding-right: 5px;
            padding-bottom: 2px; /* Reduced padding bottom */
            vertical-align: top;
        }

        .value {
            display: table-cell;
            font-size: 11px; /* Adjusted font size */
            text-align: right;
            width: 65%;
            padding-left: 5px;
            padding-bottom: 2px; /* Reduced padding bottom */
            word-wrap: break-word;
            vertical-align: top;
        }

        /* Modified currency rows */
        .row-with-currency {
            display: table;
            width: 100%;
            margin-bottom: 4px; /* Reduced margin */
            line-height: 1.3; /* Reduced line-height */
        }

        .row-with-currency .label {
            display: table-cell;
            width: 35%;
            font-size: 11px; /* Adjusted font size */
        }

        .row-with-currency .currency {
            display: table-cell;
            width: 10%;
            font-size: 11px; /* Adjusted font size */
            text-align: center;
        }

        .row-with-currency .value {
            display: table-cell;
            width: 55%;
            font-size: 11px; /* Adjusted font size */
        }

        .divider {
            border-bottom: 1px solid #000;
            margin: 5px 0; /* Reduced margin */
        }

        .footer {
            font-size: 9px; /* Reduced font size */
            margin-top: 10px; /* Reduced margin */
            text-align: center;
            font-style: italic;
            color: #888;
        }

        .signature-section {
            margin-top: 10px; /* Reduced margin */
            text-align: left;
        }

        .signature-text {
            font-size: 11px; /* Adjusted font size */
            margin-bottom: 2px; /* Reduced margin */
        }

        .signature-blank {
            width: 120px; /* Reduced width */
            height: 40px; /* Reduced height */
            margin-bottom: 2px; /* Reduced margin */
            margin-top: 4px; /* Reduced margin */
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="container">
            <div class="main-content">
                <!-- Your logo (adjust path as needed) -->
                <img class="logo" src="{{ storage_path('app/public/Logo-sanoh.png') }}" alt="Logo" />

                <h2 class="header-title">Invoice Receipt</h2>
                <h3 class="header-title2">{{ $invHeader->receipt_number }}</h3>

                <div class="row">
                    <div class="label">Supplier</div>
                    <div class="value">{{ $partner_address }}</div>
                </div>
                <div class="row">
                    <div class="label">No PO</div>
                    <div class="value">{{ $po_numbers }}</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Number</div>
                    <div class="value">{{ $invHeader->inv_no }}</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Date</div>
                    <div class="value">{{ \Carbon\Carbon::parse($invHeader->inv_date)->format('Y-m-d') }}</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Tax Number</div>
                    <div class="value">{{ $invHeader->inv_faktur }}</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Tax Date</div>
                    <div class="value">{{ \Carbon\Carbon::parse($invHeader->inv_faktur_date)->format('Y-m-d') }}</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Payment Plan Date</div>
                    <div class="value">{{ $invHeader->plan_date ? \Carbon\Carbon::parse($invHeader->plan_date)->format('Y-m-d') : '' }}</div>
                </div>
                <div class="row">
                    <div class="label">Status</div>
                    <div class="value">{{ $invHeader->status }}</div>
                </div>

                <div class="row-with-currency">
                    <div class="label">Tax Base Amount</div>
                    <div class="currency">IDR</div>
                    <div class="value">{{ number_format($invHeader->tax_base_amount, 2, '.', ',') }}</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">Tax Amount (VAT)</div>
                    <div class="currency">IDR</div>
                    <div class="value">{{ number_format($tax_amount, 2, '.', ',') }}</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">PPh Base Amount</div>
                    <div class="currency">IDR</div>
                    <div class="value">{{ number_format($pph_base_amount, 2, '.', ',') }}</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">PPh Amount (VAT)</div>
                    <div class="currency">IDR</div>
                    <div class="value">{{ number_format($invHeader->pph_amount - $invHeader->pph_base_amount, 2, '.', ',') }}</div>
                </div>

                <div class="divider"></div>

                <div class="row-with-currency">
                    <div class="label">Total Payment</div>
                    <div class="currency">IDR</div>
                    <div class="value">{{ number_format($invHeader->total_amount, 2, '.', ',') }}</div>
                </div>

                <div class="bottom-section">
                    <p class="footer">
                        *Dapat dikirimkan original invoice setiap hari Rabu/Kamis setiap jam 9.00 - 15.00 WIB
                    </p>

                    <div class="signature-section">
                        <p class="signature-text">Finance</p>
                        <p class="signature-text">PT. Sanoh Indonesia</p>
                        <div class="signature-blank"></div>
                    </div>

                    <div class="divider"></div>

                    <p class="footer">
                        Catatan: tanda terima dicetak dan dilampirkan saat invoicing
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
