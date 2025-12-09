<?php
// teacher_navigation.php
// No session_start() here — already started in the main file
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher name and photo
$stmt = $conn->prepare("SELECT username, photo FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$teacher_name = htmlspecialchars($teacher['username'] ?? 'Teacher');
$teacher_photo = htmlspecialchars($teacher['photo'] ?? 'uploads/default-teacher.jpg');
$stmt->close();

// Get current page name for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Inter',sans-serif}
        .gradient-header {background: linear-gradient(90deg, #7b2cbf, #5a189a); color:white}
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #7b2cbf, #5a189a);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.hidden {transform: translateX(-100%);}
        .sidebar-link {
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 1rem;
        }
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.15);
            padding-left: 1.5rem;
        }
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.25);
            border-left: 4px solid white;
            font-weight: 600;
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .sidebar {transform: translateX(-100%);}
            .sidebar.mobile-open {transform: translateX(0);}
            .main-content {margin-left: 0;}
            .mobile-toggle {display: block !important;}
        }
        .mobile-toggle {display: none;}
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="p-6">
        <!-- Logo -->
        <div class="flex items-center mb-10">
            <i class="fas fa-graduation-cap text-white text-4xl mr-3"></i>
            <h2 class="text-white text-2xl font-bold">GCA Portal</h2>
        </div>

        <!-- Navigation Links -->
        <nav class="space-y-2">
            <a href="teacher_dashboard.php" 
               class="sidebar-link <?= $current_page === 'teacher_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home mr-4 text-lg"></i>
                Dashboard
            </a>

            <a href="manage_timetable.php" 
               class="sidebar-link <?= $current_page === 'manage_timetable.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week mr-4 text-lg"></i>
                Manage Timetable
            </a>

            <a href="manage_teacher_courses.php" 
               class="sidebar-link <?= in_array($current_page, ['manage_teacher_courses.php', 'schedule_class.php']) ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher mr-4 text-lg"></i>
                My Courses
            </a>

            <a href="upload_materials.php" 
               class="sidebar-link <?= $current_page === 'upload_materials.php' ? 'active' : '' ?>">
                <i class="fas fa-book mr-4 text-lg"></i>
                Upload Materials
            </a>

            <a href="grades.php" 
               class="sidebar-link <?= $current_page === 'grades.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check mr-4 text-lg"></i>
                Grade Students
            </a>

            <a href="mark_attendance.php" 
               class="sidebar-link <?= $current_page === 'mark_attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check mr-4 text-lg"></i>
                Mark Attendance
            </a>

            <a href="message_students.php" 
               class="sidebar-link <?= $current_page === 'message_students.php' ? 'active' : '' ?>">
                <i class="fas fa-envelope mr-4 text-lg"></i>
                Message Students
            </a>

            <a href="teacher_profile.php" 
               class="sidebar-link <?= $current_page === 'teacher_profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user mr-4 text-lg"></i>
                My Profile
            </a>

            <hr class="border-white border-opacity-20 my-4">

            <a href="logout.php" class="sidebar-link text-red-200 hover:text-white hover:bg-red-600 hover:bg-opacity-30">
                <i class="fas fa-sign-out-alt mr-4 text-lg"></i>
                Logout
            </a>
        </nav>
    </div>
</aside>

<!-- TOP HEADER BAR (same purple gradient) -->
<header class="gradient-header shadow-lg py-4 px-6 flex justify-between items-center fixed top-0 left-0 right-0 z-40">
    <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="flex items-center space-x-4">
        <div class="text-right">
            <h1 class="text-xl font-bold">Welcome back,</h1>
            <p class="text-sm opacity-90"><?= $teacher_name ?></p>
        </div>
        <img src="<?= $teacher_photo ?>" alt="Teacher Photo" 
             class="w-12 h-12 rounded-full ring-4 ring-white ring-opacity-30 object-cover">
    </div>
</header>

<!-- MAIN CONTENT WRAPPER (so content doesn't go under header) -->
<div class="main-content pt-20" id="mainContent">
    <!-- Your page content goes here -->