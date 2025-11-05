<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch teacher info
$teacher_info = [];
try {
    $teacher_query = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id = ? AND role = 'teacher'");
    $teacher_query->bind_param("i", $user_id);
    $teacher_query->execute();
    $teacher_info = $teacher_query->get_result()->fetch_assoc();
    $teacher_query->close();

    if (!$teacher_info) {
        die("Teacher profile not found");
    }
} catch (Exception $e) {
    error_log("Error fetching teacher info: " . $e->getMessage());
    die("Error loading teacher profile");
}

// Fetch teacher_id
try {
    $teacher_id_res = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $teacher_id_res->bind_param("i", $user_id);
    $teacher_id_res->execute();
    $res = $teacher_id_res->get_result();
    if ($res->num_rows === 0) {
        die("Teacher profile not set up yet");
    }
    $teacher_id = (int)$res->fetch_assoc()['teacher_id'];
    $teacher_id_res->close();
} catch (Exception $e) {
    error_log("Error fetching teacher ID: " . $e->getMessage());
    die("Error loading teacher profile");
}

$message = "";

// Handle class scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_class') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $class_date = trim($conn->real_escape_string($_POST['class_date'] ?? ''));
    $start_time = trim($conn->real_escape_string($_POST['start_time'] ?? ''));
    $end_time = trim($conn->real_escape_string($_POST['end_time'] ?? ''));
    $room_number = trim($conn->real_escape_string($_POST['room_number'] ?? ''));
    $room_building = trim($conn->real_escape_string($_POST['room_building'] ?? ''));
    $room_capacity = filter_input(INPUT_POST, 'room_capacity', FILTER_VALIDATE_INT);
    $topic = trim($conn->real_escape_string($_POST['topic'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));

    if (!$batch_id || !$class_date || !$start_time || !$end_time || !$room_number || !$room_building || !$room_capacity || !$topic) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>All fields are required. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else if (strtotime($start_time) >= strtotime($end_time)) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>End time must be after start time. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else if (strtotime($class_date) < strtotime(date('Y-m-d'))) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Class date cannot be in the past. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else {
        try {
            // Check if teacher is assigned to this batch
            $check_batch = $conn->prepare("
                SELECT ca.batch_id FROM course_assignments ca
                WHERE ca.teacher_id = ? AND ca.batch_id = ?
            ");
            $check_batch->bind_param("ii", $teacher_id, $batch_id);
            $check_batch->execute();
            if ($check_batch->get_result()->num_rows === 0) {
                $check_batch->close();
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>You are not assigned to this batch. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } else {
                $check_batch->close();

                // Check for room conflicts
                $conflict_query = "
                    SELECT COUNT(*) as count FROM class_schedules
                    WHERE room_number = ? AND room_building = ? AND class_date = ?
                    AND TIME(CONCAT(class_date, ' ', start_time)) < TIME(?) 
                    AND TIME(CONCAT(class_date, ' ', end_time)) > TIME(?)
                ";
                $conflict_check = $conn->prepare($conflict_query);
                $conflict_check->bind_param("sssss", $room_number, $room_building, $class_date, $end_time, $start_time);
                $conflict_check->execute();
                $conflict_result = $conflict_check->get_result()->fetch_assoc();
                $conflict_check->close();

                if ($conflict_result['count'] > 0) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Room is already booked during this time slot. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                } else {
                    // Insert class schedule
                    $stmt = $conn->prepare("
                        INSERT INTO class_schedules 
                        (batch_id, teacher_id, class_date, start_time, end_time, room_number, room_building, room_capacity, topic, description, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("iisssssiss", $batch_id, $teacher_id, $class_date, $start_time, $end_time, $room_number, $room_building, $room_capacity, $topic, $description);
                    
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Class scheduled successfully. <i class='fas fa-check-circle ml-2'></i></div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error scheduling class: " . htmlspecialchars($stmt->error) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Error scheduling class: " . $e->getMessage());
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error scheduling class: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        }
    }
}

// Handle class update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_class') {
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $class_date = trim($conn->real_escape_string($_POST['class_date'] ?? ''));
    $start_time = trim($conn->real_escape_string($_POST['start_time'] ?? ''));
    $end_time = trim($conn->real_escape_string($_POST['end_time'] ?? ''));
    $room_number = trim($conn->real_escape_string($_POST['room_number'] ?? ''));
    $room_building = trim($conn->real_escape_string($_POST['room_building'] ?? ''));
    $room_capacity = filter_input(INPUT_POST, 'room_capacity', FILTER_VALIDATE_INT);
    $topic = trim($conn->real_escape_string($_POST['topic'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));

    if (!$schedule_id || !$class_date || !$start_time || !$end_time || !$room_number || !$room_building || !$room_capacity || !$topic) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>All fields are required. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else if (strtotime($start_time) >= strtotime($end_time)) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>End time must be after start time. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else {
        try {
            // Verify schedule ownership
            $verify_stmt = $conn->prepare("SELECT schedule_id FROM class_schedules WHERE schedule_id = ? AND teacher_id = ?");
            $verify_stmt->bind_param("ii", $schedule_id, $teacher_id);
            $verify_stmt->execute();
            if ($verify_stmt->get_result()->num_rows === 0) {
                $verify_stmt->close();
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>You do not have permission to update this schedule. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } else {
                $verify_stmt->close();

                // Check for room conflicts (excluding current schedule)
                $conflict_query = "
                    SELECT COUNT(*) as count FROM class_schedules
                    WHERE room_number = ? AND room_building = ? AND class_date = ?
                    AND schedule_id != ?
                    AND TIME(CONCAT(class_date, ' ', start_time)) < TIME(?) 
                    AND TIME(CONCAT(class_date, ' ', end_time)) > TIME(?)
                ";
                $conflict_check = $conn->prepare($conflict_query);
                $conflict_check->bind_param("ssisss", $room_number, $room_building, $class_date, $schedule_id, $end_time, $start_time);
                $conflict_check->execute();
                $conflict_result = $conflict_check->get_result()->fetch_assoc();
                $conflict_check->close();

                if ($conflict_result['count'] > 0) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Room is already booked during this time slot. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                } else {
                    $stmt = $conn->prepare("
                        UPDATE class_schedules 
                        SET class_date = ?, start_time = ?, end_time = ?, room_number = ?, room_building = ?, room_capacity = ?, topic = ?, description = ?
                        WHERE schedule_id = ? AND teacher_id = ?
                    ");
                    $stmt->bind_param("ssssssisii", $class_date, $start_time, $end_time, $room_number, $room_building, $room_capacity, $topic, $description, $schedule_id, $teacher_id);
                    
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Class updated successfully. <i class='fas fa-check-circle ml-2'></i></div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error updating class: " . htmlspecialchars($stmt->error) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Error updating class: " . $e->getMessage());
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error updating class: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        }
    }
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_class') {
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);

    if (!$schedule_id) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Invalid schedule ID. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM class_schedules WHERE schedule_id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $schedule_id, $teacher_id);
            
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Class deleted successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error deleting class: " . htmlspecialchars($stmt->error) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error deleting class: " . $e->getMessage());
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error deleting class: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        }
    }
}

// Fetch courses assigned to teacher
$courses = [];
try {
    $course_stmt = $conn->prepare("
        SELECT ca.batch_id, b.batch_code, c.courseName, b.start_date, b.end_date, b.status, COUNT(ce.enrollment_id) as student_count
        FROM course_assignments ca
        INNER JOIN batches b ON ca.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN course_enrollments ce ON b.batch_id = ce.batch_id AND ce.status = 'active'
        WHERE ca.teacher_id = ?
        GROUP BY ca.batch_id, b.batch_code, c.courseName, b.start_date, b.end_date, b.status
    ");
    $course_stmt->bind_param("i", $teacher_id);
    $course_stmt->execute();
    $courses_res = $course_stmt->get_result();
    while ($row = $courses_res->fetch_assoc()) {
        $courses[] = $row;
    }
    $course_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
}

// Fetch scheduled classes
$scheduled_classes = [];
try {
    $classes_stmt = $conn->prepare("
        SELECT cs.schedule_id, cs.batch_id, cs.class_date, cs.start_time, cs.end_time, 
               cs.room_number, cs.room_building, cs.room_capacity, cs.topic, cs.description,
               b.batch_code, c.courseName
        FROM class_schedules cs
        INNER JOIN batches b ON cs.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE cs.teacher_id = ?
        ORDER BY cs.class_date DESC, cs.start_time DESC
    ");
    $classes_stmt->bind_param("i", $teacher_id);
    $classes_stmt->execute();
    $classes_res = $classes_stmt->get_result();
    while ($row = $classes_res->fetch_assoc()) {
        $scheduled_classes[] = $row;
    }
    $classes_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching scheduled classes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Class - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-header {
            background: linear-gradient(90deg, #7b2cbf, #5a189a);
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #7b2cbf, #5a189a);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 1.5rem;
        }
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .mobile-toggle {
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-toggle {
                display: block;
            }
        }
        .schedule-card {
            transition: all 0.3s ease;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(123, 44, 191, 0.15);
        }
        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button.active {
            border-bottom: 3px solid #7b2cbf;
            color: #7b2cbf;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center mb-8">
                <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
                <h2 class="text-white text-xl font-bold">GCA Portal</h2>
            </div>
            
            <nav>
                <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i>
                    Manage Courses
                </a>
                <a href="schedule_class.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Schedule Class
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i>
                    Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-clipboard-check mr-3"></i>
                    Grade
                </a>
                <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-check mr-3"></i>
                    Mark Attendance
                </a>
                <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-envelope mr-3"></i>
                    Message Students
                </a>
                <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-user mr-3"></i>
                    Profile
                </a>
                <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="gradient-header text-white py-4 px-6 flex justify-between items-center">
            <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl font-semibold">Schedule Class</h1>
                <p class="text-sm">Welcome, <?= htmlspecialchars($teacher_info['username']) ?>!</p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?= $message ?? '' ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt text-purple-600 text-3xl mr-4"></i>
                        <div>
                            <h3 class="text-gray-600 text-sm font-semibold">Total Scheduled Classes</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= count($scheduled_classes) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center">
                        <i class="fas fa-users text-blue-600 text-3xl mr-4"></i>
                        <div>
                            <h3 class="text-gray-600 text-sm font-semibold">Assigned Batches</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= count($courses) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center">
                        <i class="fas fa-door-open text-green-600 text-3xl mr-4"></i>
                        <div>
                            <h3 class="text-gray-600 text-sm font-semibold">Upcoming Classes</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= count(array_filter($scheduled_classes, function($c) { return $c['class_date'] >= date('Y-m-d'); })) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule New Class Form -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-plus-circle text-purple-600 mr-2"></i>Schedule New Class
                </h2>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="schedule_class">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Batch *</label>
                            <select name="batch_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Choose a batch...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['batch_id'] ?>">
                                        <?= htmlspecialchars($course['batch_code'] . ' - ' . $course['courseName']) ?> 
                                        (<?= $course['student_count'] ?> students)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Topic *</label>
                            <input type="text" name="topic" required placeholder="e.g., Introduction to Python" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" placeholder="Describe the class content and objectives..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Class Date *</label>
                            <input type="date" name="class_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
                            <input type="time" name="start_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Time *</label>
                            <input type="time" name="end_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Room Capacity (Students) *</label>
                            <input type="number" name="room_capacity" required min="1" placeholder="e.g., 30" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Room Number *</label>
                            <input type="text" name="room_number" required placeholder="e.g., 101" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Building/Wing *</label>
                            <input type="text" name="room_building" required placeholder="e.g., Main Building" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Floor (Optional)</label>
                            <input type="text" name="room_floor" placeholder="e.g., 2nd Floor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700 transition-colors flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Schedule Class
                        </button>
                    </div>
                </form>
            </div>

            <!-- Scheduled Classes -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-list text-purple-600 mr-2"></i>Your Scheduled Classes
                </h2>

                <?php if (empty($scheduled_classes)): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                        <i class="fas fa-info-circle mr-3 text-xl"></i>
                        <p>No classes scheduled yet. Create your first class schedule above.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Topic</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($scheduled_classes as $class): 
                                    $class_datetime = strtotime($class['class_date'] . ' ' . $class['start_time']);
                                    $is_past = $class_datetime < time();
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($class['batch_code']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($class['courseName']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <i class="fas fa-calendar-day mr-1 text-purple-600"></i>
                                                <?= date('M d, Y', strtotime($class['class_date'])) ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= date('H:i', strtotime($class['start_time'])) ?> - <?= date('H:i', strtotime($class['end_time'])) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($class['topic']) ?></div>
                                            <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($class['description']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <i class="fas fa-door-open mr-1 text-green-600"></i>
                                                Room <?= htmlspecialchars($class['room_number']) ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($class['room_building']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-users mr-1"></i>
                                                <?= $class['room_capacity'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <?php if (!$is_past): ?>
                                                <button onclick="editClass(<?= $class['schedule_id'] ?>, '<?= $class['class_date'] ?>', '<?= $class['start_time'] ?>', '<?= $class['end_time'] ?>', '<?= htmlspecialchars($class['room_number']) ?>', '<?= htmlspecialchars($class['room_building']) ?>', '<?= $class['room_capacity'] ?>', '<?= htmlspecialchars($class['topic']) ?>', '<?= htmlspecialchars($class['description']) ?>')" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors text-xs">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this class schedule?');">
                                                <input type="hidden" name="action" value="delete_class">
                                                <input type="hidden" name="schedule_id" value="<?= $class['schedule_id'] ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors text-xs">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="sticky top-0 bg-purple-600 text-white py-4 px-6 flex justify-between items-center">
                <h3 class="text-xl font-bold">Edit Class Schedule</h3>
                <button onclick="closeEditModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-6">
                <input type="hidden" name="action" value="update_class">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Class Date *</label>
                        <input type="date" name="class_date" id="edit_class_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
                        <input type="time" name="start_time" id="edit_start_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Time *</label>
                        <input type="time" name="end_time" id="edit_end_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room Capacity *</label>
                        <input type="number" name="room_capacity" id="edit_room_capacity" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room Number *</label>
                        <input type="text" name="room_number" id="edit_room_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Building/Wing *</label>
                        <input type="text" name="room_building" id="edit_room_building" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Topic *</label>
                        <input type="text" name="topic" id="edit_topic" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Update Class
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
            
            if (sidebar.classList.contains('mobile-open')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = event.target.closest('.mobile-toggle');
            
            if (!sidebar.contains(event.target) && !toggleBtn) {
                sidebar.classList.remove('mobile-open');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function editClass(scheduleId, date, startTime, endTime, roomNumber, roomBuilding, capacity, topic, description) {
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_class_date').value = date;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_room_number').value = roomNumber;
            document.getElementById('edit_room_building').value = roomBuilding;
            document.getElementById('edit_room_capacity').value = capacity;
            document.getElementById('edit_topic').value = topic;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>