<?php
// student_navigation.php
// This file outputs the consistent sidebar navigation for all student pages.
// Assumes session_start(), db include, and $studentInfo (from users/students join) are set before inclusion.
// Also assumes $currentPage = basename($_SERVER['PHP_SELF']); is set.

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// If $studentInfo not set, fetch it (fallback)
if (!isset($studentInfo)) {
    include("db.php");
    $user_id = $_SESSION['user_id'];
    $stmt_student = $conn->prepare("
        SELECT s.student_id, s.photo, u.username, u.email, u.role
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.user_id = ?
    ");
    $stmt_student->bind_param("i", $user_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    $studentInfo = $result_student->fetch_assoc();
    $student_id = $studentInfo['student_id'] ?? null;
}

// Determine current page for active sidebar link (fallback if not set)
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']);
}
?>

<!-- Sidebar Navigation (Fixed for all student pages) -->
<nav class="sidebar" aria-label="Student navigation">
    <img src="<?= htmlspecialchars($studentInfo['photo'] ?? 'teacher3.png') ?>" alt="Student Profile Picture" />
    <h3>Navigation</h3>
    <a href="student.php" class="<?= ($currentPage == 'student.php') ? 'active' : '' ?>"><i class="bi bi-house-door"></i> Home</a>
    <a href="student_profile.php" class="<?= ($currentPage == 'student_profile.php') ? 'active' : '' ?>"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="student_courses.php" class="<?= in_array($currentPage, ['student_courses.php','submit_test.php','submit_activity.php']) ? 'active' : '' ?>"><i class="bi bi-journal-bookmark"></i> My Courses</a>
    <a href="student_tasks.php" class="<?= ($currentPage == 'student_tasks.php') ? 'active' : '' ?>"><i class="bi bi-list-task"></i> My Tasks</a>
     <a href="enroll.php" class="<?= ($currentPage == 'enroll.php') ? 'active' : '' ?>"><i class="bi bi-plus-circle"></i> Enroll</a>
    <a href="student_announcements.php" class="<?= ($currentPage == 'student_announcements.php') ? 'active' : '' ?>"><i class="bi bi-megaphone"></i> Announcements</a>
    <a href="student_calendar.php" class="<?= ($currentPage == 'student_calendar.php') ? 'active' : '' ?>"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php" class="<?= ($currentPage == 'attendance.php') ? 'active' : '' ?>"><i class="bi bi-card-checklist"></i> My Attendance</a>
    <a href="student_marks.php" class="<?= ($currentPage == 'student_marks.php') ? 'active' : '' ?>"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a>
    <a href="student_gradebook.php" class="<?= ($currentPage == 'student_gradebook.php') ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> My Performance</a>
    <a href="make_payment.php" class="<?= ($currentPage == 'make_payment.php') ? 'active' : '' ?>"><i class="bi bi-credit-card"></i> View & Pay Invoices</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>

<style>
    /* Consistent Sidebar Styles (can be moved to a global CSS if preferred) */
    .sidebar {
        width: 250px;
        background-color: #343a40;
        color: white;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        overflow-y: auto;
        z-index: 1000;
    }
    .sidebar img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin-bottom: 15px;
        object-fit: cover;
        border: 2px solid #1abc9c;
    }
    .sidebar h3 {
        margin-bottom: 30px;
        font-weight: bold;
        text-align: center;
    }
    .sidebar a {
        width: 100%;
        color: white;
        padding: 12px 15px;
        margin: 5px 0;
        border-radius: 6px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background-color 0.3s ease;
        font-weight: 500;
        position: relative;
    }
    .sidebar a:hover {
        background-color: #495057;
    }
    .sidebar a.active {
        background-color: #495057;
        font-weight: 600;
    }
    /* White vertical line on left of active link */
    .sidebar a.active::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: white;
        border-radius: 0 4px 4px 0;
    }
    .container-flex {
        display: flex;
        height: 100vh;
    }
    .content {
        flex: 1;
        padding: 30px 40px;
        margin-left: 250px;
        overflow-y: auto;
    }
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .content {
            margin-left: 0;
        }
    }
</style>