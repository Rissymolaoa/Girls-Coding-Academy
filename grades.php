<?php
session_start();

// Enable error reporting for debugging (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

include("db.php"); // Your database connection

$teacher_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id = ? AND role = 'teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $teacher_id);
$teacherQuery->execute();
$teacherQuery->store_result();

$teacherQuery->bind_result($username, $email, $gender, $phone);
$teacherInfo = null;
if ($teacherQuery->fetch()) {
    $teacherInfo = [
        'username' => $username,
        'email' => $email,
        'gender' => $gender,
        'phone' => $phone
    ];
} else {
    die("No teacher found for user_id: $teacher_id");
}
$teacherQuery->close();

// Handle POST actions: add, edit, delete
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $student_id = (int)$_POST['student_id'];
            $batch_id = (int)$_POST['batch_id'];
            $test_column = $_POST['test_column'];
            $score = floatval($_POST['score']);

            // Validate score
            if ($score < 0 || $score > 100) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error: Score must be between 0 and 100. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } elseif (!in_array($test_column, ['test_1', 'test_2', 'test_3', 'test_4', 'test_5', 'test_6', 'test_7', 'end_examination'])) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error: Invalid test column selected. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } else {
                // Check if grade record exists for student and batch
                $checkStmt = $conn->prepare("SELECT grade_id FROM internal_grades WHERE student_id = ? AND batch_id = ?");
                $checkStmt->bind_param("ii", $student_id, $batch_id);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    // Update existing record
                    $updateStmt = $conn->prepare("UPDATE internal_grades SET $test_column = ? WHERE student_id = ? AND batch_id = ?");
                    if (!$updateStmt) {
                        die("Update grade prepare failed: " . $conn->error);
                    }
                    $updateStmt->bind_param("dii", $score, $student_id, $batch_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                } else {
                    // Insert new record
                    $insertStmt = $conn->prepare("INSERT INTO internal_grades (student_id, batch_id, $test_column) VALUES (?, ?, ?)");
                    if (!$insertStmt) {
                        die("Insert grade prepare failed: " . $conn->error);
                    }
                    $insertStmt->bind_param("iid", $student_id, $batch_id, $score);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
                $checkStmt->close();
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Grade added/updated successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            }
        } elseif ($_POST['action'] === 'edit') {
            $grade_id = (int)$_POST['grade_id'];
            $test_column = $_POST['test_column'];
            $score = floatval($_POST['score']);

            if ($score < 0 || $score > 100) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error: Score must be between 0 and 100. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } elseif (!in_array($test_column, ['test_1', 'test_2', 'test_3', 'test_4', 'test_5', 'test_6', 'test_7', 'end_examination'])) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center'>Error: Invalid test column selected. <i class='fas fa-exclamation-triangle ml-2'></i></div>";
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE internal_grades
                    SET $test_column = ?
                    WHERE grade_id = ? AND batch_id IN (
                        SELECT ca.batch_id
                        FROM course_assignments ca
                        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
                        WHERE t.user_id = ?
                    )
                ");
                if (!$updateStmt) {
                    die("Update grade prepare failed: " . $conn->error);
                }
                $updateStmt->bind_param("dii", $score, $grade_id, $teacher_id);
                $updateStmt->execute();
                $updateStmt->close();
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Grade updated successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            }
        } elseif ($_POST['action'] === 'delete') {
            $grade_id = (int)$_POST['grade_id'];
            $deleteStmt = $conn->prepare("
                DELETE FROM internal_grades
                WHERE grade_id = ? AND batch_id IN (
                    SELECT ca.batch_id
                    FROM course_assignments ca
                    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
                    WHERE t.user_id = ?
                )
            ");
            if (!$deleteStmt) {
                die("Delete grade prepare failed: " . $conn->error);
            }
            $deleteStmt->bind_param("ii", $grade_id, $teacher_id);
            $deleteStmt->execute();
            $deleteStmt->close();
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Grade deleted successfully. <i class='fas fa-check-circle ml-2'></i></div>";
        }
    }
}

// Fetch assigned courses for the teacher
$courseStmt = $conn->prepare("
    SELECT ca.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
    ORDER BY b.start_date DESC
");
if (!$courseStmt) {
    die("Course prepare failed: " . $conn->error);
}
$courseStmt->bind_param("i", $teacher_id);
$courseStmt->execute();
$courseStmt->store_result();

$assignedCourses = [];
$courseStmt->bind_result($batch_id, $batch_code, $courseName);
while ($courseStmt->fetch()) {
    $assignedCourses[] = [
        'batch_id' => $batch_id,
        'batch_code' => $batch_code,
        'courseName' => $courseName
    ];
}
$courseStmt->close();

// Fetch enrolled students grouped by batch
$studentStmt = $conn->prepare("
    SELECT ce.student_id, ce.batch_id, u.username, u.email, u.firstName, u.lastName
    FROM course_enrollments ce
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN students s ON ce.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ? AND ce.status = 'active'
    ORDER BY ce.batch_id, u.username
");
if (!$studentStmt) {
    die("Student prepare failed: " . $conn->error);
}
$studentStmt->bind_param("i", $teacher_id);
$studentStmt->execute();
$studentStmt->store_result();

$studentsByBatch = [];
$studentStmt->bind_result($student_id, $batch_id, $username, $email, $firstName, $lastName);
while ($studentStmt->fetch()) {
    $studentsByBatch[$batch_id][] = [
        'student_id' => $student_id,
        'username' => $username,
        'email' => $email,
        'full_name' => $firstName . ' ' . $lastName
    ];
}
$studentStmt->close();

// Fetch grades grouped by batch
$gradesStmt = $conn->prepare("
    SELECT g.grade_id, g.student_id, g.batch_id, g.test_1, g.test_2, g.test_3, g.test_4, g.test_5, g.test_6, g.test_7, g.end_examination, g.created_at, u.username, u.email, u.firstName, u.lastName
    FROM internal_grades g
    INNER JOIN course_enrollments ce ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN students s ON ce.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY ce.batch_id, u.username
");
if (!$gradesStmt) {
    die("Grades prepare failed: " . $conn->error);
}
$gradesStmt->bind_param("i", $teacher_id);
$gradesStmt->execute();
$gradesStmt->store_result();

$gradesByBatch = [];
$gradesStmt->bind_result($g_grade_id, $g_student_id, $g_batch_id, $g_test_1, $g_test_2, $g_test_3, $g_test_4, $g_test_5, $g_test_6, $g_test_7, $g_end_examination, $g_created_at, $g_username, $g_email, $g_firstName, $g_lastName);
while ($gradesStmt->fetch()) {
    $gradesByBatch[$g_batch_id][] = [
        'grade_id' => $g_grade_id,
        'student_id' => $g_student_id,
        'batch_id' => $g_batch_id,
        'test_1' => $g_test_1,
        'test_2' => $g_test_2,
        'test_3' => $g_test_3,
        'test_4' => $g_test_4,
        'test_5' => $g_test_5,
        'test_6' => $g_test_6,
        'test_7' => $g_test_7,
        'end_examination' => $g_end_examination,
        'created_at' => $g_created_at,
        'username' => $g_username,
        'email' => $g_email,
        'full_name' => $g_firstName . ' ' . $g_lastName
    ];
}
$gradesStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Management - Girls Coding Academy</title>
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
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i>
                    Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
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
                <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?>!</h1>
                <p class="text-sm">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?= $message ?? '' ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Grade Management</h2>
                <p class="text-gray-600">Add or update grades for your students</p>
            </div>

            <!-- Add New Grade Form -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-plus-circle mr-2 text-purple-600"></i>Add New Grade
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Batch</label>
                            <select name="batch_id" id="batch_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Select a batch</option>
                                <?php foreach ($assignedCourses as $course): ?>
                                    <option value="<?= $course['batch_id'] ?>">
                                        <?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Student</label>
                            <select name="student_id" id="student_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Select a student</option>
                                <?php foreach ($studentsByBatch as $batch_id => $students): ?>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['student_id'] ?>" data-batch-id="<?= $batch_id ?>" style="display: none;">
                                            <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Test</label>
                            <select name="test_column" id="test_column" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Select a test</option>
                                <option value="test_1">Test 1</option>
                                <option value="test_2">Test 2</option>
                                <option value="test_3">Test 3</option>
                                <option value="test_4">Test 4</option>
                                <option value="test_5">Test 5</option>
                                <option value="test_6">Test 6</option>
                                <option value="test_7">Test 7</option>
                                <option value="end_examination">End Examination</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100)</label>
                            <input type="number" name="score" id="score" step="0.01" min="0" max="100" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-md font-medium hover:bg-purple-700 transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            Add Grade
                        </button>
                    </div>
                </form>
            </div>

            <!-- Grades by Batch -->
            <?php if (!empty($assignedCourses)): ?>
                <?php foreach ($assignedCourses as $course): ?>
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-graduation-cap mr-2 text-purple-600"></i>Batch: <?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)
                        </h3>
                        <?php if (isset($gradesByBatch[$course['batch_id']]) && count($gradesByBatch[$course['batch_id']]) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 1</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 2</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 3</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 4</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 5</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 6</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test 7</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Exam</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($gradesByBatch[$course['batch_id']] as $grade): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($grade['full_name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($grade['email']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_1'] !== null ? htmlspecialchars($grade['test_1']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_2'] !== null ? htmlspecialchars($grade['test_2']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_3'] !== null ? htmlspecialchars($grade['test_3']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_4'] !== null ? htmlspecialchars($grade['test_4']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_5'] !== null ? htmlspecialchars($grade['test_5']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_6'] !== null ? htmlspecialchars($grade['test_6']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['test_7'] !== null ? htmlspecialchars($grade['test_7']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium"><?= $grade['end_examination'] !== null ? htmlspecialchars($grade['end_examination']) : 'N/A' ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($grade['created_at']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>">
                                                            <select name="test_column" class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                                                <option value="test_1">Test 1</option>
                                                                <option value="test_2">Test 2</option>
                                                                <option value="test_3">Test 3</option>
                                                                <option value="test_4">Test 4</option>
                                                                <option value="test_5">Test 5</option>
                                                                <option value="test_6">Test 6</option>
                                                                <option value="test_7">Test 7</option>
                                                                <option value="end_examination">End Exam</option>
                                                            </select>
                                                            <input type="number" name="score" step="0.01" min="0" max="100" required class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 ml-1">
                                                            <button type="submit" class="px-3 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700 ml-1">Update</button>
                                                        </form>
                                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>">
                                                            <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                                <i class="fas fa-info-circle mr-3 text-xl"></i>
                                <p>No grades recorded for this batch yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <p>No batches assigned, so no grades to display.</p>
                </div>
            <?php endif; ?>
        </main>
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

        // Filter students by batch in the Add Grade form
        document.getElementById('batch_id').addEventListener('change', function() {
            const batchId = this.value;
            const studentSelect = document.getElementById('student_id');
            const options = studentSelect.querySelectorAll('option[data-batch-id]');
            options.forEach(option => {
                if (batchId === '' || option.getAttribute('data-batch-id') === batchId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                    if (option.selected) {
                        studentSelect.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html>