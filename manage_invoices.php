<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Check if role is admin
if ($_SESSION['role'] !== 'admin') {
    echo "<h2>Access Denied! You are not authorized to view this page.</h2>";
    exit();
}

// DB connection with error handling
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $invoice_id = (int)$_POST['invoice_id'];
        $new_status = $_POST['status'];
        
        $allowed_statuses = ['pending', 'paid', 'overdue', 'cancelled'];
        if (in_array($new_status, $allowed_statuses)) {
            $sql = "UPDATE invoices SET status = ? WHERE invoice_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $invoice_id);
            if ($stmt->execute()) {
                $success_msg = "Invoice status updated successfully!";
            } else {
                $error_msg = "Failed to update invoice status.";
            }
        }
    }
}

// Fetch all invoices with student details
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at DESC';

$sql = "
    SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status, 
           i.created_at, u.firstName, u.lastName, c.courseName, b.batch_code
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE 1=1
";

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $sql .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR i.invoice_number LIKE ?)";
}

if (!empty($filter_status)) {
    $sql .= " AND i.status = ?";
}

$sql .= " ORDER BY i.$sort_by LIMIT 100";

$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($filter_status)) {
    $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $filter_status);
} elseif (!empty($search)) {
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
} elseif (!empty($filter_status)) {
    $stmt->bind_param("s", $filter_status);
}

$stmt->execute();
$invoices_result = $stmt->get_result();

// Get summary stats
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) as overdue_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) as paid_amount
    FROM invoices
";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manage Invoices - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #4facfe;
            --danger: #fa709a;
            --warning: #ffa502;
            --info: #00d4ff;
            --dark: #1a1d29;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-top: 60px;
            color: #2d3748;
        }

        .content {
            padding: 2rem;
            min-height: calc(100vh - 60px);
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            border-top: 4px solid var(--primary);
        }

        .stat-card.pending { border-top-color: var(--warning); }
        .stat-card.paid { border-top-color: var(--success); }
        .stat-card.overdue { border-top-color: var(--danger); }

        .stat-label {
            font-size: 0.9rem;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
        }

        .stat-subtitle {
            font-size: 0.85rem;
            color: #a0aec0;
            margin-top: 0.5rem;
        }

        .filter-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-apply {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-reset {
            background: #f0f4f8;
            color: var(--dark);
            border: 2px solid var(--border);
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: var(--border);
        }

        .table-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.25rem;
            vertical-align: middle;
            border-color: var(--border);
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        .badge-pending {
            background-color: rgba(255, 165, 0, 0.2);
            color: var(--warning);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-paid {
            background-color: rgba(79, 172, 254, 0.2);
            color: var(--success);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-overdue {
            background-color: rgba(250, 112, 154, 0.2);
            color: var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-cancelled {
            background-color: rgba(160, 174, 192, 0.2);
            color: #718096;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-select {
            padding: 0.5rem;
            border-radius: 8px;
            border: 2px solid var(--border);
            background: white;
            cursor: pointer;
            font-weight: 600;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(79, 172, 254, 0.1);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(250, 112, 154, 0.1);
            color: var(--danger);
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-group {
                grid-template-columns: 1fr;
            }

            .table-section {
                padding: 1rem;
            }

            .table {
                font-size: 0.9rem;
            }

            .table thead th, .table tbody td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="bi bi-receipt"></i> Manage Invoices</h1>
        <a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Invoices</div>
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-subtitle">All invoices</div>
        </div>

        <div class="stat-card pending">
            <div class="stat-label">Pending Invoices</div>
            <div class="stat-value"><?= $stats['pending_count'] ?? 0 ?></div>
            <div class="stat-subtitle">M<?= number_format($stats['pending_amount'] ?? 0, 2) ?> awaiting payment</div>
        </div>

        <div class="stat-card paid">
            <div class="stat-label">Paid Invoices</div>
            <div class="stat-value"><?= $stats['paid_count'] ?? 0 ?></div>
            <div class="stat-subtitle">M<?= number_format($stats['paid_amount'] ?? 0, 2) ?> received</div>
        </div>

        <div class="stat-card overdue">
            <div class="stat-label">Overdue Invoices</div>
            <div class="stat-value"><?= $stats['overdue_count'] ?? 0 ?></div>
            <div class="stat-subtitle">Requires attention</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Amount</div>
            <div class="stat-value">M<?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
            <div class="stat-subtitle">All invoices combined</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <h3 class="filter-title"><i class="bi bi-funnel"></i> Filter Invoices</h3>
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <input type="text" name="search" class="form-control" placeholder="Search by student name or invoice number" value="<?= htmlspecialchars($search) ?>">
                
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <select name="sort" class="form-select">
                    <option value="created_at DESC" <?= $sort_by === 'created_at DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="created_at ASC" <?= $sort_by === 'created_at ASC' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="amount DESC" <?= $sort_by === 'amount DESC' ? 'selected' : '' ?>>Highest Amount</option>
                    <option value="amount ASC" <?= $sort_by === 'amount ASC' ? 'selected' : '' ?>>Lowest Amount</option>
                    <option value="due_date ASC" <?= $sort_by === 'due_date ASC' ? 'selected' : '' ?>>Due Soon</option>
                </select>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn-apply"><i class="bi bi-search"></i> Apply Filters</button>
                <a href="manage_invoices.php" class="btn-reset"><i class="bi bi-arrow-clockwise"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="table-section">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Student Name</th>
                        <th>Course / Batch</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                        <?php while ($invoice = $invoices_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td><?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></td>
                                <td><?= htmlspecialchars($invoice['courseName']) ?><br><small class="text-muted"><?= htmlspecialchars($invoice['batch_code']) ?></small></td>
                                <td><strong>M<?= number_format($invoice['amount'], 2) ?></strong></td>
                                <td><?= date("M d, Y", strtotime($invoice['due_date'])) ?></td>
                                <td>
                                    <span class="badge-<?= strtolower($invoice['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($invoice['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date("M d, Y", strtotime($invoice['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="">Change Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="paid">Paid</option>
                                            <option value="overdue">Overdue</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No invoices found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
