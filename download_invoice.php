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
    @media print {
        body { margin: 0; padding: 0; }
        .container { padding: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="header-left">
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

// Check if download is requested
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    // Send as HTML file that browser can print to PDF
    $filename = 'Invoice_' . preg_replace('/[^a-z0-9_-]+/i', '_', $invoice['invoice_number']) . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $invoice_html;
    exit();
}

// Default: Show interactive view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; }
        .invoice-container { background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .invoice-actions { padding: 1.5rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .invoice-content { padding: 2rem; }
        .status-badge { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        @media print { 
            .invoice-actions { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="container-lg py-4">
        <div class="invoice-container">
            <!-- Invoice Actions -->
            <div class="invoice-actions">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="mb-0">Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h3>
                        <small class="text-muted">
                            <span class="status-badge status-<?= $invoice['status'] ?>">
                                <?= ucfirst($invoice['status']) ?>
                            </span>
                        </small>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <a href="?invoice_id=<?= $invoice_id ?>&format=pdf" class="btn btn-outline-danger">
                            <i class="bi bi-file-pdf"></i> Download PDF
                        </a>
                        <a href="parent_invoices.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Invoice Content -->
            <div class="invoice-content">
                <?php echo $invoice_html; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
?>