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
        .sidebar {
            transition: transform 0.3s ease;
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 16rem; /* Matches sidebar width (w-64 = 16rem) */
            }
        }
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 50;
                transform: translateX(-100%);
            }
            .sidebar:not(.sidebar-hidden) {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="gradient-header text-white py-4 px-6 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
            <p class="text-sm">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
        </div>
        <button id="menu-toggle" class="md:hidden text-white focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </header>

    <div class="flex flex-1">
        <?php include 'teacher_navigation.php'; ?>

        <main class="flex-1 p-6 main-content">
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
            <div class="table-container bg-white rounded-lg shadow-lg">
                <table class="table w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Batch Code</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Course Name</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Start Date</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">End Date</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Status</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Students</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Activities</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Grades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batchStats as $stat): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4"><?= htmlspecialchars($stat['batch_code']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($stat['courseName']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($stat['start_date']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($stat['end_date']) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $stat['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= htmlspecialchars($stat['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4"><?= $stat['student_count'] ?></td>
                            <td class="py-3 px-4"><?= $stat['activity_count'] ?></td>
                            <td class="py-3 px-4"><?= $stat['grade_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <footer class="bg-gray-800 text-white text-center py-4 mt-4">
        &copy; <?= date('Y') ?> Girls Coding Academy
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
        });

        // Ensure sidebar is visible on page load for larger screens
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('sidebar-hidden');
            }
        });

        // Initial check for sidebar visibility
        if (window.innerWidth >= 768) {
            document.getElementById('sidebar').classList.remove('sidebar-hidden');
        }
    </script>
</body>
</html>