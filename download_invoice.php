<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

if (!isset($_GET['invoice_id']) || intval($_GET['invoice_id']) <= 0) {
    die("Invalid invoice id.");
}

$invoice_id = intval($_GET['invoice_id']);
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch invoice and related info with security check
$sql = "
    SELECT i.*, e.enrollment_id, c.courseName, b.batch_code, 
           s.student_id, u.firstName AS student_fname, u.lastName AS student_lname,
           pu.user_id AS parent_user_id, pu.firstName AS parent_fname, pu.lastName AS parent_lname
    FROM invoices i
    JOIN course_enrollments e ON i.enrollment_id = e.enrollment_id
    JOIN batches b ON e.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN parent_students ps ON s.student_id = ps.student_id
    LEFT JOIN parents p ON ps.parent_id = p.parent_id
    LEFT JOIN users pu ON p.user_id = pu.user_id
    WHERE i.invoice_id = ? AND pu.user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $invoice_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    die("Invoice not found or access denied.");
}

// School details
$school_name = "Girls Coding Academy";
$school_address = "Maseru, Lesotho";
$school_phone = "+266 000 0000";
$school_email = "info@girlscodingacademy.org";
$school_logo = "uploads/logo.png";

// Convert logo to base64 for embedding
$logo_base64 = '';
if (file_exists($school_logo)) {
    $logo_type = pathinfo($school_logo, PATHINFO_EXTENSION);
    $logo_data = file_get_contents($school_logo);
    $logo_base64 = 'data:image/' . $logo_type . ';base64,' . base64_encode($logo_data);
}

// Status styling
$status = strtoupper($invoice['status']);
$status_color = '#f59e0b';
$status_bg = '#fef3c7';
if ($invoice['status'] === 'paid') {
    $status_color = '#065f46';
    $status_bg = '#d1fae5';
} elseif ($invoice['status'] === 'overdue') {
    $status_color = '#991b1b';
    $status_bg = '#fee2e2';
}

// Build invoice HTML
$invoice_html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
<style>
    @page {
        margin: 20mm;
    }
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        color: #1e293b;
        line-height: 1.6;
        font-size: 11pt;
    }
    .container {
        max-width: 100%;
        padding: 20px;
    }
    .header {
        display: table;
        width: 100%;
        border-bottom: 3px solid #6366f1;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }
    .header-left {
        display: table-cell;
        width: 60%;
        vertical-align: top;
    }
    .header-right {
        display: table-cell;
        width: 40%;
        vertical-align: top;
        text-align: right;
    }
    .logo {
        max-height: 80px;
        max-width: 120px;
        margin-bottom: 10px;
    }
    .school-name {
        font-size: 20pt;
        font-weight: bold;
        color: #1e293b;
        margin-bottom: 5px;
    }
    .school-info {
        font-size: 9pt;
        color: #64748b;
        line-height: 1.4;
    }
    .invoice-title {
        font-size: 24pt;
        font-weight: bold;
        color: #6366f1;
        margin-bottom: 10px;
    }
    .invoice-meta {
        font-size: 9pt;
        color: #64748b;
        line-height: 1.6;
    }
    .invoice-meta strong {
        color: #1e293b;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 9pt;
        margin-top: 8px;
        background: ' . $status_bg . ';
        color: ' . $status_color . ';
    }
    .parties {
        display: table;
        width: 100%;
        margin: 30px 0;
    }
    .party {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding-right: 20px;
    }
    .party-right {
        text-align: right;
        padding-right: 0;
        padding-left: 20px;
    }
    .party-title {
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
        color: #1e293b;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
    }
    .party-info {
        font-size: 10pt;
        color: #475569;
        line-height: 1.5;
    }
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin: 30px 0;
    }
    .invoice-table thead {
        background: #f1f5f9;
    }
    .invoice-table th {
        padding: 12px;
        text-align: left;
        font-weight: bold;
        font-size: 10pt;
        color: #1e293b;
        border-bottom: 2px solid #e2e8f0;
    }
    .invoice-table th.right {
        text-align: right;
    }
    .invoice-table td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
        font-size: 10pt;
    }
    .invoice-table td.right {
        text-align: right;
    }
    .invoice-table .description {
        font-weight: 600;
        color: #1e293b;
    }
    .invoice-table .sub-description {
        font-size: 8pt;
        color: #64748b;
        font-style: italic;
        display: block;
        margin-top: 4px;
    }
    .invoice-table tfoot td {
        font-weight: bold;
        font-size: 12pt;
        color: #1e293b;
        border-top: 2px solid #e2e8f0;
        border-bottom: none;
        padding-top: 12px;
    }
    .total-amount {
        color: #6366f1;
        font-size: 14pt;
    }
    .footer {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }
    .footer-title {
        font-size: 10pt;
        font-weight: bold;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .footer-text {
        font-size: 9pt;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 4px;
    }
    .thank-you {
        text-align: center;
        margin-top: 30px;
        font-size: 9pt;
        color: #64748b;
        font-style: italic;
    }
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" class="logo" alt="Logo">' : '') . '
            <div class="school-name">' . htmlspecialchars($school_name) . '</div>
            <div class="school-info">
                ' . htmlspecialchars($school_address) . '<br>
                Phone: ' . htmlspecialchars($school_phone) . '<br>
                Email: ' . htmlspecialchars($school_email) . '
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-meta">
                <strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '<br>
                <strong>Date Issued:</strong> ' . date('d/m/Y', strtotime($invoice['created_at'])) . '<br>
                <strong>Due Date:</strong> ' . date('d/m/Y', strtotime($invoice['due_date'])) . '<br>
                <div class="status-badge">' . $status . '</div>
            </div>
        </div>
    </div>

    <!-- Billing Parties -->
    <div class="parties">
        <div class="party">
            <div class="party-title">Bill To:</div>
            <div class="party-info">
                <strong>' . htmlspecialchars($invoice['parent_fname'] . ' ' . $invoice['parent_lname']) . '</strong><br>
                Parent/Guardian<br>
                ' . htmlspecialchars($school_phone) . '<br>
                ' . htmlspecialchars($school_email) . '
            </div>
        </div>
        <div class="party party-right">
            <div class="party-title">Student Information:</div>
            <div class="party-info">
                <strong>' . htmlspecialchars($invoice['student_fname'] . ' ' . $invoice['student_lname']) . '</strong><br>
                <strong>Course:</strong> ' . htmlspecialchars($invoice['courseName']) . '<br>
                <strong>Batch:</strong> ' . htmlspecialchars($invoice['batch_code']) . '
            </div>
        </div>
    </div>

    <!-- Invoice Table -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 60%;">Description</th>
                <th style="width: 20%;" class="right">Quantity</th>
                <th style="width: 20%;" class="right">Amount (LSL)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <span class="description">Tuition Fee - ' . htmlspecialchars($invoice['courseName']) . '</span>
                    <span class="sub-description">Batch: ' . htmlspecialchars($invoice['batch_code']) . '</span>
                </td>
                <td class="right">1</td>
                <td class="right">' . number_format($invoice['amount'], 2) . '</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="right">TOTAL AMOUNT DUE:</td>
                <td class="right total-amount">LSL ' . number_format($invoice['amount'], 2) . '</td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-title">Payment Instructions:</div>
        <div class="footer-text">
            Please make payments via bank transfer or at the school office.<br>
            For queries, contact us at ' . htmlspecialchars($school_phone) . ' or ' . htmlspecialchars($school_email) . '
        </div>
        <div class="thank-you">
            Thank you for supporting your child\'s education at ' . htmlspecialchars($school_name) . '!
        </div>
    </div>
</div>
</body>
</html>
';

// Try different PDF libraries in order of preference
$pdf_generated = false;

// Option 1: Try Dompdf (if installed via composer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        // Use full namespace
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 150);
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($invoice_html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'Invoice_' . preg_replace('/[^a-z0-9_-]+/i', '_', $invoice['invoice_number']) . '.pdf';
        $dompdf->stream($filename, array("Attachment" => 1));
        $pdf_generated = true;
        exit(); // Make sure we exit after successful PDF generation
    } catch (Exception $e) {
        // Log error for debugging
        error_log("Dompdf Error: " . $e->getMessage());
        // Continue to next option
    }
}

// Option 2: Try TCPDF (if installed manually)
if (!$pdf_generated && file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
    require_once(__DIR__ . '/tcpdf/tcpdf.php');
    
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Girls Coding Academy');
        $pdf->SetAuthor($school_name);
        $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();
        $pdf->writeHTML($invoice_html, true, false, true, false, '');
        
        $filename = 'Invoice_' . preg_replace('/[^a-z0-9_-]+/i', '_', $invoice['invoice_number']) . '.pdf';
        $pdf->Output($filename, 'D');
        $pdf_generated = true;
    } catch (Exception $e) {
        // Continue to next option
    }
}

// Option 3: If no PDF library available, provide HTML download or instructions
if (!$pdf_generated) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PDF Library Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> PDF Library Not Installed</h4>
                </div>
                <div class="card-body">
                    <p class="lead">To download invoices as PDF, you need to install a PDF library.</p>
                    
                    <h5 class="mt-4">Option 1: Install Dompdf (Recommended)</h5>
                    <p>Open command prompt in your project folder and run:</p>
                    <div class="bg-dark text-light p-3 rounded">
                        <code>composer require dompdf/dompdf</code>
                    </div>
                    
                    <h5 class="mt-4">Option 2: Download TCPDF Manually</h5>
                    <ol>
                        <li>Download TCPDF from: <a href="https://github.com/tecnickcom/TCPDF/releases" target="_blank">https://github.com/tecnickcom/TCPDF</a></li>
                        <li>Extract the zip file to your project folder as "tcpdf"</li>
                        <li>Your folder structure should be: <code>C:\xampp\htdocs\GirlsCodingAcademy\tcpdf\</code></li>
                    </ol>
                    
                    <h5 class="mt-4">Option 3: View/Print Invoice as HTML</h5>
                    <p>You can still view and print the invoice:</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <button onclick="showInvoice()" class="btn btn-primary">View Invoice HTML</button>
                        <a href="parent_invoices.php" class="btn btn-secondary">Back to Invoices</a>
                    </div>
                    
                    <div id="invoiceDisplay" style="display:none;" class="mt-4">
                        <hr>
                        <div class="text-end mb-2">
                            <button onclick="window.print()" class="btn btn-success">Print Invoice</button>
                        </div>
                        <?php echo $invoice_html; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function showInvoice() {
            document.getElementById('invoiceDisplay').style.display = 'block';
            setTimeout(() => {
                document.getElementById('invoiceDisplay').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>