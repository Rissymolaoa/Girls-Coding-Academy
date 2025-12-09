<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) throw new Exception($conn->connect_error);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $invoice_id = (int)$_POST['invoice_id'];
    $new_status = $_POST['status'];
    $allowed = ['pending', 'paid', 'overdue', 'cancelled'];
    if (in_array($new_status, $allowed)) {
        $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE invoice_id = ?");
        $stmt->bind_param("si", $new_status, $invoice_id);
        $stmt->execute();
        $success_msg = "Invoice updated to <strong>" . ucfirst($new_status) . "</strong>";
    }
}

// Filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at DESC';

$sql = "
    SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status, i.created_at,
           u.firstName, u.lastName, c.courseName, b.batch_code
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $like = "%$search%";
    $sql .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR i.invoice_number LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

if ($filter_status !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql .= " ORDER BY $sort_by";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result();

// Stats
$stats = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) as overdue_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) as paid_amount
    FROM invoices
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invoices • Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(to bottom right, #f8fafc, #e0e7ff);
            margin: 0;
            padding: 0;
        }
        /* Critical Fix: Push content below fixed top navigation */
        .main-content {
            margin-left: 280px;           /* your sidebar width */
            padding-top: 100px;           /* height of your top navigation */
            min-height: 100vh;
            padding-left: 2rem;
            padding-right: 2rem;
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding-top: 90px; }
        }
        .card { transition: all 0.3s ease; }
        .card:hover { transform: translateY(-8px); }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending   { background: #fff7ed; color: #9a3412; }
        .status-paid      { background: #ecfdf5; color: #065f46; }
        .status-overdue   { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body class="min-h-screen">

    <!-- Your existing top navigation -->
    <?php include 'top_navigation.php'; ?>
    
    <!-- Your existing sidebar -->
    <?php include 'admin_navigation.php'; ?>

    <!-- MAIN CONTENT - NOW PERFECTLY VISIBLE -->
    <div class="main-content">

        <!-- Header -->
        <div class="mb-10 text-center">
            <h1 class="text-4xl font-extrabold bg-gradient-to-r from-indigo-600 to-pink-600 bg-clip-text text-transparent">
                Manage Invoices
            </h1>
            <p class="text-gray-600 mt-3">Track payments and update invoice statuses</p>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 max-w-4xl mx-auto">
                <i class="bi bi-check-circle-fill text-2xl"></i>
                <span class="font-medium"><?= $success_msg ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-10 max-w-7xl mx-auto">
            <div class="card bg-gradient-to-br from-slate-800 to-slate-900 text-white p-6 rounded-2xl shadow-xl">
                <p class="text-slate-300 text-sm font-medium">Total Invoices</p>
                <p class="text-4xl font-black mt-2"><?= $stats['total'] ?? 0 ?></p>
            </div>
            <div class="card bg-gradient-to-br from-amber-500 to-orange-600 text-white p-6 rounded-2xl shadow-xl">
                <p class="text-amber-100 text-sm font-medium">Pending</p>
                <p class="text-4xl font-black mt-2"><?= $stats['pending_count'] ?? 0 ?></p>
                <p class="text-amber-100 text-xs mt-1">M<?= number_format($stats['pending_amount'] ?? 0, 2) ?></p>
            </div>
            <div class="card bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-6 rounded-2xl shadow-xl">
                <p class="text-emerald-100 text-sm font-medium">Paid</p>
                <p class="text-4xl font-black mt-2"><?= $stats['paid_count'] ?? 0 ?></p>
                <p class="text-emerald-100 text-xs mt-1">M<?= number_format($stats['paid_amount'] ?? 0, 2) ?></p>
            </div>
            <div class="card bg-gradient-to-br from-rose-500 to-pink-600 text-white p-6 rounded-2xl shadow-xl">
                <p class="text-rose-100 text-sm font-medium">Overdue</p>
                <p class="text-4xl font-black mt-2"><?= $stats['overdue_count'] ?? 0 ?></p>
            </div>
            <div class="card bg-gradient-to-br from-indigo-600 to-purple-700 text-white p-6 rounded-2xl shadow-xl">
                <p class="text-indigo-100 text-sm font-medium">Total Revenue</p>
                <p class="text-4xl font-black mt-2">M<?= number_format($stats['total_amount'] ?? 0, 2) ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 max-w-7xl mx-auto">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                Filter & Search
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search student or invoice..." 
                       class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 outline-none">

                <select name="status" class="px-4 py-3 border border-gray-300 rounded-xl">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
                    <option value="paid" <?= $filter_status==='paid'?'selected':'' ?>>Paid</option>
                    <option value="overdue" <?= $filter_status==='overdue'?'selected':'' ?>>Overdue</option>
                    <option value="cancelled" <?= $filter_status==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>

                <select name="sort" class="px-4 py-3 border border-gray-300 rounded-xl">
                    <option value="created_at DESC">Newest First</option>
                    <option value="created_at ASC">Oldest First</option>
                    <option value="amount DESC">Highest Amount</option>
                    <option value="due_date ASC">Due Soonest</option>
                </select>

                <div class="flex gap-3">
                    <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:shadow-lg transition">
                        Apply
                    </button>
                    <a href="manage_invoices.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-300 transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Invoices Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden max-w-7xl mx-auto">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                        <tr>
                            <th class="px-6 py-5 text-left">Invoice #</th>
                            <th class="px-6 py-5 text-left">Student</th>
                            <th class="px-6 py-5 text-left">Course / Batch</th>
                            <th class="px-6 py-5 text-left">Amount</th>
                            <th class="px-6 py-5 text-left">Due Date</th>
                            <th class="px-6 py-5 text-left">Status</th>
                            <th class="px-6 py-5 text-left">Created</th>
                            <th class="px-6 py-5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($invoices->num_rows === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-16 text-gray-500">
                                    <i class="bi bi-receipt text-6xl mb-4 opacity-30"></i>
                                    <p class="text-xl">No invoices found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-5 font-bold text-indigo-600">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                </td>
                                <td class="px-6 py-5">
                                    <?= htmlspecialchars($inv['firstName'] . ' ' . $inv['lastName']) ?>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium"><?= htmlspecialchars($inv['courseName']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($inv['batch_code']) ?></div>
                                </td>
                                <td class="px-6 py-5 font-bold text-xl">
                                    M<?= number_format($inv['amount'], 2) ?>
                                </td>
                                <td class="px-6 py-5 <?= strtotime($inv['due_date']) < time() && $inv['status']!='paid' ? 'text-red-600 font-bold' : '' ?>">
                                    <?= date('M d, Y', strtotime($inv['due_date'])) ?>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="status-badge status-<?= $inv['status'] ?>">
                                        <?= ucfirst($inv['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-gray-600">
                                    <?= date('M d, Y', strtotime($inv['created_at'])) ?>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                                        <select name="status" onchange="this.form.submit()" 
                                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500">
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>