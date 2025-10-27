<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch parent details for sidebar
$parent_sql = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();

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
    $children_sql = "SELECT s.student_id, u.firstName, u.lastName,s.student_number, s.photo as student_photo
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
$all_invoices = [];
$total_due = 0;
$pending_count = 0;
foreach ($children as &$child) {
    $enrollments_sql = "SELECT e.enrollment_id, b.batch_id, c.courseName, c.price
                        FROM course_enrollments e
                        JOIN batches b ON e.batch_id = b.batch_id
                        JOIN courses c ON b.course_id = c.course_id
                        WHERE e.student_id = ?";
    $stmt_enroll = $conn->prepare($enrollments_sql);
    $stmt_enroll->bind_param("i", $child['student_id']);
    $stmt_enroll->execute();
    $enroll_result = $stmt_enroll->get_result();
    $enrollments = $enroll_result->fetch_all(MYSQLI_ASSOC);

    $child['enrollments'] = [];
    foreach ($enrollments as $enroll) {
        // Get invoice
        $invoice_sql = "SELECT * FROM invoices WHERE enrollment_id = ?";
        $stmt_invoice = $conn->prepare($invoice_sql);
        $stmt_invoice->bind_param("i", $enroll['enrollment_id']);
        $stmt_invoice->execute();
        $invoice_result = $stmt_invoice->get_result();
        $invoice = $invoice_result->fetch_assoc();

        if ($invoice) {
            $status_class = $invoice['status'] === 'paid' ? 'success' : 
                           ($invoice['status'] === 'overdue' ? 'danger' : 'warning');
            $invoice['status_class'] = $status_class;
            $child['enrollments'][] = $invoice;
            if ($invoice['status'] !== 'paid') {
                $total_due += $invoice['amount'];
                $pending_count++;
            }
        }
    }
}

// Handle payment (simulate by updating status to paid)
if (isset($_POST['pay_invoice'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $update_sql = "UPDATE invoices SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE invoice_id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("i", $invoice_id);
    if ($stmt_update->execute()) {
        // Optionally insert into payments
        $amount = floatval($_POST['amount']);
        $payer_id = $user_id;
        $insert_payment = "INSERT INTO payments (invoice_id, amount, payer_user_id) VALUES (?, ?, ?)";
        $stmt_payment = $conn->prepare($insert_payment);
        $stmt_payment->bind_param("idi", $invoice_id, $amount, $payer_id);
        $stmt_payment->execute();
        $success = "Payment recorded successfully!";
        // Refresh page
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payments - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
            object-fit: cover;
        }
        .sidebar h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            border-radius: 0 20px 20px 0;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            font-size: 1.1rem;
        }
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 1.1rem;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .summary-card:hover {
            transform: translateY(-4px);
        }
        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .summary-card.total-due .summary-icon { color: var(--danger-color); }
        .summary-card.pending .summary-icon { color: var(--warning-color); }
        .summary-card.paid .summary-icon { color: var(--success-color); }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .children-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .child-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
        }
        .child-body {
            padding: 1.5rem;
        }
        .child-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .child-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .invoice-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f9f9f9;
        }
        .invoice-status {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-paid { background: var(--success-color); color: white; }
        .status-pending { background: var(--warning-color); color: white; }
        .status-overdue { background: var(--danger-color); color: white; }
        .btn-pay {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-pay:hover {
            transform: translateY(-1px);
        }
        .no-invoices {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        .no-child {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            z-index: 1001;
            position: fixed;
            top: 1rem;
            left: 1rem;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .page-header h1 { font-size: 1.5rem; }
            .summary-grid { grid-template-columns: 1fr; }
            .child-info { flex-direction: column; text-align: center; }
        }
        @media (max-width: 768px) {
            .toggle-sidebar { display: block; }
        }
    </style>
</head>
<body>
    <button class="toggle-sidebar" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= $parent['photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
            </li>
            <li class="nav-item">
                <a href="parents_chatting.php" class="nav-link"><i class="bi bi-chat"></i> Group Chat</a>
            </li>
            <li class="nav-item">
                <a href="parent_payments.php" class="nav-link active"><i class="bi bi-credit-card"></i> Payments</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <header class="page-header">
            <div>
                <h1>Fee Payments</h1>
                <p>Manage tuition fees for your children's courses. View outstanding balances, due dates, and complete payments securely.</p>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <section class="summary-section">
            <div class="summary-grid">
                <div class="summary-card total-due">
                    <div class="summary-icon"><i class="bi bi-currency-dollar"></i></div>
                    <div class="summary-value">LSL <?= number_format($total_due, 2) ?></div>
                    <p class="summary-label">Total Due</p>
                </div>
                <div class="summary-card pending">
                    <div class="summary-icon"><i class="bi bi-clock"></i></div>
                    <div class="summary-value"><?= $pending_count ?></div>
                    <p class="summary-label">Pending Invoices</p>
                </div>
                <div class="summary-card paid">
                    <div class="summary-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="summary-value"><?= count($children) - $pending_count ?></div>
                    <p class="summary-label">Paid This Term</p>
                </div>
            </div>
        </section>

        <?php if (empty($children)): ?>
            <div class="no-child">
                <i class="bi bi-person-x"></i>
                <h3>No Children Enrolled</h3>
                <p>Link a child to your account to manage payments.</p>
                <a href="children.php" class="btn btn-primary">Manage Children</a>
            </div>
        <?php else: ?>
            <!-- Children Payments -->
            <section class="children-section">
                <div class="child-header">
                    <h5><i class="bi bi-people"></i> Payments by Child</h5>
                </div>
                <div class="child-body">
                    <?php foreach ($children as $child): ?>
                        <div class="child-info mb-4">
                            <img src="<?= $child['student_photo'] ?? 'default-student.png' ?>" alt="Child Photo" class="child-photo">
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></h6>
                                <small class="text-muted">Student ID: <?= $child['student_number'] ?></small>
                            </div>
                        </div>

                        <?php if (empty($child['enrollments'])): ?>
                            <div class="no-invoices">
                                <i class="bi bi-receipt"></i>
                                <p>No invoices for this child yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($child['enrollments'] as $invoice): ?>
                                <div class="invoice-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($invoice['invoice_number']) ?></h6>
                                            <p class="mb-1 text-muted">Course: <?= $invoice['course_name'] ?? 'N/A' ?></p>
                                            <p class="mb-1"><strong>Amount:</strong> LSL <?= number_format($invoice['amount'], 2) ?></p>
                                            <p class="mb-0"><strong>Due Date:</strong> <?= date('M d, Y', strtotime($invoice['due_date'])) ?></p>
                                        </div>
                                        <span class="invoice-status status-<?= $invoice['status_class'] ?> ms-2"><?= ucfirst($invoice['status']) ?></span>
                                    </div>

                                    <?php if ($invoice['status'] !== 'paid'): ?>
                                        <form method="POST" class="mt-2 d-inline">
                                            <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                                            <input type="hidden" name="amount" value="<?= $invoice['amount'] ?>">
                                            <button type="submit" name="pay_invoice" class="btn btn-pay" onclick="return confirm('Confirm payment of L<?= number_format($invoice['amount'], 2) ?>?')">
                                                <i class="bi bi-credit-card"></i> Pay Now
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success ms-2">Paid</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-muted">Payments are processed securely. For payment issues, contact admin@girlscoding.com.</small>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                document.getElementById('main-content').classList.remove('expanded');
            }
        });
    </script>
</body>
</html>