<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); 
    exit();
}
require_once 'db.php';

// Check if this is the first page load for admin in this session
$isFirstLogin = !isset($_SESSION['dashboard_viewed']);
if ($isFirstLogin) {
    $_SESSION['dashboard_viewed'] = true;
}

function safeCount($conn, $sql) {
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? (int)$row['count'] : 0;
}

// Summary Stats
$total_students = safeCount($conn, "SELECT COUNT(*) as count FROM students");
$total_teachers = safeCount($conn, "SELECT COUNT(*) as count FROM teachers");
$total_parents = safeCount($conn, "SELECT COUNT(*) as count FROM parents");
$total_courses = safeCount($conn, "SELECT COUNT(*) as count FROM courses");
$active_batches = safeCount($conn, "SELECT COUNT(*) as count FROM batches WHERE status='active'");
$active_enrollments = safeCount($conn, "SELECT COUNT(*) as count FROM course_enrollments WHERE status='active'");
$pending_invoices = safeCount($conn, "SELECT COUNT(*) as count FROM invoices WHERE status='pending'");
$upcoming_events = safeCount($conn, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE() AND is_posted=1");
$avg_attendance = safeCount($conn, "SELECT ROUND(AVG(CASE WHEN status='Present' THEN 100 ELSE 0 END), 1) as count FROM attendance WHERE session_id >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

// Recent Enrollments
$recent_enrollments = $conn->query("
    SELECT u.firstName, u.lastName, c.courseName, b.batch_code, ce.enrolled_at
    FROM course_enrollments ce
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ce.status = 'active'
    ORDER BY ce.enrolled_at DESC
    LIMIT 8
");

// Recent Payments (for modal)
$recent_payments = [];
$payments_result = $conn->query("
    SELECT p.amount, p.payment_date, p.payment_method,
           u.firstName, u.lastName, c.courseName
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY p.payment_date DESC
    LIMIT 10
");
if ($payments_result) {
    while ($row = $payments_result->fetch_assoc()) {
        $recent_payments[] = $row;
    }
}

// Upcoming Events
$events_result = $conn->query("
    SELECT title, description, event_date, event_time_start, location
    FROM events
    WHERE event_date >= CURDATE() AND is_posted = 1
    ORDER BY event_date, event_time_start
    LIMIT 5
");

// Recent Announcements
$announcements_result = $conn->query("
    SELECT message, recipients, created_at
    FROM admin_announcements
    ORDER BY created_at DESC
    LIMIT 4
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f0f7ff 0%, #e8f2ff 100%); }
        .card-summary {
            background: white;
            border-left: 5px solid #3b82f6;
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.15);
        }
        .btn-action {
            background: #3b82f6;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }
        .quick-link {
            background: white;
            border: 1px solid #e5e7eb;
            color: #1f2937;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .quick-link:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.1);
        }
        .quick-link:hover {
            border-color: currentColor;
        }
        .quick-link:hover i {
            transform: scale(1.1);
        }
        .quick-link i {
            transition: transform 0.3s ease;
        }
        .stats-card {
            background: white;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);
        }
        .table-row:hover {
            background: #f0f7ff;
        }
        .modal-backdrop { background: rgba(0, 0, 0, 0.5); }
    </style>
</head>
<body>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">

        <!-- Header Section -->
        <div class="mb-12">
            <h1 class="text-4xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600 mt-2"><?= date('l, F j, Y') ?></p>
        </div>

        <!-- Top Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="card-summary rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Students</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= $total_students ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="card-summary rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Active Enrollments</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= $active_enrollments ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <i class="fas fa-clipboard-list text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="card-summary rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Pending Invoices</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= $pending_invoices ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <i class="fas fa-file-invoice-dollar text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="card-summary rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Active Batches</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= $active_batches ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <i class="fas fa-layer-group text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions - Categorized -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Quick Actions</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- People Management -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-blue-100 border-b border-gray-200">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-users text-blue-600"></i>
                            People Management
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="manage_students.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-blue-50 border border-blue-100">
                            <i class="fas fa-user-graduate text-2xl text-blue-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Students</span>
                                <p class="text-xs text-gray-600">Manage student records</p>
                            </div>
                        </a>
                        <a href="manage_teachers.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-cyan-50 border border-cyan-100">
                            <i class="fas fa-chalkboard-teacher text-2xl text-cyan-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Teachers</span>
                                <p class="text-xs text-gray-600">Manage teacher profiles</p>
                            </div>
                        </a>
                        <a href="manage_parents.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-indigo-50 border border-indigo-100">
                            <i class="fas fa-user-tie text-2xl text-indigo-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Parents</span>
                                <p class="text-xs text-gray-600">Manage parent accounts</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Academic Management -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-green-100 border-b border-gray-200">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-book text-green-600"></i>
                            Academic Management
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="manage_courses.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-green-50 border border-green-100">
                            <i class="fas fa-book-open text-2xl text-green-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Courses</span>
                                <p class="text-xs text-gray-600">Create & manage courses</p>
                            </div>
                        </a>
                        <a href="add_batch.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-emerald-50 border border-emerald-100">
                            <i class="fas fa-calendar-week text-2xl text-emerald-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Batches</span>
                                <p class="text-xs text-gray-600">Manage course batches</p>
                            </div>
                        </a>
                        <a href="view_attendance.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-teal-50 border border-teal-100">
                            <i class="fas fa-clipboard-list text-2xl text-teal-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Attendance</span>
                                <p class="text-xs text-gray-600">Track attendance records</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Finance & Communication -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-amber-50 to-amber-100 border-b border-gray-200">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-cog text-amber-600"></i>
                            Finance & Communication
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="manage_invoices.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-amber-50 border border-amber-100">
                            <i class="fas fa-receipt text-2xl text-amber-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Invoices</span>
                                <p class="text-xs text-gray-600">View & manage invoices</p>
                            </div>
                        </a>
                        <a href="admin_announcements.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-rose-50 border border-rose-100">
                            <i class="fas fa-bullhorn text-2xl text-rose-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Announcements</span>
                                <p class="text-xs text-gray-600">Send announcements</p>
                            </div>
                        </a>
                        <a href="events.php" class="quick-link rounded-lg p-4 text-left shadow-sm flex items-center gap-4 bg-orange-50 border border-orange-100">
                            <i class="fas fa-calendar-check text-2xl text-orange-600 flex-shrink-0"></i>
                            <div>
                                <span class="font-semibold text-sm text-gray-900">Events</span>
                                <p class="text-xs text-gray-600">Manage events</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="stats-card rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Teachers</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_teachers ?></p>
                    </div>
                    <i class="fas fa-chalkboard-teacher text-3xl text-blue-200"></i>
                </div>
            </div>

            <div class="stats-card rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Parents</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_parents ?></p>
                    </div>
                    <i class="fas fa-user-tie text-3xl text-blue-200"></i>
                </div>
            </div>

            <div class="stats-card rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Courses</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_courses ?></p>
                    </div>
                    <i class="fas fa-book text-3xl text-blue-200"></i>
                </div>
            </div>

            <div class="stats-card rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Attendance</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $avg_attendance ?>%</p>
                    </div>
                    <i class="fas fa-chart-line text-3xl text-blue-200"></i>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Enrollments -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-8 py-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Recent Enrollments</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Student Name</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Course / Batch</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
                                    <?php while ($e = $recent_enrollments->fetch_assoc()): ?>
                                        <tr class="table-row border-b border-gray-200 hover:bg-blue-50">
                                            <td class="px-8 py-5 text-gray-900 font-medium"><?= htmlspecialchars($e['firstName'] . ' ' . $e['lastName']) ?></td>
                                            <td class="px-8 py-5">
                                                <div class="text-gray-900 font-medium text-sm"><?= htmlspecialchars($e['courseName']) ?></div>
                                                <div class="text-gray-500 text-xs"><?= htmlspecialchars($e['batch_code']) ?></div>
                                            </td>
                                            <td class="px-8 py-5 text-gray-600 text-sm"><?= date("M d, Y", strtotime($e['enrolled_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-8 text-gray-500">No recent enrollments</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Events & Announcements -->
            <div class="space-y-8">
                <!-- Upcoming Events -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-8 py-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Upcoming Events</h3>
                    </div>
                    <div class="p-6">
                        <?php if ($events_result && $events_result->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while ($ev = $events_result->fetch_assoc()): ?>
                                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                                        <div class="flex gap-4">
                                            <div class="bg-blue-600 text-white rounded-lg p-3 text-center min-w-fit">
                                                <div class="text-lg font-bold"><?= date('d', strtotime($ev['event_date'])) ?></div>
                                                <div class="text-xs"><?= date('M', strtotime($ev['event_date'])) ?></div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($ev['title']) ?></h4>
                                                <p class="text-gray-600 text-xs mt-1"><?= htmlspecialchars(substr($ev['description'], 0, 80)) ?><?= strlen($ev['description']) > 80 ? '...' : '' ?></p>
                                                <p class="text-gray-500 text-xs mt-2">
                                                    <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($ev['event_time_start'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-8">No upcoming events</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Announcements -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-8 py-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Recent Announcements</h3>
                    </div>
                    <div class="p-6">
                        <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while ($a = $announcements_result->fetch_assoc()): ?>
                                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars(substr($a['message'], 0, 100))) ?><?= strlen($a['message']) > 100 ? '...' : '' ?></p>
                                        <p class="text-gray-500 text-xs mt-3">
                                            <i class="fas fa-calendar"></i> <?= date("M d, Y", strtotime($a['created_at'])) ?>
                                            • To <strong><?= ucfirst($a['recipients']) ?></strong>
                                        </p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-8">No recent announcements</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal - Shows only on first login -->
<?php if (!empty($recent_payments) && $isFirstLogin): ?>
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 animate-fade-in">
        <!-- Modal Header -->
        <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-t-lg">
            <h2 class="text-2xl font-bold flex items-center gap-3">
                <i class="fas fa-check-circle"></i>
                New Payments Received!
            </h2>
            <p class="text-blue-100 mt-1">You have received payments in the last 24 hours</p>
        </div>

        <!-- Modal Body -->
        <div class="p-8 max-h-96 overflow-y-auto">
            <div class="space-y-4">
                <?php foreach ($recent_payments as $p): ?>
                    <div class="flex items-center justify-between p-5 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                M
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?></p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($p['courseName']) ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-blue-600">M<?= number_format($p['amount'], 2) ?></p>
                            <p class="text-gray-500 text-xs"><?= date("h:i A", strtotime($p['payment_date'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-8 py-4 border-t border-gray-200 text-right bg-gray-50 rounded-b-lg">
            <button onclick="closePaymentModal()" class="btn-action rounded-lg px-8 py-3 font-medium">
                Got it!
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function closePaymentModal() {
        const modal = document.getElementById('paymentModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('paymentModal');
        if (modal) {
            setTimeout(() => {
                modal.classList.remove('hidden');
            }, 500);
        }
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('paymentModal');
        if (modal && e.target === modal) {
            closePaymentModal();
        }
    });
</script>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    #paymentModal:not(.hidden) {
        display: flex;
    }
</style>

</body>
</html>