<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

include 'db.php';

// === ALL REQUIRED COUNTS FOR DASHBOARD CARDS ===
function getCount($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? (int)$res->fetch_assoc()['count'] : 0;
}

$active_enrollments = getCount($conn, "SELECT COUNT(*) as count FROM course_enrollments WHERE status='active'");
$pending_invoices    = getCount($conn, "SELECT COUNT(*) as count FROM invoices WHERE status='pending'");

// === CHART DATA ===
$enrollments_per_month = $conn->query("
    SELECT DATE_FORMAT(enrolled_at, '%Y-%m') as month, COUNT(*) as count
    FROM course_enrollments
    WHERE enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month
");

$course_popularity = $conn->query("
    SELECT c.courseName, COUNT(ce.enrollment_id) as students
    FROM courses c
    LEFT JOIN batches b ON c.course_id = b.course_id
    LEFT JOIN course_enrollments ce ON b.batch_id = ce.batch_id AND ce.status='active'
    GROUP BY c.course_id
    ORDER BY students DESC LIMIT 10
");

$attendance_rate = $conn->query("
    SELECT b.batch_code, 
           ROUND(AVG(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)*100, 1) as rate
    FROM attendance a
    JOIN batches b ON a.batch_id = b.batch_id
    GROUP BY b.batch_id
    ORDER BY rate DESC
");

$payment_methods = $conn->query("
    SELECT COALESCE(payment_method, 'Other') as payment_method, COUNT(*) as count
    FROM payments
    WHERE status = 'completed'
    GROUP BY payment_method
");

$revenue_per_month = $conn->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
    FROM payments
    WHERE status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month
");

// Prepare data for charts
$months = []; $enrollments_data = [];
while ($row = $enrollments_per_month->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month']));
    $enrollments_data[] = (int)$row['count'];
}

$course_names = []; $course_students = [];
while ($row = $course_popularity->fetch_assoc()) {
    $course_names[] = $row['courseName'];
    $course_students[] = (int)$row['students'];
}

$batch_names = []; $attendance_rates = [];
while ($row = $attendance_rate->fetch_assoc()) {
    $batch_names[] = $row['batch_code'];
    $attendance_rates[] = (float)$row['rate'];
}

$methods = []; $method_counts = [];
while ($row = $payment_methods->fetch_assoc()) {
    $methods[] = $row['payment_method'];
    $method_counts[] = (int)$row['count'];
}

$revenue_months = []; $revenue_amounts = [];
while ($row = $revenue_per_month->fetch_assoc()) {
    $revenue_months[] = date('M Y', strtotime($row['month']));
    $revenue_amounts[] = (float)$row['total'];
}

$total_revenue = array_sum($revenue_amounts);
$avg_attendance_rate = !empty($attendance_rates) ? round(array_sum($attendance_rates)/count($attendance_rates), 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reports & Analytics - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        canvas { background: white; border-radius: 1rem; padding: 1rem; }
        .stat-big { font-size: 1.8rem; font-weight: 500; }
        .glass { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); }
        /* Adjust container-fluid padding and margin for no extra top space and closer left alignment */
        .container-fluid.custom-container {
            margin-left: 260px; /* Slightly tighter than before */
            margin-top: 0 !important;
            padding-top: 0 !important;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            max-width: 1440px;
        }
        /* Chart heights for better fit */
        #enrollmentsChart, #revenueChart { height: 250px !important; }
        #courseChart { height: 350px !important; }
        #paymentPieChart { height: 250px !important; }
        #attendanceChart { height: 180px !important; }
        /* Title alignment and spacing */
        h1.display-5 {
            padding-top: 0;
            margin-top: 0;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: left;
            user-select: none;
        }
    </style>
</head>
<body class="pb-5">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="container-fluid custom-container">

    <h1 class="display-5 fw-bold mb-4">
        Reports & Analytics
    </h1>

    <!-- Top Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="card p-4 text-center glass">
                <i class="bi bi-people fs-1 text-primary mb-3"></i>
                <div class="stat-big text-primary"><?= $active_enrollments ?></div>
                <p class="mb-0">Active Students</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-4 text-center glass">
                <i class="bi bi-currency-dollar fs-1 text-success mb-3"></i>
                <div class="stat-big text-success">M<?= number_format($total_revenue, 0) ?></div>
                <p class="mb-0">Revenue (12 mo)</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-4 text-center glass">
                <i class="bi bi-graph-up-arrow fs-1 text-info mb-3"></i>
                <div class="stat-big text-info"><?= $avg_attendance_rate ?>%</div>
                <p class="mb-0">Avg Attendance</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-4 text-center glass">
                <i class="bi bi-receipt fs-1 text-warning mb-3"></i>
                <div class="stat-big text-warning"><?= $pending_invoices ?></div>
                <p class="mb-0">Pending Invoices</p>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4">
        <!-- Monthly Enrollments -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Monthly Enrollments (Last 12 Months)</h5>
                    <canvas id="enrollmentsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue Trend -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Monthly Revenue (MVR)</h5>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Course Popularity -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Top Courses by Enrollment</h5>
                    <canvas id="courseChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Methods Pie -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Payment Methods Distribution</h5>
                    <canvas id="paymentPieChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Attendance by Batch -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Attendance Rate by Batch</h5>
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Enrollments Line
new Chart(document.getElementById('enrollmentsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Enrollments',
            data: <?= json_encode($enrollments_data) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { responsive: true }
});

// Revenue Line
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($revenue_months) ?>,
        datasets: [{
            label: 'Revenue (MVR)',
            data: <?= json_encode($revenue_amounts) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { responsive: true }
});

// Course Bar with new colors
new Chart(document.getElementById('courseChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($course_names) ?>,
        datasets: [{
            label: 'Students',
            data: <?= json_encode($course_students) ?>,
            backgroundColor: [
                '#4f46e5', '#4338ca', '#6d28d9', '#7c3aed', '#8b5cf6',
                '#a78bfa', '#c4b5fd', '#ddd6fe', '#e0e7ff', '#ede9fe'
            ]
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        scales: {
            x: { beginAtZero: true }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

// Payment Pie
new Chart(document.getElementById('paymentPieChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($methods) ?>,
        datasets: [{
            data: <?= json_encode($method_counts) ?>,
            backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981']
        }]
    },
    options: { responsive: true }
});

// Attendance Bar
new Chart(document.getElementById('attendanceChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($batch_names) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode($attendance_rates) ?>,
            backgroundColor: '#10b981'
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
</script>

</body>
</html>
