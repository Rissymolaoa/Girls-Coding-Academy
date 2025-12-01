<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Safe count function
function safeCount($conn, $sql) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['count'] ?? 0;
    }
    return 0;
}

// Summary Stats
$total_students     = safeCount($conn, "SELECT COUNT(*) as count FROM students");
$total_teachers     = safeCount($conn, "SELECT COUNT(*) as count FROM teachers");
$total_parents      = safeCount($conn, "SELECT COUNT(*) as count FROM parents");
$total_courses      = safeCount($conn, "SELECT COUNT(*) as count FROM courses");
$active_batches     = safeCount($conn, "SELECT COUNT(*) as count FROM batches WHERE status='active'");
$active_enrollments = safeCount($conn, "SELECT COUNT(*) as count FROM course_enrollments WHERE status='active'");
$pending_invoices   = safeCount($conn, "SELECT COUNT(*) as count FROM invoices WHERE status='pending'");
$upcoming_events    = safeCount($conn, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE() AND is_posted=1");
$avg_attendance     = safeCount($conn, "SELECT ROUND(AVG(CASE WHEN status='Present' THEN 100 ELSE 0 END), 1) as count FROM attendance WHERE session_id >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

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
") ?: false;

// Recent Payments (for alert)
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
      AND p.status = 'completed'
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
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; --primary-dark: #4338ca; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .stat-icon { @apply w-16 h-16 rounded-2xl flex items-center justify-center text-white text-3xl; }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Welcome Header -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-10 mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div>
                    <h1 class="text-5xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">
                        Welcome back, Admin!
                    </h1>
                    <p class="text-xl text-gray-600 mt-4">Here's your academy overview for <?= date('l, F j, Y') ?></p>
                </div>
                <div class="grid grid-cols-3 gap-8 text-center">
                    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white p-6 rounded-2xl card-hover">
                        <p class="text-4xl font-bold"><?= $total_students ?></p>
                        <p class="text-indigo-100 mt-2">Students</p>
                    </div>
                    <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white p-6 rounded-2xl card-hover">
                        <p class="text-4xl font-bold"><?= $active_enrollments ?></p>
                        <p class="text-green-100 mt-2">Enrollments</p>
                    </div>
                    <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-6 rounded-2xl card-hover">
                        <p class="text-4xl font-bold"><?= $pending_invoices ?></p>
                        <p class="text-amber-100 mt-2">Pending Invoices</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-8">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-6">
                <a href="manage_courses.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-book-open text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Courses</p>
                </a>
                <a href="manage_batches.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-calendar-week text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Batches</p>
                </a>
                <a href="manage_students.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-user-graduate text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Students</p>
                </a>
                <a href="manage_teachers.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-chalkboard-teacher text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Teachers</p>
                </a>
                <a href="manage_parents.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-user-tie text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Parents</p>
                </a>
                <a href="manage_events.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-calendar-event text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Events</p>
                </a>
                <a href="admin_announcements.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-bullhorn text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Announce</p>
                </a>
                <a href="reports.php" class="text-center p-6 bg-gray-50 rounded-xl hover:bg-indigo-50 transition card-hover">
                    <i class="fas fa-chart-bar text-4xl text-indigo-600 mb-3"></i>
                    <p class="font-medium">Reports</p>
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-6 mb-10">
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-users text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $total_students ?></p>
                <p class="text-indigo-100 text-sm">Students</p>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-chalkboard-teacher text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $total_teachers ?></p>
                <p class="text-purple-100 text-sm">Teachers</p>
            </div>
            <div class="bg-gradient-to-br from-pink-500 to-pink-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-user-tie text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $total_parents ?></p>
                <p class="text-pink-100 text-sm">Parents</p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-book-open text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $total_courses ?></p>
                <p class="text-blue-100 text-sm">Courses</p>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-layer-group text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $active_batches ?></p>
                <p class="text-green-100 text-sm">Active Batches</p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-receipt text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $pending_invoices ?></p>
                <p class="text-amber-100 text-sm">Pending Invoices</p>
            </div>
            <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-calendar-check text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $upcoming_events ?></p>
                <p class="text-cyan-100 text-sm">Upcoming Events</p>
            </div>
            <div class="bg-gradient-to-br from-teal-500 to-teal-600 text-white rounded-2xl p-6 card-hover">
                <i class="fas fa-chart-line text-4xl mb-3 opacity-80"></i>
                <p class="text-4xl font-bold"><?= $avg_attendance ?>%</p>
                <p class="text-teal-100 text-sm">Avg Attendance</p>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Enrollments</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left">Student</th>
                                    <th class="px-6 py-4 text-left">Course / Batch</th>
                                    <th class="px-6 py-4 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
                                    <?php while ($e = $recent_enrollments->fetch_assoc()): ?>
                                        <tr class="border-b hover:bg-gray-50 transition">
                                            <td class="px-6 py-5 font-medium"><?= htmlspecialchars($e['firstName'] . ' ' . $e['lastName']) ?></td>
                                            <td class="px-6 py-5">
                                                <div><?= htmlspecialchars($e['courseName']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($e['batch_code']) ?></div>
                                            </td>
                                            <td class="px-6 py-5 text-gray-600"><?= date("M d, Y", strtotime($e['enrolled_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-10 text-gray-500">No recent enrollments</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Upcoming Events</h2>
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while ($ev = $events_result->fetch_assoc()): ?>
                            <div class="mb-6 p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl">
                                <div class="flex items-start gap-4">
                                    <div class="bg-primary text-white rounded-lg p-3 text-center">
                                        <div class="text-xl font-bold"><?= date('d', strtotime($ev['event_date'])) ?></div>
                                        <div class="text-xs"><?= date('M', strtotime($ev['event_date'])) ?></div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($ev['title']) ?></h4>
                                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($ev['description']) ?></p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($ev['event_time_start'])) ?>
                                            • <?= htmlspecialchars($ev['location']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">No upcoming events</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Announcements</h2>
                    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                        <?php while ($a = $announcements_result->fetch_assoc()): ?>
                            <div class="mb-4 p-4 bg-gray-50 rounded-xl">
                                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars(substr($a['message'], 0, 100))) ?><?= strlen($a['message']) > 100 ? '...' : '' ?></p>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-clock"></i> <?= date("M d, Y", strtotime($a['created_at'])) ?>
                                    • To <?= ucfirst($a['recipients']) ?>
                                </p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">No recent announcements</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Alert Modal -->
<?php if (!empty($recent_payments)): ?>
<div class="modal fade" id="paymentAlert" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                <h5 class="modal-title text-xl font-bold">
                    <i class="fas fa-bell mr-3"></i> New Payments Received!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-6">
                <p class="text-lg text-gray-700 mb-6">
                    <strong>Great news!</strong> You have received new payments in the last 24 hours:
                </p>
                <div class="space-y-4">
                    <?php foreach ($recent_payments as $p): ?>
                        <div class="flex items-center justify-between p-4 bg-green-50 rounded-xl border border-green-200">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-xl">
                                    M
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">
                                        <?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?>
                                    </p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($p['courseName']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-green-600">M<?= number_format($p['amount'], 2) ?></p>
                                <p class="text-xs text-gray-500"><?= date("h:i A", strtotime($p['payment_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($recent_payments)): ?>
    const today = new Date().toISOString().slice(0, 10);
    if (!sessionStorage.getItem('paymentAlert_' + today)) {
        setTimeout(() => {
            const modal = new bootstrap.Modal(document.getElementById('paymentAlert'));
            modal.show();
            sessionStorage.setItem('paymentAlert_' + today, 'shown');
        }, 2000);
    }
    <?php endif; ?>
});
</script>
</body>
</html>