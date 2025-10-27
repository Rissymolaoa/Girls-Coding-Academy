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

$user_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
if (!$teacherInfo) {
    die("No teacher found for user_id: $user_id");
}
$teacherQuery->close();

// Fetch teacher_id
$teacherIdQuery = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
if (!$teacherIdQuery) {
    die("Teacher ID query preparation failed: " . $conn->error);
}
$teacherIdQuery->bind_param("i", $user_id);
$teacherIdQuery->execute();
$teacherIdResult = $teacherIdQuery->get_result();
if ($teacherIdResult->num_rows === 0) {
    die("Error: Teacher profile not found. Please contact the administrator.");
}
$teacher = $teacherIdResult->fetch_assoc();
$teacher_id = (int)$teacher['teacher_id'];
$teacherIdQuery->close();

// Fetch total stats
$totalBatchesQuery = $conn->prepare("SELECT COUNT(DISTINCT ca.batch_id) AS total_batches FROM course_assignments ca WHERE ca.teacher_id = ?");
$totalBatchesQuery->bind_param("i", $teacher_id);
$totalBatchesQuery->execute();
$totalBatchesResult = $totalBatchesQuery->get_result()->fetch_assoc();
$total_batches = $totalBatchesResult['total_batches'];
$totalBatchesQuery->close();

$totalLearnersQuery = $conn->prepare("SELECT COUNT(*) AS total_learners FROM course_enrollments ce INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id WHERE ca.teacher_id = ? AND ce.status = 'active'");
$totalLearnersQuery->bind_param("i", $teacher_id);
$totalLearnersQuery->execute();
$totalLearnersResult = $totalLearnersQuery->get_result()->fetch_assoc();
$total_learners = $totalLearnersResult['total_learners'];
$totalLearnersQuery->close();

$totalActivitiesQuery = $conn->prepare("SELECT COUNT(*) AS total_activities FROM activities WHERE teacher_id = ?");
$totalActivitiesQuery->bind_param("i", $teacher_id);
$totalActivitiesQuery->execute();
$totalActivitiesResult = $totalActivitiesQuery->get_result()->fetch_assoc();
$total_activities = $totalActivitiesResult['total_activities'];
$totalActivitiesQuery->close();

$totalInternalsQuery = $conn->prepare("SELECT COUNT(*) AS total_internals FROM internal_grades g INNER JOIN course_enrollments ce ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id WHERE ca.teacher_id = ?");
if (!$totalInternalsQuery) {
    die("Total internals query preparation failed: " . $conn->error);
}
$totalInternalsQuery->bind_param("i", $teacher_id);
$totalInternalsQuery->execute();
$totalInternalsResult = $totalInternalsQuery->get_result()->fetch_assoc();
$total_internals = $totalInternalsResult['total_internals'];
$totalInternalsQuery->close();

// Fetch batch stats for cards
$courseQuery = $conn->prepare("SELECT ca.assignment_id, b.batch_id, b.batch_code, b.start_date, b.end_date, b.status, c.courseName FROM course_assignments ca INNER JOIN batches b ON ca.batch_id = b.batch_id INNER JOIN courses c ON b.course_id = c.course_id INNER JOIN teachers t ON ca.teacher_id = t.teacher_id WHERE t.user_id = ? ORDER BY b.start_date DESC");
if (!$courseQuery) {
    die("Course query preparation failed: " . $conn->error);
}
$courseQuery->bind_param("i", $user_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
$courseQuery->close();

$batchStats = [];
while ($batch = $assignedCourses->fetch_assoc()) {
    $batch_id = (int)$batch['batch_id'];
    $studentCountQuery = $conn->prepare("SELECT COUNT(*) AS student_count FROM course_enrollments WHERE batch_id = ? AND status = 'active'");
    $studentCountQuery->bind_param("i", $batch_id);
    $studentCountQuery->execute();
    $studentCountResult = $studentCountQuery->get_result()->fetch_assoc();
    $student_count = $studentCountResult['student_count'];
    $studentCountQuery->close();

    $activityCountQuery = $conn->prepare("SELECT COUNT(*) AS total_activities FROM activities WHERE batch_id = ?");
    $activityCountQuery->bind_param("i", $batch_id);
    $activityCountQuery->execute();
    $activityCountResult = $activityCountQuery->get_result()->fetch_assoc();
    $activity_count = $activityCountResult['total_activities'];
    $activityCountQuery->close();

    $gradeCountQuery = $conn->prepare("SELECT COUNT(*) AS grade_count FROM internal_grades WHERE batch_id = ?");
    $gradeCountQuery->bind_param("i", $batch_id);
    $gradeCountQuery->execute();
    $gradeCountResult = $gradeCountQuery->get_result()->fetch_assoc();
    $grade_count = $gradeCountResult['grade_count'];
    $gradeCountQuery->close();

    $batchStats[] = [
        'batch_id' => $batch_id,
        'batch_code' => $batch['batch_code'],
        'courseName' => $batch['courseName'],
        'start_date' => $batch['start_date'],
        'end_date' => $batch['end_date'],
        'status' => $batch['status'],
        'student_count' => $student_count,
        'activity_count' => $activity_count,
        'grade_count' => $grade_count,
    ];
}

// Fetch all students data for top 3 and table
$studentsQuery = $conn->prepare("
    SELECT 
        ce.enrollment_id, ce.student_id, ce.batch_id, ce.status as enrollment_status,
        b.batch_code,
        u.firstName, u.lastName,
        s.photo,
        g.test_1, g.test_2, g.test_3, g.test_4, g.test_5, g.test_6, g.test_7, g.end_examination
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN internal_grades g ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
    WHERE ca.teacher_id = ? AND ce.status = 'active'
    ORDER BY u.lastName, u.firstName
");
if (!$studentsQuery) {
    die("Students query preparation failed: " . $conn->error);
}
$studentsQuery->bind_param("i", $teacher_id);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();

$allStudents = [];
while ($row = $studentsResult->fetch_assoc()) {
    $scores = [$row['test_1'], $row['test_2'], $row['test_3'], $row['test_4'], $row['test_5'], $row['test_6'], $row['test_7'], $row['end_examination']];
    $validScores = array_filter($scores, fn($score) => $score !== null && $score !== '');
    $marks = !empty($validScores) ? array_sum($validScores) / count($validScores) : 0;
    $marks_percent = round($marks, 2);

    $status = ($marks_percent >= 50) ? 'Pass' : 'Fail';

    $allStudents[] = [
        'enrollment_id' => $row['enrollment_id'],
        'student_id' => $row['student_id'],
        'batch_code' => $row['batch_code'],
        'firstName' => $row['firstName'],
        'lastName' => $row['lastName'],
        'full_name' => $row['firstName'] . ' ' . $row['lastName'],
        'photo' => $row['photo'] ?: 'default-student.jpg',
        'marks_percent' => $marks_percent,
        'cgpa' => $marks_percent / 10,
        'status' => $status
    ];
}
$studentsQuery->close();

usort($allStudents, fn($a, $b) => $b['marks_percent'] <=> $a['marks_percent']);
$topStudents = array_slice($allStudents, 0, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Girls Coding Academy</title>
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
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .table-container {
            overflow-x: auto;
        }
        .table th, .table td {
            white-space: nowrap;
        }
        .student-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }
        .batch-card {
            cursor: pointer;
        }
        .top-student-card {
            cursor: pointer;
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
                <a href="teacher_dashboard.php" class="sidebar-link flex active items-center text-white py-3 px-4 rounded mb-2">
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
                <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?>!</h1>
                <p class="text-sm">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Dashboard Overview</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card bg-white rounded-lg shadow-lg p-6">
                    <i class="fas fa-chalkboard-teacher text-3xl text-purple-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Total Batches</h3>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_batches ?></p>
                </div>
                <div class="card bg-white rounded-lg shadow-lg p-6">
                    <i class="fas fa-users text-3xl text-purple-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Total Learners</h3>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_learners ?></p>
                </div>
                <div class="card bg-white rounded-lg shadow-lg p-6">
                    <i class="fas fa-tasks text-3xl text-purple-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Total Activities</h3>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_activities ?></p>
                </div>
                <div class="card bg-white rounded-lg shadow-lg p-6">
                    <i class="fas fa-clipboard-check text-3xl text-purple-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Total Internals Graded</h3>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_internals ?></p>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-6">Batches Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($batchStats as $stat): ?>
                <a href="batch_details.php?id=<?= $stat['batch_id'] ?>" class="batch-card card bg-white rounded-lg shadow-lg p-6 no-underline">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2"><?= htmlspecialchars($stat['batch_code']) ?> - <?= htmlspecialchars($stat['courseName']) ?></h3>
                    <p class="text-gray-600 mb-4">Students Enrolled: <?= $stat['student_count'] ?></p>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        <?= htmlspecialchars($stat['status']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-6">Top 3 Students</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php foreach ($topStudents as $index => $student): ?>
                <a href="teacher_student_information.php?id=<?= $student['student_id'] ?>" class="top-student-card card bg-white rounded-lg shadow-lg p-6 no-underline text-center">
                    <img src="<?= htmlspecialchars($student['photo']) ?>" alt="<?= htmlspecialchars($student['full_name']) ?>" class="student-img mx-auto mb-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2"><?= htmlspecialchars($student['full_name']) ?></h3>
                    <div class="text-3xl mb-2">
                        <?php if ($index == 0): ?>
                            <i class="fas fa-trophy text-yellow-500"></i>
                        <?php elseif ($index == 1): ?>
                            <i class="fas fa-trophy text-gray-400"></i>
                        <?php else: ?>
                            <i class="fas fa-trophy text-orange-500"></i>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600">Position <?= $index + 1 ?></p>
                    <p class="text-blue-600 font-semibold"><?= $student['marks_percent'] ?>%</p>
                </a>
                <?php endforeach; ?>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-6">Students Marks</h2>
            <div class="flex justify-between items-center mb-4">
                <div></div>
                <a href="all_students_marks.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                    View All
                </a>
            </div>
            <div class="table-container bg-white rounded-lg shadow-lg overflow-hidden">
                <table class="table w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Image</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Name</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Last Name</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Batch</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Marks %</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">CGPA</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allStudents as $student): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4">
                                <img src="<?= htmlspecialchars($student['photo']) ?>" alt="<?= htmlspecialchars($student['full_name']) ?>" class="student-img">
                            </td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['firstName']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['lastName']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['batch_code']) ?></td>
                            <td class="py-3 px-4 font-semibold"><?= $student['marks_percent'] ?>%</td>
                            <td class="py-3 px-4"><?= number_format($student['cgpa'], 2) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $student['status'] === 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= htmlspecialchars($student['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>

        
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('mobile-open');
            
            // Close sidebar when clicking outside on mobile
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