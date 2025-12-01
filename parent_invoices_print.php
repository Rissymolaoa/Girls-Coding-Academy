<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}
include("db.php");

$error = $success = "";
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
$parent_record_id = $parent_record['parent_id'] ?? 0;

// Fetch children + invoices
$children = [];
$total_due = $pending_count = 0;

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

    foreach ($children as &$child) {
        $child['enrollments'] = [];
        $enroll_sql = "SELECT e.enrollment_id, b.batch_id, b.batch_code, c.course_id, c.courseName, c.price
                       FROM course_enrollments e
                       JOIN batches b ON e.batch_id = b.batch_id
                       JOIN courses c ON b.course_id = c.course_id
                       WHERE e.student_id = ?";
        $stmt_en = $conn->prepare($enroll_sql);
        $stmt_en->bind_param("i", $child['student_id']);
        $stmt_en->execute();
        $res = $stmt_en->get_result();

        while ($en = $res->fetch_assoc()) {
            $inv_sql = "SELECT i.*, c.courseName, b.batch_code
                        FROM invoices i
                        JOIN course_enrollments e ON i.enrollment_id = e.enrollment_id
                        JOIN batches b ON e.batch_id = b.batch_id
                        JOIN courses c ON b.course_id = c.course_id
                        WHERE i.enrollment_id = ? ORDER BY i.created_at DESC";
            $stmt_inv = $conn->prepare($inv_sql);
            $stmt_inv->bind_param("i", $en['enrollment_id']);
            $stmt_inv->execute();
            $inv_res = $stmt_inv->get_result();
            while ($inv = $inv_res->fetch_assoc()) {
                $child['enrollments'][] = $inv;
                if (($inv['status'] ?? 'pending') !== 'paid') {
                    $total_due += (float)$inv['amount'];
                    $pending_count++;
                }
            }
        }
    }
    unset($child);
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $amount = (float)$_POST['amount'];

    $upd = $conn->prepare("UPDATE invoices SET status='paid', updated_at=NOW() WHERE invoice_id=?");
    if ($upd->bind_param("i", $invoice_id) && $upd->execute()) {
        $pay = $conn->prepare("INSERT INTO payments (invoice_id, amount, payer_user_id, payment_date) VALUES (?, ?, ?, NOW())");
        $pay->bind_param("idi", $invoice_id, $amount, $user_id);
        $pay->execute();
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments & Invoices | <?= htmlspecialchars($school_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #f8fafc;
            --text: #1e293b;
            --text-light: #64748b;
            --sidebar-bg: rgba(30, 41, 59, 0.95);
            --sidebar-hover: rgba(255,255,255,0.1);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin:0; }
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 270px;
            background: var(--sidebar-bg);
            backdrop-filter: blur(12px);
            color: #fff;
            z-index: 1000;
            transition: transform 0.3s ease;
            padding: 1.5rem 0;
        }
        .sidebar.collapsed { transform: translateX(-100%); }
        .main-content { margin-left: 270px; padding: 2rem; min-height: 100vh; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar-header img {
            width: 80px; height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.15);
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            margin: 0.25rem 1rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }
        .nav-link:hover { background: var(--sidebar-hover); color: #fff; }
        .nav-link.active {
            background: rgba(99, 102, 241, 0.25);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }
        .nav-link i { width: 24px; margin-right: 12px; font-size: 1.1rem; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 25px rgba(0,0,0,0.06); overflow: hidden; }
        .invoice-item {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .invoice-item:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .status-badge {
            padding: 6px 14px;
            border-radius: 8px;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid { background: var(--success); }
        .status-pending { background: var(--warning); }
        .status-overdue { background: var(--danger); }
        .btn-pay {
            background: linear-gradient(135deg, var(--success), #059669);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-pay:hover { background: linear-gradient(135deg, #059669, #047857); }

        /* Print Styles */
        @media print {
            .sidebar, .no-print, button, .btn { display: none !important; }
            body, .main-content { margin: 0 !important; padding: 20px !important; background: white !important; }
            #printableInvoice { display: block !important; width: 100%; }
        }

        /* Invoice Preview */
        .invoice-preview { background: white; padding: 40px; max-width: 900px; margin: 0 auto; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        .school-logo { width: 80px; height: 80px; object-fit: contain; }
        .invoice-meta h3 { font-size: 28px; color: var(--primary); margin: 0 0 10px; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.overdue { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= htmlspecialchars($parent['photo'] ?? 'default-parent-avatar.png') ?>" alt="Parent" onerror="this.src='default-avatar.png'">
        <h5 class="mt-3 mb-0"><?= htmlspecialchars($parent['firstName'] ?? 'Parent') ?></h5>
        <small class="text-white-50"><?= htmlspecialchars($parent['lastName'] ?? '') ?></small>
    </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link" onclick="showSection('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link" onclick="showSection('children')"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link" target="_blank"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link" target="_blank"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link" target="_blank"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link" target="_blank"><i class="bi bi-envelope"></i> Messages</a>
            </li>
       
            <li class="nav-item">
                <a href="parent_profile.php" class="nav-link" onclick="showSection('profile')"><i class="bi bi-person-circle"></i> Profile</a>
            </li>
            <li class="nav-item">
                <a href="parent_payments.php" class="nav-link "><i class="bi bi-credit-card"></i> Payments</a>
            </li>
             <li class="nav-item">
                <a href="parent_invoices_print.php" class="nav-link active"><i class="bi bi-credit-card"></i> Invoices</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
</nav>

<!-- Mobile Menu Toggle -->
<button class="btn btn-primary d-lg-none position-fixed top-0 start-0 m-3 z-2000 rounded-circle shadow-lg" id="menuToggle">
    <i class="bi bi-list fs-3"></i>
</button>

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Fee Payments & Invoices</h2>
            <p class="text-muted">View, pay, print or download all invoices</p>
        </div>
        <div class="text-end">
            <h4 class="mb-1 text-danger fw-bold">Total Due: LSL <?= number_format($total_due, 2) ?></h4>
            <small class="text-muted"><?= $pending_count ?> pending invoice<?= $pending_count != 1 ? 's' : '' ?></small>
            <?php if (!empty($children)): ?>
                <button class="btn btn-success ms-3 mt-2" onclick="printCombinedStatement()">
                    <i class="bi bi-printer"></i> Print Combined Statement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> <?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <?php if (empty($children)): ?>
        <div class="text-center py-5">
            <i class="bi bi-person-x fs-1 text-muted"></i>
            <h4 class="mt-4 text-muted">No children linked to your account</h4>
            <a href="children.php" class="btn btn-primary mt-3">Link a Child</a>
        </div>
    <?php else: ?>
        <?php foreach ($children as $child): ?>
            <div class="card mb-4">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between py-3">
                    <div class="d-flex align-items-center">
                        <img src="<?= htmlspecialchars($child['student_photo'] ?? 'default-student.png') ?>" class="rounded-circle me-3" width="64" height="64" alt="Student">
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></h5>
                            <small class="text-muted">Student</small>
                        </div>
                    </div>
                    <?php if (!empty($child['enrollments'])): ?>
                        <button class="btn btn-outline-primary btn-sm" onclick='printStudentStatement(<?= json_encode($child) ?>)'>
                            <i class="bi bi-printer"></i> Print Statement
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body pt-0">
                    <?php if (empty($child['enrollments'])): ?>
                        <p class="text-center text-muted py-4"><i class="bi bi-receipt"></i> No invoices yet.</p>
                    <?php else: ?>
                        <?php foreach ($child['enrollments'] as $inv):
                            $status = $inv['status'] ?? 'pending';
                            $badge_class = $status === 'paid' ? 'status-paid' : ($status === 'overdue' ? 'status-overdue' : 'status-pending');
                        ?>
                            <div class="invoice-item d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="flex-grow-1">
                                    <strong class="d-block mb-1"><?= $inv['invoice_number'] ?? 'INV-' . $inv['enrollment_id'] ?></strong>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($inv['courseName'] ?? 'Course') ?> — <?= htmlspecialchars($inv['batch_code'] ?? '') ?>
                                    </small>
                                    <div class="mt-2">
                                        <strong>LSL <?= number_format($inv['amount'], 2) ?></strong>
                                        <span class="text-muted ms-3">Due: <?= date('d M Y', strtotime($inv['due_date'])) ?></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="status-badge <?= $badge_class ?> mb-2"><?= strtoupper($status) ?></div>
                                    <?php if ($status !== 'paid'): ?>
                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                                            <input type="hidden" name="amount" value="<?= $inv['amount'] ?>">
                                            <button name="pay_invoice" class="btn btn-pay btn-sm" onclick="return confirm('Confirm payment of LSL <?= number_format($inv['amount'], 2) ?>?')">
                                                <i class="bi bi-credit-card"></i> Pay Now
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <button class="btn btn-outline-primary btn-sm me-1" onclick='showInvoicePreview(<?= json_encode($inv) ?>, <?= json_encode($child["firstName"]." ".$child["lastName"]) ?>)'>
                                            <i class="bi bi-eye"></i> Preview
                                        </button>
                                        <a href="download_invoice.php?invoice_id=<?= $inv['invoice_id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-file-earmark-pdf"></i> PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- Invoice Preview Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
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
                <button type="button" class="btn btn-primary" id="printInvoiceBtn"><i class="bi bi-printer"></i> Print</button>
                <a id="downloadPdfLink" href="#" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Download PDF</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile menu toggle
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});

// Show single invoice
function showInvoicePreview(invoice, studentName) {
    const school = { name: "<?= $school_name ?>", address: "<?= $school_address ?>", phone: "<?= $school_phone ?>", email: "<?= $school_email ?>", logo: "<?= $school_logo ?>" };
    const parentName = "<?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?>";
    const statusClass = invoice.status === 'paid' ? 'paid' : (invoice.status === 'overdue' ? 'overdue' : 'pending');

    const html = `
        <div class="invoice-preview" id="printableInvoice">
            <div class="invoice-header">
                <div class="d-flex align-items-center gap-4">
                    <img src="${school.logo}" alt="Logo" class="school-logo" onerror="this.style.display='none'">
                    <div>
                        <h2>${school.name}</h2>
                        <p>${school.address}<br>Mohalalitoe, Leoka street<br>Phone: ${school.phone}<br>Email: ${school.email}</p>
                    </div>
                </div>
                <div class="text-end">
                    <h3>INVOICE</h3>
                    <p><strong>#:</strong> ${invoice.invoice_number || 'N/A'}</p>
                    <p><strong>Issued:</strong> ${new Date(invoice.created_at).toLocaleDateString()}</p>
                    <p><strong>Due:</strong> ${new Date(invoice.due_date).toLocaleDateString()}</p>
                    <span class="status-badge ${statusClass}">${(invoice.status || 'pending').toUpperCase()}</span>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-6">
                    <h5>Bill To:</h5>
                    <p><strong>${parentName}</strong><br>Parent/Guardian</p>
                </div>
                <div class="col-6">
                    <h5>Student:</h5>
                    <p><strong>${studentName}</strong><br>${invoice.courseName}<br>Batch: ${invoice.batch_code}</p>
                </div>
            </div>
            <table class="table table-bordered">
                <thead class="table-light"><tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Tuition Fee - ${invoice.courseName}<br><small>Batch: ${invoice.batch_code}</small></td>
                        <td class="text-center">1</td>
                        <td class="text-end">LSL ${parseFloat(invoice.amount).toFixed(2)}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="2" class="text-end fw-bold">TOTAL DUE:</td><td class="text-end fw-bold text-primary">LSL ${parseFloat(invoice.amount).toFixed(2)}</td></tr>
                </tfoot>
            </table>
            <div class="text-center mt-5 text-muted">
                <p>Thank you for choosing ${school.name}!</p>
            </div>
        </div>`;
    document.getElementById('invoicePreviewContent').innerHTML = html;
    document.getElementById('downloadPdfLink').href = 'download_invoice.php?invoice_id=' + (invoice.invoice_id || '');
    new bootstrap.Modal(document.getElementById('invoiceModal')).show();
    document.getElementById('printInvoiceBtn').onclick = () => window.print();
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