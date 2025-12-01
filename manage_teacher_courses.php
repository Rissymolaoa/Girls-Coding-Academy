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

// Handle student inactivation request
$message = "";
$show_reason_modal = false;
$modal_student = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'open_reason_modal') {
        $show_reason_modal = true;
        $modal_student = [
            'enrollment_id' => filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT),
            'student_name' => trim($_POST['student_name'] ?? ''),
            'student_email' => trim($_POST['student_email'] ?? ''),
            'batch_code' => trim($_POST['batch_code'] ?? '')
        ];
    } elseif ($_POST['action'] === 'submit_inactivation_request') {
        $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
        $student_name = trim($_POST['student_name'] ?? '');
        $student_email = trim($_POST['student_email'] ?? '');
        $batch_code = trim($_POST['batch_code'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        
        if ($enrollment_id && $student_name && $student_email && $batch_code && $reason) {
            try {
                $stmt = $conn->prepare("INSERT INTO inactivation_requests (enrollment_id, student_name, student_email, batch_code, teacher_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("isssss", $enrollment_id, $student_name, $student_email, $batch_code, $teacher_id, $reason);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Inactivation request submitted to administrator. <i class='fas fa-check-circle ml-2'></i></div>";
            } catch (Exception $e) {
                error_log("Error requesting inactivation: " . $e->getMessage());
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error requesting inactivation: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            }
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Please provide a reason for inactivation request. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        }
    }
}

// Handle activity assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_activity') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $resource_file = '';

    if (!$batch_id || !$title || !$description || !$due_date) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>All fields are required for activity assignment. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else {
        $upload_error = false;
        
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['resource_file'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Allowed file types: PDF, JPG, PNG. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    $upload_error = true;
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>File size exceeds 200MB. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    $upload_error = true;
                } else {
                    $original_name = basename($file['name']);
                    $filepath = $upload_dir . time() . '_' . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $resource_file = $filepath;
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error uploading file. Please try again. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                        $upload_error = true;
                    }
                }
            }
        }

        if (!$upload_error) {
            try {
                $stmt = $conn->prepare("INSERT INTO activities (batch_id, teacher_id, title, description, due_date, resource_file, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("iissss", $batch_id, $teacher_id, $title, $description, $due_date, $resource_file);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Activity assigned successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            } catch (Exception $e) {
                error_log("Error assigning activity: " . $e->getMessage());
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error assigning activity: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            }
        }
    }
}

// Handle test assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_test') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $max_score = filter_var($_POST['max_score'] ?? 0, FILTER_VALIDATE_FLOAT);
    $resource_file = '';

    if (!$batch_id || !$title || !$description || !$due_date || $max_score === false || $max_score <= 0 || $max_score > 100) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>All fields are required, and max score must be between 0 and 100. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
    } else {
        $upload_error = false;
        
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['resource_file'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Allowed file types: PDF, JPG, PNG. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    $upload_error = true;
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>File size exceeds 200MB. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    $upload_error = true;
                } else {
                    $original_name = basename($file['name']);
                    $filepath = $upload_dir . time() . '_' . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $resource_file = $filepath;
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error uploading file. Please try again. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                        $upload_error = true;
                    }
                }
            }
        }

        if (!$upload_error) {
            try {
                $stmt = $conn->prepare("INSERT INTO tests (batch_id, teacher_id, title, description, due_date, max_score, resource_file, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("iissdss", $batch_id, $teacher_id, $title, $description, $due_date, $max_score, $resource_file);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Test assigned successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            } catch (Exception $e) {
                error_log("Error assigning test: " . $e->getMessage());
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error assigning test: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            }
        }
    }
}

// Fetch courses assigned to teacher
$courses = [];
try {
    $course_stmt = $conn->prepare("
        SELECT ca.batch_id, b.batch_code, c.courseName, b.start_date, b.end_date, b.status
        FROM course_assignments ca
        INNER JOIN batches b ON ca.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE ca.teacher_id = ?
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
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error loading courses: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
}

$selected_course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?:
                     filter_input(INPUT_POST, 'selected_course_id', FILTER_VALIDATE_INT);

// Fetch students if course is selected
$students_by_batch = [];
if ($selected_course_id) {
    // Validate batch_id
    try {
        $stmt_check_batch = $conn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
        $stmt_check_batch->bind_param("i", $selected_course_id);
        $stmt_check_batch->execute();
        $res_check_batch = $stmt_check_batch->get_result();
        if ($res_check_batch->num_rows === 0) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Invalid batch ID selected. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            $selected_course_id = null;
        }
        $stmt_check_batch->close();
    } catch (Exception $e) {
        error_log("Error validating batch ID: " . $e->getMessage());
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error validating batch ID: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        $selected_course_id = null;
    }

    if ($selected_course_id) {
        // Students
        try {
            $stmt_students = $conn->prepare("
                SELECT ce.enrollment_id, ce.batch_id, ce.student_id, u.firstName, u.lastName, u.email, ce.status
                FROM course_enrollments ce
                INNER JOIN students s ON ce.student_id = s.student_id
                INNER JOIN users u ON s.user_id = u.user_id
                WHERE ce.batch_id = ? AND ce.status = 'active'
                ORDER BY u.firstName
            ");
            $stmt_students->bind_param("i", $selected_course_id);
            $stmt_students->execute();
            $res_students = $stmt_students->get_result();
            $students_by_batch[$selected_course_id] = [];
            while ($row = $res_students->fetch_assoc()) {
                $students_by_batch[$selected_course_id][] = $row;
            }
            $stmt_students->close();
        } catch (Exception $e) {
            error_log("Error fetching students: " . $e->getMessage());
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error fetching students: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teacher Courses - Girls Coding Academy</title>
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
        .batch-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .batch-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(123, 44, 191, 0.2);
            border-color: #7b2cbf;
        }
        .batch-card.selected {
            border-color: #7b2cbf;
            background: linear-gradient(135deg, #f3e7ff 0%, #ffffff 100%);
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
                <a href="manage_teacher_courses.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i>
                    Manage Courses
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i>
                    Upload Materials
                </a>
                <a href="view_assigned_tests.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-list-check mr-3"></i>
                    Tests & Activities
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
                <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacher_info['username']) ?>!</h1>
                <p class="text-sm">Email: <?= htmlspecialchars($teacher_info['email']) ?> | Gender: <?= htmlspecialchars($teacher_info['gender']) ?> | Phone: <?= htmlspecialchars($teacher_info['phone']) ?></p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?= $message ?? '' ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Your Courses / Batches</h2>
                <p class="text-gray-600">Select a batch to manage students, assign activities, or assign tests</p>
            </div>

            <!-- Batch Selection Cards -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-folder-open mr-2 text-purple-600"></i>Select Batch
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <a href="?course_id=<?= $course['batch_id'] ?>" 
                           class="batch-card bg-white rounded-lg shadow-lg p-6 no-underline <?= $course['batch_id'] == $selected_course_id ? 'selected' : '' ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">
                                        <?= htmlspecialchars($course['courseName']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600">Batch Code: <?= htmlspecialchars($course['batch_code']) ?></p>
                                </div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?= $course['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= htmlspecialchars($course['status']) ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-calendar mr-2 text-purple-600"></i>
                                    <span class="text-sm"><?= date('M j, Y', strtotime($course['start_date'])) ?> - <?= date('M j, Y', strtotime($course['end_date'])) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Batch Management -->
            <?php if ($selected_course_id): ?>
                <?php
                // Fetch batch details
                try {
                    $stmt_batch = $conn->prepare("
                        SELECT b.batch_code, c.courseName
                        FROM batches b
                        INNER JOIN courses c ON b.course_id = c.course_id
                        WHERE b.batch_id = ?
                    ");
                    $stmt_batch->bind_param("i", $selected_course_id);
                    $stmt_batch->execute();
                    $batch_details = $stmt_batch->get_result()->fetch_assoc();
                    $stmt_batch->close();

                    if (!$batch_details) {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Invalid batch selected. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                        $selected_course_id = null;
                    }
                } catch (Exception $e) {
                    error_log("Error fetching batch details: " . $e->getMessage());
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error fetching batch details: " . htmlspecialchars($e->getMessage()) . " <i class='fas fa-exclamation-triangle ml-2'></i></div>";
                    $selected_course_id = null;
                }
                ?>

                <?php if ($selected_course_id): ?>
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-graduation-cap mr-2 text-purple-600"></i>Batch: <?= htmlspecialchars($batch_details['batch_code']) ?> (<?= htmlspecialchars($batch_details['courseName']) ?>)
                            </h3>
                        </div>

                        <!-- Students enrolled -->
                        <div class="mb-8">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Enrolled Students</h4>
                            <?php if (!empty($students_by_batch[$selected_course_id])): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students_by_batch[$selected_course_id] as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($student['email']) ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="open_reason_modal">
                                                            <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                                            <input type="hidden" name="student_name" value="<?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?>">
                                                            <input type="hidden" name="student_email" value="<?= htmlspecialchars($student['email']) ?>">
                                                            <input type="hidden" name="batch_code" value="<?= htmlspecialchars($batch_details['batch_code']) ?>">
                                                            <button type="submit" class="px-3 py-1 bg-yellow-500 text-white rounded text-xs hover:bg-yellow-600 transition-colors flex items-center">
                                                                <i class="fas fa-paper-plane mr-1"></i>Request Inactivation
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                                    <p>No students enrolled in this batch.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Assign activity -->
                        <div class="mb-8">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Assign Class Activity / Homework</h4>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="action" value="assign_activity">
                                <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                        <input type="date" name="due_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                    <input type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-md font-medium hover:bg-purple-700 transition-colors flex items-center">
                                        <i class="fas fa-plus mr-2"></i>
                                        Assign Activity
                                    </button>
                                    <a href="view_assigned_tests.php?batch_id=<?= $selected_course_id ?>" class="px-6 py-2 bg-gray-500 text-white rounded-md font-medium hover:bg-gray-600 transition-colors">View Assigned Activities</a>
                                </div>
                            </form>
                        </div>

                        <!-- Assign test -->
                        <div class="mb-8">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Assign Test</h4>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="action" value="assign_test">
                                <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Test Title</label>
                                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                        <input type="date" name="due_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Score</label>
                                        <input type="number" name="max_score" min="0" max="100" step="0.1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                    <input type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-md font-medium hover:bg-purple-700 transition-colors flex items-center">
                                        <i class="fas fa-plus mr-2"></i>
                                        Assign Test
                                    </button>
                                    <a href="view_assigned_tests.php?batch_id=<?= $selected_course_id ?>" class="px-6 py-2 bg-gray-500 text-white rounded-md font-medium hover:bg-gray-600 transition-colors">View Assigned Tests</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($courses)): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <p>No courses assigned to you.</p>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <p>Please select a batch to manage.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Inactivation Reason Modal -->
    <?php if ($show_reason_modal && $modal_student): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-comment-dots mr-2 text-yellow-500"></i>Reason for Inactivation Request
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Student: <strong><?= htmlspecialchars($modal_student['student_name']) ?></strong><br>
                Email: <strong><?= htmlspecialchars($modal_student['student_email']) ?></strong>
            </p>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="submit_inactivation_request">
                <input type="hidden" name="enrollment_id" value="<?= $modal_student['enrollment_id'] ?>">
                <input type="hidden" name="student_name" value="<?= htmlspecialchars($modal_student['student_name']) ?>">
                <input type="hidden" name="student_email" value="<?= htmlspecialchars($modal_student['student_email']) ?>">
                <input type="hidden" name="batch_code" value="<?= htmlspecialchars($modal_student['batch_code']) ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Inactivation</label>
                    <textarea name="reason" rows="4" required placeholder="Please explain why you are requesting this student's inactivation..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="flex gap-3 justify-end pt-4">
                    <a href="?course_id=<?= $selected_course_id ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md font-medium hover:bg-gray-400 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md font-medium hover:bg-yellow-600 transition-colors flex items-center">
                        <i class="fas fa-check mr-2"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
    </script>
</body>
</html>