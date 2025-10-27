<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$error = "";
$success = "";
$school_name = "Girls Coding Academy";
$school_address = "Maseru, Lesotho";
$school_phone = "+266 6859 0023";
$school_email = "info@girlscodingacademy.org";
$school_logo = "admin.png";

$user_id = $_SESSION['user_id'] ?? 0;

// Fetch parent details
$parent_sql = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc() ?: [];

// Get parent_id
$parent_id_sql = "SELECT parent_id FROM parents WHERE user_id = ?";
$stmt_parent = $conn->prepare($parent_id_sql);
$stmt_parent->bind_param("i", $user_id);
$stmt_parent->execute();
$parent_record = $stmt_parent->get_result()->fetch_assoc();
$parent_record_id = $parent_record ? $parent_record['parent_id'] : 0;

// Fetch children
$children = [];
if ($parent_record_id > 0) {
    $children_sql = "SELECT s.student_id, u.firstName, u.lastName, s.photo as student_photo
                    FROM parent_students ps 
                    JOIN students s ON ps.student_id = s.student_id 
                    JOIN users u ON s.user_id = u.user_id 
                    WHERE ps.parent_id = ?";
    $stmt_children = $conn->prepare($children_sql);
    $stmt_children->bind_param("i", $parent_record_id);
    $stmt_children->execute();
    $children_result = $stmt_children->get_result();
    $children = $children_result->fetch_all(MYSQLI_ASSOC);
}

// For each child, fetch enrollments and invoices
$total_due = 0;
$pending_count = 0;
foreach ($children as &$child) {
    $child['enrollments'] = [];

    $enrollments_sql = "SELECT e.enrollment_id, b.batch_id, b.batch_code, c.course_id, c.courseName, c.price
                        FROM course_enrollments e
                        JOIN batches b ON e.batch_id = b.batch_id
                        JOIN courses c ON b.course_id = c.course_id
                        WHERE e.student_id = ?";
    $stmt_enroll = $conn->prepare($enrollments_sql);
    $stmt_enroll->bind_param("i", $child['student_id']);
    $stmt_enroll->execute();
    $enroll_result = $stmt_enroll->get_result();
    $enrollments = $enroll_result->fetch_all(MYSQLI_ASSOC);

    foreach ($enrollments as $enroll) {
        $invoice_sql = "SELECT i.*, c.courseName, b.batch_code
                        FROM invoices i
                        JOIN course_enrollments e ON i.enrollment_id = e.enrollment_id
                        JOIN batches b ON e.batch_id = b.batch_id
                        JOIN courses c ON b.course_id = c.course_id
                        WHERE i.enrollment_id = ?
                        ORDER BY i.created_at DESC";
        $stmt_invoice = $conn->prepare($invoice_sql);
        $stmt_invoice->bind_param("i", $enroll['enrollment_id']);
        $stmt_invoice->execute();
        $invoice_result = $stmt_invoice->get_result();
        while ($invoice = $invoice_result->fetch_assoc()) {
            $child['enrollments'][] = $invoice;
            if ($invoice['status'] !== 'paid') {
                $total_due += floatval($invoice['amount']);
                $pending_count++;
            }
        }
    }
}
unset($child);

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $update_sql = "UPDATE invoices SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE invoice_id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("i", $invoice_id);
    if ($stmt_update->execute()) {
        $insert_payment = "INSERT INTO payments (invoice_id, amount, payer_user_id, payment_date) VALUES (?, ?, ?, NOW())";
        $stmt_payment = $conn->prepare($insert_payment);
        $stmt_payment->bind_param("idi", $invoice_id, $amount, $user_id);
        $stmt_payment->execute();
        $success = "Payment recorded successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Payment failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices - Parent Dashboard | <?= htmlspecialchars($school_name) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{
            --primary:#6366f1; --accent:#06b6d4; --bg:#f8fafc; --text:#1e293b;
            --muted:#64748b; --success:#10b981; --danger:#ef4444; --warning:#f59e0b;
        }
        body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; background:var(--bg); color:var(--text); margin:0; }
        .sidebar{ width:260px; position:fixed; top:0; left:0; bottom:0; background:linear-gradient(180deg,#1e293b,#334155); color:#fff; padding-bottom:40px; overflow:auto; }
        .main{ margin-left:260px; padding:24px; min-height:100vh; }
        .sidebar img{ width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,0.15); }
        .invoice-card{ background:#fff; border-radius:8px; padding:12px; border:1px solid #e8eef5; margin-bottom:12px; }
        .status-paid{ background:var(--success); color:#fff; padding:6px 8px; border-radius:6px; font-weight:600; }
        .status-pending{ background:var(--warning); color:#fff; padding:6px 8px; border-radius:6px; font-weight:600; }
        .status-overdue{ background:var(--danger); color:#fff; padding:6px 8px; border-radius:6px; font-weight:600; }
        .btn-pay{ background:linear-gradient(135deg,var(--success),#059669); color:#fff; border:none; }
        
        /* Print Styles */
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .sidebar, .main, .modal-header, .modal-footer, .no-print, button, .btn {
                display: none !important;
            }
            
            .modal-dialog {
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .modal-content {
                border: none !important;
                box-shadow: none !important;
            }
            
            .modal-body {
                padding: 0 !important;
            }
            
            #printableInvoice {
                display: block !important;
                position: relative !important;
                width: 100% !important;
                max-width: 100% !important;
                background: white !important;
                padding: 20px !important;
                margin: 0 !important;
            }
            
            .invoice-header {
                border-bottom: 3px solid #6366f1 !important;
                padding-bottom: 20px !important;
                margin-bottom: 30px !important;
            }
            
            .invoice-table {
                page-break-inside: avoid;
            }
            
            .invoice-footer {
                page-break-inside: avoid;
            }
        }
        
        /* Invoice Preview Styles */
        .invoice-preview {
            background: white;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .school-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        .school-details h2 {
            margin: 0;
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
        }
        
        .school-details p {
            margin: 2px 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .invoice-meta {
            text-align: right;
        }
        
        .invoice-meta h3 {
            margin: 0 0 10px 0;
            color: #6366f1;
            font-size: 28px;
            font-weight: 700;
        }
        
        .invoice-meta p {
            margin: 4px 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .invoice-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        .party-info h4 {
            margin: 0 0 10px 0;
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .party-info p {
            margin: 4px 0;
            color: #475569;
            font-size: 14px;
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
            font-weight: 600;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        
        .invoice-table tfoot td {
            font-weight: 700;
            font-size: 18px;
            color: #1e293b;
            border-top: 2px solid #e2e8f0;
            border-bottom: none;
        }
        
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.overdue {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar p-3" id="sidebar">
        <div class="text-center mb-3">
            <img src="<?= htmlspecialchars($parent['photo'] ?? 'default-parent-avatar.png') ?>" alt="Parent" onerror="this.src='default-avatar.png'">
            <h5 class="mt-2 mb-0"><?= htmlspecialchars($parent['firstName'] ?? 'Parent') ?></h5>
            <small class="text-muted d-block mb-2"><?= htmlspecialchars($parent['lastName'] ?? '') ?></small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="children.php"><i class="bi bi-people"></i> My Children</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="parent_view_attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="parent_view_performance.php"><i class="bi bi-graph-up"></i> Performance</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="parent_view_materials.php"><i class="bi bi-folder"></i> Materials</a></li>
            <li class="nav-item"><a class="nav-link text-white active" href="#"><i class="bi bi-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="parents_chatting.php"><i class="bi bi-chat"></i> Group Chat</a></li>
            <li class="nav-item mt-3"><a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main -->
    <main class="main">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h2>Fee Payments</h2>
                <p class="text-muted">Review invoices, print them or download as PDF.</p>
            </div>
            <div class="text-end">
                <div class="badge bg-primary">Total Due: LSL <?= number_format($total_due,2) ?></div>
                <div class="mt-1 text-muted">Pending invoices: <?= $pending_count ?></div>
                <?php if (!empty($children)): ?>
                    <button class="btn btn-success btn-sm mt-2" onclick="printCombinedStatement()">
                        <i class="bi bi-printer"></i> Print Combined Statement
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($children)): ?>
            <div class="text-center py-5">
                <i class="bi bi-person-x fs-1 text-muted"></i>
                <h4 class="mt-3">No children linked to your account</h4>
                <p class="text-muted">Add a child to manage invoices and payments.</p>
                <a href="children.php" class="btn btn-primary">Manage Children</a>
            </div>
        <?php else: ?>
            <?php foreach ($children as $child): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3 justify-content-between">
                        <div class="d-flex align-items-center">
                            <img src="<?= htmlspecialchars($child['student_photo'] ?? 'default-student.png') ?>" alt="student" style="width:64px;height:64px;border-radius:8px;object-fit:cover;margin-right:12px;">
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></h5>
                            </div>
                        </div>
                        <?php if (!empty($child['enrollments'])): ?>
                            <button class="btn btn-outline-primary btn-sm" 
                                    onclick='printStudentStatement(<?= htmlspecialchars(json_encode($child), ENT_QUOTES, 'UTF-8') ?>)'>
                                <i class="bi bi-printer"></i> Print Student Statement
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($child['enrollments'])): ?>
                        <div class="invoice-card text-center text-muted">
                            <i class="bi bi-receipt"></i>
                            <div>No invoices for this child yet.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($child['enrollments'] as $invoice): ?>
                            <?php
                                $status_label = ucfirst($invoice['status'] ?? 'pending');
                                $status_class = ($invoice['status'] === 'paid') ? 'status-paid' : (($invoice['status'] === 'overdue') ? 'status-overdue' : 'status-pending');
                            ?>
                            <div class="invoice-card d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($invoice['invoice_number'] ?? 'INV-') ?></h6>
                                    <div class="text-muted small">Course: <?= htmlspecialchars($invoice['courseName'] ?? 'N/A') ?> — Batch: <?= htmlspecialchars($invoice['batch_code'] ?? 'N/A') ?></div>
                                    <div class="mt-1"><strong>Amount:</strong> LSL <?= number_format($invoice['amount'],2) ?></div>
                                    <div class="small text-muted">Due: <?= date('M d, Y', strtotime($invoice['due_date'] ?? date('Y-m-d'))) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="<?= $status_class ?> mb-2"><?= $status_label ?></div>

                                    <?php if (($invoice['status'] ?? '') !== 'paid'): ?>
                                        <form method="POST" class="d-inline-block mb-2">
                                            <input type="hidden" name="invoice_id" value="<?= intval($invoice['invoice_id']) ?>">
                                            <input type="hidden" name="amount" value="<?= htmlspecialchars($invoice['amount']) ?>">
                                            <button type="submit" name="pay_invoice" class="btn btn-pay btn-sm" onclick="return confirm('Confirm payment of LSL <?= number_format($invoice['amount'],2) ?>?')">
                                                <i class="bi bi-credit-card"></i> Pay Now
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="mb-2"><span class="badge bg-success">Paid</span></div>
                                    <?php endif; ?>

                                    <div>
                                        <button class="btn btn-outline-primary btn-sm me-2"
                                            onclick='showInvoicePreview(<?= json_encode($invoice) ?>, <?= json_encode($child['firstName'] . " " . $child['lastName']) ?>)'>
                                            <i class="bi bi-eye"></i> Preview & Print
                                        </button>

                                        <a class="btn btn-outline-secondary btn-sm" target="_blank"
                                           href="download_invoice.php?invoice_id=<?= intval($invoice['invoice_id']) ?>">
                                            <i class="bi bi-file-earmark-pdf"></i> Download PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p class="text-center text-muted mt-4">For payment issues contact: <?= htmlspecialchars($school_email) ?></p>
    </main>

    <!-- Modal: Invoice Preview -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header no-print">
            <h5 class="modal-title">Invoice Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <div id="invoicePreviewContent"></div>
          </div>
          <div class="modal-footer no-print">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="printInvoiceBtn"><i class="bi bi-printer"></i> Print Invoice</button>
            <a id="downloadPdfLink" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
          </div>
        </div>
      </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showInvoicePreview(invoiceObj, studentName) {
    const school = {
        name: <?= json_encode($school_name) ?>,
        address: <?= json_encode($school_address) ?>,
        phone: <?= json_encode($school_phone) ?>,
        email: <?= json_encode($school_email) ?>,
        logo: <?= json_encode($school_logo) ?>
    };

    const parentName = <?= json_encode($parent['firstName'] . ' ' . $parent['lastName']) ?>;
    const invoice = invoiceObj;
    const statusClass = invoice.status === 'paid' ? 'paid' : (invoice.status === 'overdue' ? 'overdue' : 'pending');
    
    const html = `
        <div class="invoice-preview" id="printableInvoice">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="school-info">
                    <img src="${school.logo}" alt="School Logo" class="school-logo" onerror="this.style.display='none';">
                    <div class="school-details">
                        <h2>${school.name}</h2>
                        <p>${school.address}</p>
                        <p>Mohalalitoe, Leoka street</p>
                        <p>Phone: ${school.phone}</p>
                        <p>Email: ${school.email}</p>
                    </div>
                </div>
                <div class="invoice-meta">
                    <h3>INVOICE</h3>
                    <p><strong>Invoice #:</strong> ${invoice.invoice_number || 'N/A'}</p>
                    <p><strong>Date Issued:</strong> ${new Date(invoice.created_at || Date.now()).toLocaleDateString('en-GB')}</p>
                    <p><strong>Due Date:</strong> ${new Date(invoice.due_date || Date.now()).toLocaleDateString('en-GB')}</p>
                    <p><span class="status-badge ${statusClass}">${(invoice.status || 'pending').toUpperCase()}</span></p>
                </div>
            </div>

            <!-- Billing Information -->
            <div class="invoice-parties">
                <div class="party-info">
                    <h4>Bill To:</h4>
                    <p><strong>${parentName}</strong></p>
                    <p>Parent/Guardian</p>
                    <p>${school.phone}</p>
                    <p>${school.email}</p>
                </div>
                <div class="party-info">
                    <h4>Student Information:</h4>
                    <p><strong>${studentName}</strong></p>
                    <p><strong>Course:</strong> ${invoice.courseName || 'N/A'}</p>
                    <p><strong>Batch:</strong> ${invoice.batch_code || 'N/A'}</p>
                </div>
            </div>

            <!-- Invoice Items Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Description</th>
                        <th style="width: 20%; text-align: center;">Quantity</th>
                        <th style="width: 20%; text-align: right;">Amount (LSL)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Tuition Fee - ${invoice.courseName || 'Course'}</strong><br>
                            <small style="color: #64748b;">Batch: ${invoice.batch_code || 'N/A'}</small>
                        </td>
                        <td style="text-align: center;">1</td>
                        <td style="text-align: right;">${parseFloat(invoice.amount || 0).toFixed(2)}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align: right; padding-right: 20px;">TOTAL AMOUNT DUE:</td>
                        <td style="text-align: right; color: #6366f1;">LSL ${parseFloat(invoice.amount || 0).toFixed(2)}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Footer -->
            <div class="invoice-footer">
                <p><strong>Payment Instructions:</strong></p>
                <p>Please make payments via bank transfer, Mpesa, Ecocash or at the school office.</p>
                <p>For queries, contact us at ${school.phone} or ${school.email}</p>
                <p style="margin-top: 20px; font-style: italic;">Thank you for supporting your child's education at ${school.name}!</p>
            </div>
        </div>
    `;

    document.getElementById('invoicePreviewContent').innerHTML = html;
    document.getElementById('downloadPdfLink').href = 'download_invoice.php?invoice_id=' + encodeURIComponent(invoice.invoice_id || '');
    document.getElementById('downloadPdfLink').style.display = 'inline-block';

    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();

    document.getElementById('printInvoiceBtn').onclick = function() {
        window.print();
    };
}

function printStudentStatement(child) {
    const school = {
        name: <?= json_encode($school_name) ?>,
        address: <?= json_encode($school_address) ?>,
        phone: <?= json_encode($school_phone) ?>,
        email: <?= json_encode($school_email) ?>,
        logo: <?= json_encode($school_logo) ?>
    };

    const parentName = <?= json_encode($parent['firstName'] . ' ' . $parent['lastName']) ?>;
    const studentName = child.firstName + ' ' + child.lastName;
    
    let totalAmount = 0;
    let totalPaid = 0;
    let totalPending = 0;
    
    // Calculate totals
    child.enrollments.forEach(inv => {
        totalAmount += parseFloat(inv.amount || 0);
        if (inv.status === 'paid') {
            totalPaid += parseFloat(inv.amount || 0);
        } else {
            totalPending += parseFloat(inv.amount || 0);
        }
    });

    // Build invoice rows
    let invoiceRows = '';
    child.enrollments.forEach(inv => {
        const statusClass = inv.status === 'paid' ? 'paid' : (inv.status === 'overdue' ? 'overdue' : 'pending');
        invoiceRows += `
            <tr>
                <td>${inv.invoice_number || 'N/A'}</td>
                <td>${inv.courseName || 'N/A'}<br><small style="color: #64748b;">${inv.batch_code || ''}</small></td>
                <td style="text-align: center;">${new Date(inv.created_at || Date.now()).toLocaleDateString('en-GB')}</td>
                <td style="text-align: center;">${new Date(inv.due_date || Date.now()).toLocaleDateString('en-GB')}</td>
                <td style="text-align: right;">LSL ${parseFloat(inv.amount || 0).toFixed(2)}</td>
                <td style="text-align: center;"><span class="status-badge ${statusClass}">${(inv.status || 'pending').toUpperCase()}</span></td>
            </tr>
        `;
    });

    const html = `
        <div class="invoice-preview" id="printableInvoice">
            <div class="invoice-header">
                <div class="school-info">
                    <img src="${school.logo}" alt="School Logo" class="school-logo" onerror="this.style.display='none';">
                    <div class="school-details">
                        <h2>${school.name}</h2>
                        <p>${school.address}</p>
                        <p>Mohalalitoe, Leoka street</p>
                        <p>Phone: ${school.phone}</p>
                        <p>Email: ${school.email}</p>
                    </div>
                </div>
                <div class="invoice-meta">
                    <h3>STUDENT STATEMENT</h3>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-GB')}</p>
                    <p><strong>Student:</strong> ${studentName}</p>
                    <p><strong>Parent/Guardian:</strong> ${parentName}</p>
                </div>
            </div>

            <div style="margin: 30px 0;">
                <h4 style="color: #1e293b; margin-bottom: 20px;">Account Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div style="padding: 15px; background: #f1f5f9; border-radius: 8px;">
                        <div style="color: #64748b; font-size: 14px; margin-bottom: 5px;">Total Invoiced</div>
                        <div style="font-size: 24px; font-weight: 700; color: #1e293b;">LSL ${totalAmount.toFixed(2)}</div>
                    </div>
                    <div style="padding: 15px; background: #d1fae5; border-radius: 8px;">
                        <div style="color: #065f46; font-size: 14px; margin-bottom: 5px;">Total Paid</div>
                        <div style="font-size: 24px; font-weight: 700; color: #065f46;">LSL ${totalPaid.toFixed(2)}</div>
                    </div>
                    <div style="padding: 15px; background: #fef3c7; border-radius: 8px;">
                        <div style="color: #92400e; font-size: 14px; margin-bottom: 5px;">Total Outstanding</div>
                        <div style="font-size: 24px; font-weight: 700; color: #92400e;">LSL ${totalPending.toFixed(2)}</div>
                    </div>
                </div>
            </div>

            <h4 style="color: #1e293b; margin: 30px 0 15px 0;">Invoice History</h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Course/Batch</th>
                        <th style="text-align: center;">Issue Date</th>
                        <th style="text-align: center;">Due Date</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${invoiceRows}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right; padding-right: 20px;">TOTAL OUTSTANDING:</td>
                        <td style="text-align: right; color: #f59e0b;">LSL ${totalPending.toFixed(2)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="invoice-footer">
                <p><strong>Payment Instructions:</strong></p>
                <p>Please make payments via bank transfer, Mpesa, Ecocash or at the school office.</p>
                <p>For queries, contact us at ${school.phone} or ${school.email}</p>
                <p style="margin-top: 20px; font-style: italic;">Thank you for supporting your child's education at ${school.name}!</p>
            </div>
        </div>
    `;

    document.getElementById('invoicePreviewContent').innerHTML = html;
    document.getElementById('downloadPdfLink').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();

    document.getElementById('printInvoiceBtn').onclick = function() {
        window.print();
    };
}

function printCombinedStatement() {
    const school = {
        name: <?= json_encode($school_name) ?>,
        address: <?= json_encode($school_address) ?>,
        phone: <?= json_encode($school_phone) ?>,
        email: <?= json_encode($school_email) ?>,
        logo: <?= json_encode($school_logo) ?>
    };

    const parentName = <?= json_encode($parent['firstName'] . ' ' . $parent['lastName']) ?>;
    const allChildren = <?= json_encode($children) ?>;
    
    let grandTotalAmount = 0;
    let grandTotalPaid = 0;
    let grandTotalPending = 0;
    
    // Build sections for each child
    let childrenSections = '';
    
    allChildren.forEach(child => {
        const studentName = child.firstName + ' ' + child.lastName;
        let childTotal = 0;
        let childPaid = 0;
        let childPending = 0;
        
        // Calculate child totals
        child.enrollments.forEach(inv => {
            childTotal += parseFloat(inv.amount || 0);
            if (inv.status === 'paid') {
                childPaid += parseFloat(inv.amount || 0);
            } else {
                childPending += parseFloat(inv.amount || 0);
            }
        });
        
        grandTotalAmount += childTotal;
        grandTotalPaid += childPaid;
        grandTotalPending += childPending;
        
        // Build invoice rows for this child
        let invoiceRows = '';
        child.enrollments.forEach(inv => {
            const statusClass = inv.status === 'paid' ? 'paid' : (inv.status === 'overdue' ? 'overdue' : 'pending');
            invoiceRows += `
                <tr>
                    <td>${inv.invoice_number || 'N/A'}</td>
                    <td>${inv.courseName || 'N/A'}<br><small style="color: #64748b;">${inv.batch_code || ''}</small></td>
                    <td style="text-align: center;">${new Date(inv.created_at || Date.now()).toLocaleDateString('en-GB')}</td>
                    <td style="text-align: center;">${new Date(inv.due_date || Date.now()).toLocaleDateString('en-GB')}</td>
                    <td style="text-align: right;">LSL ${parseFloat(inv.amount || 0).toFixed(2)}</td>
                    <td style="text-align: center;"><span class="status-badge ${statusClass}">${(inv.status || 'pending').toUpperCase()}</span></td>
                </tr>
            `;
        });
        
        childrenSections += `
            <div style="margin: 40px 0; page-break-inside: avoid;">
                <h4 style="color: #1e293b; padding: 10px; background: #f1f5f9; border-radius: 8px; margin-bottom: 15px;">
                    Student: ${studentName}
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="padding: 12px; background: #f1f5f9; border-radius: 8px;">
                        <div style="color: #64748b; font-size: 13px;">Total Invoiced</div>
                        <div style="font-size: 18px; font-weight: 700; color: #1e293b;">LSL ${childTotal.toFixed(2)}</div>
                    </div>
                    <div style="padding: 12px; background: #d1fae5; border-radius: 8px;">
                        <div style="color: #065f46; font-size: 13px;">Paid</div>
                        <div style="font-size: 18px; font-weight: 700; color: #065f46;">LSL ${childPaid.toFixed(2)}</div>
                    </div>
                    <div style="padding: 12px; background: #fef3c7; border-radius: 8px;">
                        <div style="color: #92400e; font-size: 13px;">Outstanding</div>
                        <div style="font-size: 18px; font-weight: 700; color: #92400e;">LSL ${childPending.toFixed(2)}</div>
                    </div>
                </div>
                
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Course/Batch</th>
                            <th style="text-align: center;">Issue Date</th>
                            <th style="text-align: center;">Due Date</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${invoiceRows}
                    </tbody>
                </table>
            </div>
        `;
    });

    const html = `
        <div class="invoice-preview" id="printableInvoice">
            <div class="invoice-header">
                <div class="school-info">
                    <img src="${school.logo}" alt="School Logo" class="school-logo" onerror="this.style.display='none';">
                    <div class="school-details">
                        <h2>${school.name}</h2>
                        <p>${school.address}</p>
                        <p>Mohalalitoe, Leoka street</p>
                        <p>Phone: ${school.phone}</p>
                        <p>Email: ${school.email}</p>
                    </div>
                </div>
                <div class="invoice-meta">
                    <h3>COMBINED STATEMENT</h3>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-GB')}</p>
                    <p><strong>Parent/Guardian:</strong> ${parentName}</p>
                    <p><strong>Number of Students:</strong> ${allChildren.length}</p>
                </div>
            </div>

            <div style="margin: 30px 0;">
                <h4 style="color: #1e293b; margin-bottom: 20px;">Overall Account Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div style="padding: 20px; background: #f1f5f9; border-radius: 8px;">
                        <div style="color: #64748b; font-size: 14px; margin-bottom: 5px;">Total Invoiced</div>
                        <div style="font-size: 28px; font-weight: 700; color: #1e293b;">LSL ${grandTotalAmount.toFixed(2)}</div>
                    </div>
                    <div style="padding: 20px; background: #d1fae5; border-radius: 8px;">
                        <div style="color: #065f46; font-size: 14px; margin-bottom: 5px;">Total Paid</div>
                        <div style="font-size: 28px; font-weight: 700; color: #065f46;">LSL ${grandTotalPaid.toFixed(2)}</div>
                    </div>
                    <div style="padding: 20px; background: #fef3c7; border-radius: 8px;">
                        <div style="color: #92400e; font-size: 14px; margin-bottom: 5px;">Total Outstanding</div>
                        <div style="font-size: 28px; font-weight: 700; color: #92400e;">LSL ${grandTotalPending.toFixed(2)}</div>
                    </div>
                </div>
            </div>

            <h3 style="color: #1e293b; margin: 40px 0 20px 0;">Breakdown by Student</h3>
            ${childrenSections}

            <div class="invoice-footer">
                <p><strong>Payment Instructions:</strong></p>
                <p>Please make payments via bank transfer, Mpesa, Ecocash or at the school office.</p>
                <p>For queries, contact us at ${school.phone} or ${school.email}</p>
                <p style="margin-top: 20px; font-style: italic;">Thank you for supporting your children's education at ${school.name}!</p>
            </div>
        </div>
    `;

    document.getElementById('invoicePreviewContent').innerHTML = html;
    document.getElementById('downloadPdfLink').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();

    document.getElementById('printInvoiceBtn').onclick = function() {
        window.print();
    };
}
</script>
</body>
</html>