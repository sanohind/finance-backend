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
            padding: 15px;
            font-size: 12px; /* Slightly reduced font size */
        }

        .content-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            margin: 0 auto;
            width: 100%;
            max-width: 480px;
            padding: 15px;
            background-color: #fff;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
        }

        .bottom-section {
            margin-top: auto;
            padding-top: 30px;
        }

        .header-title {
            font-size: 16px; /* Reduced size */
            font-weight: bold;
            margin-bottom: 8px;
            text-align: center;
        }

        .header-title2 {
            font-size: 14px; /* Reduced size */
            font-weight: bold;
            margin-bottom: 8px;
            text-align: center;
        }

        .logo {
            width: 80px; /* Smaller logo */
            margin-bottom: 8px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        /* Modified row layout */
        .row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
            line-height: 1.5; /* Adds vertical spacing within the row */
        }

        .label {
            display: table-cell;
            font-size: 12px;
            width: 35%;
            font-weight: bold;
            text-align: left;
            padding-right: 5px;
            padding-bottom: 5px; /* Added padding bottom */
            vertical-align: top; /* Aligns text to top */
        }

        .value {
            display: table-cell;
            font-size: 12px;
            text-align: right;
            width: 65%;
            padding-left: 5px;
            padding-bottom: 5px; /* Added padding bottom */
            word-wrap: break-word;
            vertical-align: top; /* Aligns text to top */
        }

        /* Modified currency rows */
        .row-with-currency {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .row-with-currency .label {
            display: table-cell;
            width: 35%;
        }

        .row-with-currency .currency {
            display: table-cell;
            width: 10%;
            font-size: 12px;
            text-align: center;
        }

        .row-with-currency .value {
            display: table-cell;
            width: 55%;
        }

        .divider {
            border-bottom: 1px solid #000;
            margin: 8px 0;
        }

        .footer {
            font-size: 10px;
            margin-top: 15px;
            text-align: center;
            font-style: italic;
            color: #888;
        }

        .signature-section {
            margin-top: 20px;
            text-align: left;
        }

        .signature-text {
            font-size: 12px;
            margin-bottom: 4px;
        }

        .signature-blank {
            width: 150px; /* Reduced width */
            height: 80px; /* Reduced height */
            margin-bottom: 4px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="container">
            <div class="main-content">
                <!-- Your logo (adjust path as needed) -->
                <img class="logo" src="{{ public_path('storage/public/Logo-sanoh.png') }}" alt="Logo" />

                <h2 class="header-title">Invoice Receipt</h2>
                <h3 class="header-title2">SANOH0166</h3>

                <div class="row">
                    <div class="label">Supplier</div>
                    <div class="value">5224-PT. MULTI KARYA SINARDINAMIKA</div>
                </div>
                <div class="row">
                    <div class="label">No PO</div>
                    <div class="value">PL2402698</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Number</div>
                    <div class="value">SANOH 3.7.4</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Date</div>
                    <div class="value">2025-01-20</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Tax Number</div>
                    <div class="value">0110003226895312</div>
                </div>
                <div class="row">
                    <div class="label">Invoice Tax Date</div>
                    <div class="value">2025-01-20</div>
                </div>
                <div class="row">
                    <div class="label">Status</div>
                    <div class="value">Approved</div>
                </div>

                <div class="row-with-currency">
                    <div class="label">Tax Base Amount</div>
                    <div class="currency">IDR</div>
                    <div class="value">6,274,800.00</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">Tax Amount (VAT)</div>
                    <div class="currency">IDR</div>
                    <div class="value">690,255.00</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">PPh Base Amount</div>
                    <div class="currency">IDR</div>
                    <div class="value">500,000.00</div>
                </div>
                <div class="row-with-currency">
                    <div class="label">PPh Amount (VAT)</div>
                    <div class="currency">IDR</div>
                    <div class="value">10,000.00</div>
                </div>

                <div class="divider"></div>

                <div class="row-with-currency">
                    <div class="label">Total Payment</div>
                    <div class="currency">IDR</div>
                    <div class="value">7,349,532.00</div>
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
