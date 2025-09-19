<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Example student info
$studentInfo = [
    "username" => $_SESSION['username'],
    "email" => "student@example.com",
    "role" => $_SESSION['role']
];

// Example stats
$stats = [
    "Courses Enrolled" => 3,
    "Assignments Due" => 2,
    "Attendance" => "85%"
];

// Example announcements
$announcements = [
    "Midterm exams start next week.",
    "New course materials uploaded in the portal."
];

// Example upcoming schedule
$upcoming = [
    ["date" => "2025-09-21", "event" => "Math Assignment Due"],
    ["date" => "2025-09-25", "event" => "Science Project Presentation"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            display: flex;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            height: 100vh;
            padding: 20px;
        }
        .sidebar h3 {
            margin-top: 15px;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 10px 0;
            text-decoration: none;
        }
        .sidebar a:hover, .sidebar .active {
            background-color: #495057;
            border-radius: 5px;
        }
        .sidebar img {
            width: 100px;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        .content {
            flex: 1;
            padding: 20px;
        }
        .card {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats {
            display: flex;
            gap: 20px;
        }
        .section {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <img src="admin.png" alt="Student Picture" class="admin-pic">
            <h3>Navigation</h3>
            <a href="student.php" class="active"><i class="bi bi-house-door"></i> Home</a>
            <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
            <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
            <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
            <a href="attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
            <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
            <a href="enroll.php"><i class="bi bi-person-circle"></i> Enrollments</a>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>

        <!-- Main Content -->
        <div class="content">
            <h2>Welcome, <?= htmlspecialchars($studentInfo['username']) ?>!</h2>
            <p>You are logged in as a <strong><?= htmlspecialchars($_SESSION['role']) ?></strong>.</p>

            <!-- Quick Stats -->
            <div class="stats">
                <?php foreach ($stats as $label => $value): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($label) ?></h3>
                    <p><?= htmlspecialchars($value) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Announcements -->
            <div class="section">
                <h3>Recent Announcements</h3>
                <ul>
                    <?php foreach ($announcements as $a): ?>
                    <li><?= htmlspecialchars($a) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Upcoming Schedule -->
            <div class="section">
                <h3>Upcoming Schedule</h3>
                <ul>
                    <?php foreach ($upcoming as $u): ?>
                    <li><strong><?= htmlspecialchars($u['date']) ?>:</strong> <?= htmlspecialchars($u['event']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
