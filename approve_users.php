<?php
session_start();

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle actions
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $action = $_GET['action'];

    if ($action === "approve") {
        $conn->query("UPDATE users SET status='active' WHERE user_id=$user_id");
    } elseif ($action === "reject") {
        $conn->query("UPDATE users SET status='rejected' WHERE user_id=$user_id");
    } elseif ($action === "waitlist") {
        $conn->query("UPDATE users SET status='waitlist' WHERE user_id=$user_id");
    } elseif ($action === "delete") {
        $conn->query("DELETE FROM users WHERE user_id=$user_id");
    }
    header("Location: approve_users.php");
    exit();
}

// Fetch users by status
$pending = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='pending'");
$waitlist = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='waitlist'");
$rejected = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='rejected'");
$recent = $conn->query("SELECT user_id, firstName, lastName, email, updated_at FROM users WHERE status='active' ORDER BY updated_at DESC LIMIT 5");

// Fetch counts for stats
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='active'")->fetch_assoc()['total'];
$waitlistedUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='waitlist'")->fetch_assoc()['total'];
$rejectedUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='rejected'")->fetch_assoc()['total'];
$pendingUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='pending'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Approve Users</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f9f9f9; }

    header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }

    .container { display: flex; }

    .sidebar {
        width: 220px;
        background: #34495e;
        min-height: 100vh;
        padding: 20px;
    }

sidebar { width: 220px; background: #343a40; color: #fff; min-height: 100vh; padding-top: 20px; position: fixed; }
.sidebar a { display: flex; align-items: center; padding: 10px 15px; color: #fff; text-decoration: none; border-radius: 5px; margin-bottom: 4px; }
.sidebar a:hover, .sidebar a.active { background: #495057; }
.sidebar a i { margin-right: 10px; }

    h1{margin:0;
    font-size:20px;
    font-weight:600}
    .content { display: flex; flex: 1; padding: 30px; gap: 20px; }

    .main-section { flex: 2; }

    h2 { margin-bottom: 15px; color: #2c3e50; }

    table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    a.active { background:#1abc9c; }

    th, td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
        font-size: 14px;
    }

    th { color: white; }

    a.btn {
        padding: 5px 10px;
        text-decoration: none;
        border-radius: 4px;
        color: white;
        font-size: 13px;
    }

    a.approve { background: #27ae60; }
    a.reject { background: #e74c3c; }
    a.waitlist { background: #f39c12; }
    a.delete { background: #c0392b; }

    a.btn:hover { opacity: 0.8; }

    footer {
        background: #2c3e50;
        color: white;
        text-align: center;
        padding: 15px;
        margin-top: 20px;
    }

    .admin-pic {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin-bottom: 15px;
        border: 3px solid #1abc9c;
        object-fit: cover;
    }

    .pending th { background: #1abc9c; }
    .waitlist th { background: #f39c12; }
    .rejected th { background: #e74c3c; }
    .recent th { background: #2980b9; }

    /* Charts section */
    .charts {
        flex: 1;
        position: sticky;
        top: 20px;
        height: fit-content;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .chart-container { margin-bottom: 20px; }
</style>
</head>
<body>
    
<header>
    <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>


<div class="container">
    <div class="sidebar">
        <img src="admin.jpg" alt="Admin Picture" class="admin-pic">
    <h4 class="text-center mb-4">Administration</h4>
    <a href="admin_dashboard.php"><i class="bi bi-house-door-fill"></i> Dashboard</a>
    <a href="approve_users.php"class="active><i class="bi bi-person-check-fill></i> Approve Users</a>
    <a href="manage_courses.php"><i class="bi bi-journal-bookmark-fill"></i> Courses</a>
    <a href="manage_students.php"><i class="bi bi-people-fill"></i> Students</a>
    <a href="manage_teachers.php"><i class="bi bi-person-badge-fill"></i> Teachers</a>
    <a href="parents_summary.php"><i class="bi bi-people"></i> Parent Summary</a>
    <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Parents</a>
    <a href="assign_parent_student.php"><i class="bi bi-person-plus-fill"></i> Assign Students</a>
    <a href="course_assignment.php"><i class="bi bi-book-half"></i> Assign Courses</a>
    <a href="add_batch.php"><i class="bi bi-plus-circle-fill"></i> Add Batch</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <div class="content">
       
        <div class="main-section">
           
            <h2>Awaiting Approval</h2>
            <?php if ($pending->num_rows > 0) { ?>
            <table class="pending">
                <tr>
                    <th>Name</th><th>Email</th><th>Registration Date</th><th>Actions</th>
                </tr>
                <?php while ($row = $pending->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['firstName'].' '.$row['lastName'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <a class="btn approve" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn reject" href="approve_users.php?action=reject&user_id=<?= $row['user_id'] ?>">Reject</a>
                        <a class="btn waitlist" href="approve_users.php?action=waitlist&user_id=<?= $row['user_id'] ?>">Waitlist</a>
                    </td>
                </tr>
                <?php } ?>
            </table>
            <?php } else { echo "<p>No users awaiting approval.</p>"; } ?>

            <h2>Waiting List</h2>
            <?php if ($waitlist->num_rows > 0) { ?>
            <table class="waitlist">
                <tr>
                    <th>Name</th><th>Email</th><th>Registered At</th><th>Actions</th>
                </tr>
                <?php while ($row = $waitlist->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['firstName'].' '.$row['lastName'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <a class="btn approve" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn delete" href="approve_users.php?action=delete&user_id=<?= $row['user_id'] ?>">Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </table>
            <?php } else { echo "<p>No waitlisted users.</p>"; } ?>

            <h2>Rejections</h2>
            <?php if ($rejected->num_rows > 0) { ?>
            <table class="rejected">
                <tr>
                    <th>Name</th><th>Email</th><th>Registered At</th><th>Actions</th>
                </tr>
                <?php while ($row = $rejected->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['firstName'].' '.$row['lastName'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <a class="btn approve" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn delete" href="approve_users.php?action=delete&user_id=<?= $row['user_id'] ?>">Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </table>
            <?php } else { echo "<p>No rejected users.</p>"; } ?>

            <h2>Recent Approvals</h2>
            <?php if ($recent->num_rows > 0) { ?>
            <table class="recent">
                <tr>
                    <th>Name</th><th>Email</th><th>Approved At</th>
                </tr>
                <?php while ($row = $recent->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['firstName'].' '.$row['lastName'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['updated_at'] ?></td>
                </tr>
                <?php } ?>
            </table>
            <?php } else { echo "<p>No recent approvals.</p>"; } ?>
        </div>

        <div class="charts">
            <h2>Application Statistics</h2>
            <div class="chart-container">
                <canvas id="barChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> Girls Coding Academy. All Rights Reserved.</p>
</footer>

<script>
    const barCtx = document.getElementById('barChart').getContext('2d');
    const barChart = new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: ['Pending', 'Waitlisted', 'Rejected', 'Active'],
            datasets: [{
                label: 'Users',
                data: [<?= $pendingUsers ?>, <?= $waitlistedUsers ?>, <?= $rejectedUsers ?>, <?= $activeUsers ?>],
                backgroundColor: ['#1abc9c','#f39c12','#e74c3c','#27ae60']
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    const pieCtx = document.getElementById('pieChart').getContext('2d');
    const pieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Waitlisted', 'Rejected', 'Active'],
            datasets: [{
                data: [<?= $pendingUsers ?>, <?= $waitlistedUsers ?>, <?= $rejectedUsers ?>, <?= $activeUsers ?>],
                backgroundColor: ['#1abc9c','#f39c12','#e74c3c','#27ae60']
            }]
        },
        options: { responsive: true }
    });
</script>
</body>
</html>
