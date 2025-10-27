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
    die("Database connection failed: " . $e->getMessage() . "<br>Tip: Ensure XAMPP MySQL is running on port 3306. Check XAMPP Control Panel and start MySQL module.");
}

// Fetch summary counts
function getCount($conn, $query) {
    $result = $conn->query($query);
    return $result ? (int)$result->fetch_assoc()['count'] : 0;
}

$total_students = getCount($conn, "SELECT COUNT(*) as count FROM students");
$total_teachers = getCount($conn, "SELECT COUNT(*) as count FROM teachers");
$total_parents  = getCount($conn, "SELECT COUNT(*) as count FROM parents");
$total_courses  = getCount($conn, "SELECT COUNT(*) as count FROM courses");
$active_courses = getCount($conn, "SELECT COUNT(*) as count FROM courses WHERE status='active'");
$total_batches  = getCount($conn, "SELECT COUNT(*) as count FROM batches WHERE status='active'");
$total_enrollments = getCount($conn, "SELECT COUNT(*) as count FROM course_enrollments WHERE status='active'");
$pending_invoices = getCount($conn, "SELECT COUNT(*) as count FROM invoices WHERE status='pending'");
$upcoming_events = getCount($conn, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE() AND is_posted=1");
$avg_attendance = getCount($conn, "SELECT ROUND(AVG(CASE WHEN status='Present' THEN 1 ELSE 0 END)*100, 1) as count FROM attendance WHERE session_id >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$total_payments = getCount($conn, "SELECT COUNT(*) as count FROM payments WHERE status='completed'");

// Fetch recent enrollments
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

// Fetch recent payments
$recent_payments = [];
if ($_SESSION['role'] === 'admin') {
    $recent_payments_query = $conn->query("
        SELECT p.payment_id, p.amount, p.payment_date, p.payment_method, 
               u.firstName, u.lastName, c.courseName, b.batch_code
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
    while ($payment = $recent_payments_query->fetch_assoc()) {
        $recent_payments[] = $payment;
    }
}

// Fetch upcoming events
$upcoming_events_list = $conn->query("
    SELECT title, description, event_date, event_time_start, location
    FROM events 
    WHERE event_date >= CURDATE() AND is_posted = 1
    ORDER BY event_date ASC, event_time_start ASC
    LIMIT 5
");

// Fetch recent announcements
$recent_announcements = $conn->query("
    SELECT message, recipients, created_at
    FROM admin_announcements 
    ORDER BY created_at DESC 
    LIMIT 4
");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin Dashboard - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --accent: #f093fb;
            --success: #4facfe;
            --danger: #fa709a;
            --warning: #ffa502;
            --info: #00d4ff;
            --light: #f8fafc;
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
            min-height: calc(100vh - 60px);
            padding: 2rem;
        }

        .header-section {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .header-content h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header-content p {
            color: #718096;
            font-size: 1rem;
            margin: 0;
        }

        .header-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stat-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .stat-badge strong {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .stat-badge span {
            font-size: 0.85rem;
            color: #718096;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            border-color: var(--primary);
        }

        .stat-card.secondary::before { background: linear-gradient(90deg, var(--accent), var(--danger)); }
        .stat-card.success::before { background: linear-gradient(90deg, var(--success), var(--info)); }
        .stat-card.warning::before { background: linear-gradient(90deg, var(--warning), #ff7a00); }
        .stat-card.info::before { background: linear-gradient(90deg, #00d4ff, #0099ff); }
        .stat-card.danger::before { background: linear-gradient(90deg, var(--danger), #ff5c8a); }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .stat-card-title {
            font-size: 0.95rem;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .stat-card.secondary .stat-icon { background: linear-gradient(135deg, var(--accent), var(--danger)); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, var(--success), var(--info)); }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, var(--warning), #ff7a00); }
        .stat-card.info .stat-icon { background: linear-gradient(135deg, #00d4ff, #0099ff); }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, var(--danger), #ff5c8a); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-change {
            font-size: 0.85rem;
            color: #48bb78;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .section-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }

        .table-wrapper {
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
            font-size: 0.95rem;
            padding: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.25rem;
            vertical-align: middle;
            border-color: var(--border);
            color: #4a5568;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            padding: 1.5rem 1rem;
            background: white;
            border: 2px solid var(--border);
            border-radius: 14px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            color: var(--primary);
        }

        .action-btn i {
            font-size: 1.8rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .action-btn:hover i {
            transform: scale(1.2);
        }

        .event-item, .announcement-item {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(240, 147, 251, 0.05));
            border-left: 4px solid var(--primary);
            margin-bottom: 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .event-item strong, .announcement-item strong {
            color: var(--dark);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.05rem;
        }

        .event-item small, .announcement-item small {
            color: #718096;
        }

        .event-date {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        footer {
            background: linear-gradient(135deg, var(--dark), #2d3748);
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            border-radius: 16px 16px 0 0;
        }

        footer p {
            margin: 0;
            font-size: 0.95rem;
        }

        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--success), var(--info));
            color: white;
            border: none;
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }

            .header-section {
                flex-direction: column;
                text-align: center;
            }

            .header-stats {
                justify-content: center;
                width: 100%;
            }

            .cards-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .header-content h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-badge {
                padding: 0.75rem 1rem;
            }

            .stat-badge strong {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <h1>Admin Dashboard</h1>
            <p>Welcome back! Monitor your academy's performance in real-time</p>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <strong><?= $total_students ?></strong>
                <span>Total Students</span>
            </div>
            <div class="stat-badge">
                <strong><?= $total_teachers ?></strong>
                <span>Active Teachers</span>
            </div>
            <div class="stat-badge">
                <strong><?= $total_courses ?></strong>
                <span>Courses</span>
            </div>
            <div class="stat-badge">
                <strong><?= $total_enrollments ?></strong>
                <span>Enrollments</span>
            </div>
        </div>
    </div>

    <!-- Summary Cards Grid -->
    <div class="cards-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Students</span>
                <div class="stat-icon"><i class="bi bi-mortarboard"></i></div>
            </div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Active learners</div>
        </div>

        <div class="stat-card secondary">
            <div class="stat-card-header">
                <span class="stat-card-title">Teachers</span>
                <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
            </div>
            <div class="stat-value"><?= $total_teachers ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Instructors</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <span class="stat-card-title">Active Enrollments</span>
                <div class="stat-icon"><i class="bi bi-clipboard-check"></i></div>
            </div>
            <div class="stat-value"><?= $total_enrollments ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Course registrations</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <span class="stat-card-title">Active Batches</span>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-value"><?= $total_batches ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Running classes</div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <span class="stat-card-title">Pending Invoices</span>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-value"><?= $pending_invoices ?></div>
            <div class="stat-change"><i class="bi bi-alert-circle"></i> Payment pending</div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <span class="stat-card-title">Attendance Rate</span>
                <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
            </div>
            <div class="stat-value"><?= $avg_attendance ?>%</div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> 30-day average</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Parents</span>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
            <div class="stat-value"><?= $total_parents ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Guardians</div>
        </div>

        <div class="stat-card secondary">
            <div class="stat-card-header">
                <span class="stat-card-title">Upcoming Events</span>
                <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
            </div>
            <div class="stat-value"><?= $upcoming_events ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Scheduled</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <span class="stat-card-title">Completed Payments</span>
                <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
            </div>
            <div class="stat-value"><?= $total_payments ?></div>
            <div class="stat-change"><i class="bi bi-arrow-up-right"></i> Transactions</div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Enrollments -->
            <div class="section-card">
                <h2 class="section-title"><i class="bi bi-arrow-right-circle"></i> Recent Enrollments</h2>
                <div class="table-wrapper">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Course / Batch</th>
                                <th>Enrolled Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
                                <?php while($e = $recent_enrollments->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($e['firstName'] . ' ' . $e['lastName']) ?></strong></td>
                                        <td><?= htmlspecialchars($e['courseName']) ?><br><small class="text-muted"><?= htmlspecialchars($e['batch_code']) ?></small></td>
                                        <td><?= date("M d, Y H:i", strtotime($e['enrolled_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No recent enrollments</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="section-card">
                <h2 class="section-title"><i class="bi bi-calendar-event"></i> Upcoming Events</h2>
                <?php if ($upcoming_events_list && $upcoming_events_list->num_rows > 0): ?>
                    <?php while($ev = $upcoming_events_list->fetch_assoc()): ?>
                        <div class="event-item">
                            <strong><?= htmlspecialchars($ev['title']) ?></strong>
                            <small><?= htmlspecialchars($ev['description']) ?></small><br>
                            <span class="event-date"><i class="bi bi-calendar-check"></i> <?= htmlspecialchars($ev['event_date']) ?> at <?= htmlspecialchars($ev['event_time_start']) ?></span>
                            <br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['location']) ?></small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-4">No upcoming events scheduled</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Announcements -->
            <div class="section-card">
                <h2 class="section-title"><i class="bi bi-megaphone"></i> Announcements</h2>
                <?php if ($recent_announcements && $recent_announcements->num_rows > 0): ?>
                    <?php while($ann = $recent_announcements->fetch_assoc()): ?>
                        <div class="announcement-item">
                            <strong><?= htmlspecialchars(substr($ann['message'], 0, 50)) ?>...</strong>
                            <small class="text-muted"><i class="bi bi-clock"></i> <?= date("M d, Y H:i", strtotime($ann['created_at'])) ?></small><br>
                            <span class="badge badge-<?= strtolower($ann['recipients']) === 'students' ? 'primary' : 'info' ?>" style="margin-top: 0.5rem;"><?= ucfirst(htmlspecialchars($ann['recipients'])) ?></span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-4">No announcements</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="section-card">
                <h2 class="section-title"><i class="bi bi-lightning"></i> Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="manage_courses.php" class="action-btn" title="Manage courses"><i class="bi bi-journal"></i><span>Courses</span></a>
                    <a href="manage_batches.php" class="action-btn" title="Manage batches"><i class="bi bi-calendar-check"></i><span>Batches</span></a>
                    <a href="manage_students.php" class="action-btn" title="Manage students"><i class="bi bi-people"></i><span>Students</span></a>
                    <a href="manage_teachers.php" class="action-btn" title="Manage teachers"><i class="bi bi-person-badge"></i><span>Teachers</span></a>
                    <a href="manage_events.php" class="action-btn" title="Manage events"><i class="bi bi-calendar-event"></i><span>Events</span></a>
                    <a href="view_attendance.php" class="action-btn" title="View attendance"><i class="bi bi-check-circle"></i><span>Attendance</span></a>
                    <a href="manage_invoices.php" class="action-btn" title="Manage invoices"><i class="bi bi-receipt"></i><span>Invoices</span></a>
                    <a href="admin_announcements.php" class="action-btn" title="Announcements"><i class="bi bi-megaphone"></i><span>Announce</span></a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    <p><i class="bi bi-shield-check"></i> &copy; 2025 Girls Coding Academy | Admin Dashboard</p>
</footer>

<?php if ($_SESSION['role'] === 'admin' && !empty($recent_payments)): ?>
<div class="modal fade" id="paymentsAlert" tabindex="-1" aria-labelledby="paymentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentsLabel"><i class="bi bi-credit-card"></i> Recent Payments Alert</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong>✓ Recent payments received (last 24 hours):</strong></p>
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?></td>
                                    <td><strong><?= htmlspecialchars($p['courseName']) ?></strong></td>
                                    <td>$<?= number_format($p['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($p['payment_method'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3" style="background: rgba(0, 212, 255, 0.1); border: 1px solid var(--info); color: var(--info);">
                    <i class="bi bi-info-circle"></i> Total recent payments: <strong><?= count($recent_payments) ?></strong> transactions
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($_SESSION['role'] === 'admin' && !empty($recent_payments)): ?>
        if (!sessionStorage.getItem('paymentsModalShown')) {
            setTimeout(() => {
                const modal = new bootstrap.Modal(document.getElementById('paymentsAlert'));
                modal.show();
                sessionStorage.setItem('paymentsModalShown', 'true');
            }, 1000);
        }
        <?php endif; ?>
    });
</script>
</body>
</html>