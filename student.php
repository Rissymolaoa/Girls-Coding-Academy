<?php
session_start();

// Enable error reporting for debugging (optional, remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php"); // DB connection

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch student info from users table
$studentQuery = $conn->prepare("SELECT username, email FROM users WHERE user_id = ? AND role = 'student'");
if (!$studentQuery) {
    die("Student query preparation failed: " . $conn->error);
}
$studentQuery->bind_param("i", $user_id);
$studentQuery->execute();
$studentInfo = $studentQuery->get_result()->fetch_assoc();
if (!$studentInfo) {
    die("No student found for user_id: $user_id");
}
$studentQuery->close();

// Fetch student_id from students table
$studentIdQuery = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
if (!$studentIdQuery) {
    die("Student ID query preparation failed: " . $conn->error);
}
$studentIdQuery->bind_param("i", $user_id);
$studentIdQuery->execute();
$studentIdResult = $studentIdQuery->get_result();
if ($studentIdResult->num_rows === 0) {
    die("Error: Student profile not found. Please contact the administrator to set up your student profile.");
}
$student = $studentIdResult->fetch_assoc();
$student_id = (int)$student['student_id'];
$studentIdQuery->close();

// Fetch enrolled batches for stats
$batchQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.status = 'active'
    ORDER BY b.start_date DESC
");
if (!$batchQuery) {
    die("Batch query preparation failed: " . $conn->error);
}
$batchQuery->bind_param("i", $student_id);
$batchQuery->execute();
$batchResult = $batchQuery->get_result();
$enrolledBatches = [];
while ($batch = $batchResult->fetch_assoc()) {
    $enrolledBatches[] = $batch;
}
$batchQuery->close();

// Demo data (replace with real data as needed)
$stats = [
    "Enrolled Courses" => count($enrolledBatches),
    "Pending Assignments" => 2, // Placeholder: Replace with actual query
    "Upcoming Classes" => 3,    // Placeholder: Replace with actual query
    "Overall Grade" => "B+"    // Placeholder: Replace with actual query
];

$announcements = [
    "Exam schedule will be released next week.",
    "New coding challenge available in your Python course.",
    "School will remain closed on Friday for maintenance."
];

$upcoming = [
    ["date" => "2025-09-01", "event" => "Math Virtual Class"],
    ["date" => "2025-09-03", "event" => "Python Assignment Due"],
    ["date" => "2025-09-05", "event" => "Hackathon Workshop"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Girls Coding Academy</title>
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; }
        header {
            background: linear-gradient(90deg, #7b2cbf, #5a189a);
            color: #fff;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        .container { display: flex; }
        /* Sidebar */
        .sidebar {
            width: 240px;
            background: #343a40;
            min-height: 100vh;
            padding: 20px 15px;
            color: white;
        }
        .sidebar h3 {
            color: #adb5bd;
            margin-bottom: 15px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #f8f9fa;
            text-decoration: none;
            padding: 10px 15px;
            margin: 6px 0;
            border-radius: 6px;
            transition: background 0.3s, color 0.3s;
        }
        .sidebar a i {
            font-size: 18px;
        }
        .sidebar a:hover {
            background: #495057;
            color: #fff;
        }
        .sidebar a.active {
            background: #495057;
            color: #fff;
        }
        .student-pic {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 2px solid #ddd;
            object-fit: cover;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        /* Content area */
        .content {
            flex: 1;
            padding: 30px;
        }
        h2 { margin-bottom: 20px; color: #333; }
        p { font-size: 16px; margin-bottom: 25px; }
        footer {
            background: #fff;
            color: #333;
            text-align: center;
            padding: 15px;
            margin-top: 30px;
            border-top: 1px solid #ddd;
        }
        /* Dashboard sections */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid #eee;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-3px);
        }
        .card h3 { margin-bottom: 10px; color: #333; font-size: 16px; }
        .card p { font-size: 18px; font-weight: bold; color: #5a189a; }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            border: 1px solid #eee;
        }
        .section h3 { margin-bottom: 15px; color: #333; font-size: 18px; }
        .section ul { list-style: none; padding-left: 0; }
        .section ul li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 15px;
        }
        .section ul li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <h1>Girls Coding Academy - Student Dashboard</h1>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <img src="student.png" alt="Student Picture" class="student-pic">
            <h3>Navigation</h3>
            <a href="student.php" class="active"><i class="bi bi-house-door"></i> Home</a>
            <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
            <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
            <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
            <a href="attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
            <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
            <a href="enroll.php"><i class="bi bi-person-plus"></i> Enroll</a>
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

    <!-- Footer -->
    <footer>
        &copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.
    </footer>
</body>
</html>