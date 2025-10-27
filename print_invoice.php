<?php
// print_invoice.php
// Generates a printable PDF invoice and optionally emails it via PHPMailer.
// Fixed: Manual PHPMailer includes (no Composer needed).

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Manual PHPMailer includes (place PHPMailer/src/ in project root)
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

// Include FPDF (download fpdf.php to root if needed)
require_once('fpdf.php');

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$invoice_id = (int)($_GET['id'] ?? 0);
$invoice = null;
$payments = [];

// Fetch invoice details with joins
$sql = "
    SELECT i.*, 
           ce.enrollment_id,
           u.firstName, u.lastName, u.email, u.phone,
           b.batch_code, b.start_date, b.end_date,
           c.courseName, c.title as course_title, c.price
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE i.invoice_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Invoice not found.");
}

// Fetch related payments
$payment_sql = "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$payment_result = $stmt->get_result();
while ($pay = $payment_result->fetch_assoc()) {
    $payments[] = $pay;
}
$stmt->close();

$conn->close();

// Extend FPDF for custom invoice layout
class InvoicePDF extends FPDF {
    function Header() {
        // Logo (optional: add path to logo)
        // $this->Image('gca_logo.png', 10, 10, 30);
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'Girls Coding Academy', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 5, 'Invoice', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Thank you for your business!', 0, 1, 'C');
    }

    function InvoiceTable($header, $data) {
        $this->SetFont('Arial', 'B', 12);
        foreach ($header as $col) {
            $this->Cell(30, 7, $col[0], 1);
        }
        $this->Ln();
        $this->SetFont('Arial', '', 10);
        foreach ($data as $row) {
            foreach ($row as $col) {
                $this->Cell(30, 6, $col, 1);
            }
            $this->Ln();
        }
    }
}

// Create PDF
$pdf = new InvoicePDF();
$pdf->AliasNbPages();
$pdf->AddPage('P', 'A4');
$pdf->SetFont('Arial', '', 12);

// Invoice Header Details
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Invoice Number: ' . $invoice['invoice_number'], 0, 1);
$pdf->Cell(0, 10, 'Date: ' . date('F j, Y', strtotime($invoice['created_at'])), 0, 1);
$pdf->Cell(0, 10, 'Due Date: ' . date('F j, Y', strtotime($invoice['due_date'])), 0, 1);
$pdf->Ln(5);

// Student Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Bill To:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 10, $invoice['firstName'] . ' ' . $invoice['lastName'], 0, 1);
$pdf->Cell(0, 10, $invoice['email'], 0, 1);
if ($invoice['phone']) {
    $pdf->Cell(0, 10, 'Phone: ' . $invoice['phone'], 0, 1);
}
$pdf->Ln(10);

// Course Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Description:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 10, $invoice['course_title'] . ' (' . $invoice['courseName'] . ')', 0, 1);
$pdf->Cell(0, 10, 'Batch: ' . $invoice['batch_code'], 0, 1);
$pdf->Cell(0, 10, 'Batch Period: ' . date('M j, Y', strtotime($invoice['start_date'])) . ' - ' . date('M j, Y', strtotime($invoice['end_date'])), 0, 1);
$pdf->Ln(10);

// Amount and Status
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Total Amount: $' . number_format($invoice['amount'], 2), 0, 1, 'R');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Status: ' . ucfirst($invoice['status']), 0, 1);
$pdf->Ln(10);

// Payments Table (if any)
if (!empty($payments)) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Payment History:', 0, 1);
    $pdf->Ln(5);

    $header = array(array('Payment ID', 'Amount', 'Method', 'Status', 'Date'));
    $data = array();
    foreach ($payments as $pay) {
        $data[] = array(
            $pay['payment_id'],
            '$' . number_format($pay['amount'], 2),
            $pay['payment_method'] ?? 'N/A',
            ucfirst($pay['status']),
            date('M j, Y H:i', strtotime($pay['payment_date']))
        );
    }
    $pdf->InvoiceTable($header, $data);
    $pdf->Ln(10);
}

// Notes
$pdf->SetFont('Arial', 'I', 10);
$pdf->MultiCell(0, 6, 'Notes: This invoice is for the enrollment in the specified course and batch. For questions, contact admin@girlscoding.com.', 0, 'J');
$pdf->Ln(10);

// Generate PDF filename
$pdf_filename = 'invoices/invoice_' . $invoice['invoice_number'] . '.pdf'; // Save to invoices/ folder
if (!is_dir('invoices')) {
    mkdir('invoices', 0755, true);
}
$pdf->Output('F', $pdf_filename); // Save to file

// Optional: Email the PDF
$send_email = (isset($_GET['email']) && $_GET['email'] === 'true');
if ($send_email) {
    $email_to = $invoice['email'];
    $email_subject = "Your Girls Coding Academy Invoice #" . $invoice['invoice_number'];
    $email_body = "
        <h2>Invoice #" . $invoice['invoice_number'] . "</h2>
        <p>Dear " . $invoice['firstName'] . ",</p>
        <p>Attached is your invoice for " . $invoice['course_title'] . ". Please review and let us know if you have questions.</p>
        <p>Amount Due: $" . number_format($invoice['amount'], 2) . "<br>
        Due Date: " . date('F j, Y', strtotime($invoice['due_date'])) . "</p>
        <p>Thank you!<br>Girls Coding Academy</p>
    ";

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);

    try {
        // Server settings (configure for your SMTP, e.g., Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Or your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-admin-email@gmail.com'; // Your email
        $mail->Password   = 'your-app-password'; // App password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your-admin-email@gmail.com', 'Girls Coding Academy');
        $mail->addAddress($email_to, $invoice['firstName'] . ' ' . $invoice['lastName']);
        $mail->addReplyTo('your-admin-email@gmail.com', 'Admin Support');

        // Attachments
        $mail->addAttachment($pdf_filename); // Attach the generated PDF

        // Content
        $mail->isHTML(true);
        $mail->Subject = $email_subject;
        $mail->Body    = $email_body;
        $mail->AltBody = strip_tags($email_body);

        $mail->send();
        echo "<script>alert('Invoice emailed successfully!'); window.close() || window.location.href='manage_invoices.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Email failed: " . $mail->ErrorInfo . "'); window.close() || window.location.href='manage_invoices.php';</script>";
    }

    // Clean up PDF file after sending
    unlink($pdf_filename);
} else {
    // Default: Download PDF
    $pdf->Output('D', $pdf_filename);
}
?>