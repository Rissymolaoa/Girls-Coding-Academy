<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); exit();
}
require_once 'db.php';

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
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7c3aed; --primary-light: #a78bfa; --primary-dark: #6d28d9;
            --secondary: #1e293b; --accent: #f472b6; --success: #10b981; --warning: #f59e0b;
        }
        .dark { background: #0f172a; color: #e2e8f0; }
        .dark .bg-white { background: #1e293b; }
        .dark .text-gray-800 { color: #e2e8f0; }
        .dark .bg-gray-50 { background: #1e293b; }
        .dark .border-gray-200 { border-color: #334155; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(124,58,237,0.2); }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.2); }
        .sidebar-link { transition: all 0.3s; }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); padding-left: 1.5rem; }
        .sidebar-link.active { background: rgba(124,58,237,0.3); border-left: 4px solid white; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-purple-50 via-pink-50 to-indigo-100 dark:from-gray-900 dark:to-purple-900 transition-all duration-500">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen p-6">
    <div class="max-w-7xl mx-auto">

        <!-- Dark Mode Toggle -->
        <div class="fixed top-24 right-8 z-50">
            <button onclick="document.documentElement.classList.toggle('dark')" class="bg-white dark:bg-gray-800 p-4 rounded-full shadow-2xl hover:scale-110 transition">
                <i class="fas fa-moon text-2xl text-purple-600 dark:text-yellow-400"></i>
            </button>
        </div>

        <!-- Welcome Header -->
        <div class="glass rounded-3xl p-10 mb-10 border border-white/20 shadow-2xl">
            <div class="flex flex-col lg:flex-row justify-between items-center gap-8">
                <div>
                    <h1 class="text-5xl font-extrabold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        Welcome back, Admin!
                    </h1>
                    <p class="text-2xl text-gray-700 dark:text-gray-300 mt-4"><?= date('l, F j, Y') ?></p>
                </div>
                <div class="grid grid-cols-3 gap-8">
                    <div class="bg-gradient-to-br from-purple-600 to-purple-800 text-white p-8 rounded-3xl card-hover text-center">
                        <i class="fas fa-users text-5xl mb-4 opacity-90"></i>
                        <p class="text-5xl font-bold"><?= $total_students ?></p>
                        <p class="text-purple-200 text-lg">Students</p>
                    </div>
                    <div class="bg-gradient-to-br from-pink-500 to-rose-600 text-white p-8 rounded-3xl card-hover text-center">
                        <i class="fas fa-receipt text-5xl mb-4 opacity-90"></i>
                        <p class="text-5xl font-bold"><?= $pending_invoices ?></p>
                        <p class="text-pink-200 text-lg">Pending Invoices</p>
                    </div>
                    <div class="bg-gradient-to-br from-indigo-600 to-blue-700 text-white p-8 rounded-3xl card-hover text-center">
                        <i class="fas fa-calendar-check text-5xl mb-4 opacity-90"></i>
                        <p class="text-5xl font-bold"><?= $active_enrollments ?></p>
                        <p class="text-indigo-200 text-lg">Active Enrollments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="glass rounded-3xl p-10 mb-10 border border-white/20">
            <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-8">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-6">
                <a href="manage_students.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-user-graduate text-5xl text-purple-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Students</p>
                </a>
                <a href="manage_teachers.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-chalkboard-teacher text-5xl text-indigo-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Teachers</p>
                </a>
                <a href="manage_parents.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-user-tie text-5xl text-pink-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Parents</p>
                </a>
                <a href="manage_courses.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-book-open text-5xl text-green-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Courses</p>
                </a>
                <a href="manage_batches.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-calendar-week text-5xl text-blue-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Batches</p>
                </a>
                <a href="invoices.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-file-invoice-dollar text-5xl text-yellow-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Invoices</p>
                </a>
                <a href="attendance_report.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-clipboard-list text-5xl text-teal-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Attendance</p>
                </a>
                <a href="admin_announcements.php" class="text-center p-6 bg-white/80 dark:bg-gray-800 rounded-2xl card-hover shadow-lg">
                    <i class="fas fa-bullhorn text-5xl text-red-600 mb-3"></i>
                    <p class="font-bold text-gray-800 dark:text-white">Announce</p>
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-6 mb-10">
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-users text-5xl text-purple-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $total_students ?></p>
                <p class="text-gray-600 dark:text-gray-300">Students</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-chalkboard-teacher text-5xl text-indigo-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $total_teachers ?></p>
                <p class="text-gray-600 dark:text-gray-300">Teachers</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-user-tie text-5xl text-pink-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $total_parents ?></p>
                <p class="text-gray-600 dark:text-gray-300">Parents</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-book-open text-5xl text-green-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $total_courses ?></p>
                <p class="text-gray-600 dark:text-gray-300">Courses</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-layer-group text-5xl text-blue-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $active_batches ?></p>
                <p class="text-gray-600 dark:text-gray-300">Active Batches</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-receipt text-5xl text-yellow-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $pending_invoices ?></p>
                <p class="text-gray-600 dark:text-gray-300">Pending Invoices</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-calendar-event text-5xl text-red-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $upcoming_events ?></p>
                <p class="text-gray-600 dark:text-gray-300">Upcoming Events</p>
            </div>
            <div class="glass rounded-3xl p-8 text-center card-hover border border-white/30">
                <i class="fas fa-chart-line text-5xl text-teal-600 mb-4"></i>
                <p class="text-5xl font-bold text-gray-800 dark:text-white"><?= $avg_attendance ?>%</p>
                <p class="text-gray-600 dark:text-gray-300">Avg Attendance</p>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="glass rounded-3xl p-8 border border-white/20">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Recent Enrollments</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left rounded-tl-xl">Student</th>
                                    <th class="px-6 py-4 text-left">Course / Batch</th>
                                    <th class="px-6 py-4 text-left rounded-tr-xl">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
                                    <?php while ($e = $recent_enrollments->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition">
                                            <td class="px-6 py-5 font-medium"><?= htmlspecialchars($e['firstName'] . ' ' . $e['lastName']) ?></td>
                                            <td class="px-6 py-5">
                                                <div class="font-semibold"><?= htmlspecialchars($e['courseName']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($e['batch_code']) ?></div>
                                            </td>
                                            <td class="px-6 py-5 text-gray-600 dark:text-gray-400"><?= date("M d, Y", strtotime($e['enrolled_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-12 text-gray-500">No recent enrollments</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="glass rounded-3xl p-8 mb-8 border border-white/20">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Upcoming Events</h2>
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while ($ev = $events_result->fetch_assoc()): ?>
                            <div class="mb-6 p-6 bg-gradient-to-r from-purple-100 to-pink-100 dark:from-purple-900/50 dark:to-pink-900/50 rounded-2xl">
                                <div class="flex items-start gap-4">
                                    <div class="bg-gradient-to-br from-purple-600 to-pink-600 text-white rounded-2xl p-4 text-center font-bold">
                                        <div class="text-2xl"><?= date('d', strtotime($ev['event_date'])) ?></div>
                                        <div class="text-sm"><?= date('M', strtotime($ev['event_date'])) ?></div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-lg text-gray-800 dark:text-white"><?= htmlspecialchars($ev['title']) ?></h4>
                                        <p class="text-gray-700 dark:text-gray-300 text-sm mt-1"><?= htmlspecialchars($ev['description']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                            <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($ev['event_time_start'])) ?>
                                            • <?= htmlspecialchars($ev['location']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-12">No upcoming events</p>
                    <?php endif; ?>
                </div>

                <div class="glass rounded-3xl p-8 border border-white/20">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Recent Announcements</h2>
                    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                        <?php while ($a = $announcements_result->fetch_assoc()): ?>
                            <div class="mb-5 p-5 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                                <p class="text-gray-700 dark:text-gray-300"><?= nl2br(htmlspecialchars(substr($a['message'], 0, 120))) ?><?= strlen($a['message']) > 120 ? '...' : '' ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                    <i class="fas fa-clock"></i> <?= date("M d, Y", strtotime($a['created_at'])) ?>
                                    • To <?= ucfirst($a['recipients']) ?>
                                </p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-12">No recent announcements</p>
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
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <div class="modal-header bg-gradient-to-r from-green-500 to-emerald-600 text-white p-8">
                <h5 class="modal-title text-3xl font-bold">
                    <i class="fas fa-bell mr-4"></i> New Payments Received!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-8 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-gray-800 dark:to-gray-900">
                <p class="text-xl text-gray-800 dark:text-gray-200 mb-8 text-center">
                    <strong>Congratulations!</strong> You have received new payments in the last 24 hours:
                </p>
                <div class="space-y-6">
                    <?php foreach ($recent_payments as $p): ?>
                        <div class="flex items-center justify-between p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-green-200 dark:border-green-800">
                            <div class="flex items-center gap-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                    M
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-800 dark:text-white">
                                        <?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?>
                                    </p>
                                    <p class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($p['courseName']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-4xl font-bold text-green-600 dark:text-green-400">M<?= number_format($p['amount'], 2) ?></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400"><?= date("h:i A", strtotime($p['payment_date'])) ?></p>
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