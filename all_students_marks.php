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
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
$teacherQuery->close();

// Fetch teacher_id
$teacherIdQuery = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$teacherIdQuery->bind_param("i", $user_id);
$teacherIdQuery->execute();
$teacherIdResult = $teacherIdQuery->get_result();
$teacher = $teacherIdResult->fetch_assoc();
$teacher_id = (int)$teacher['teacher_id'];
$teacherIdQuery->close();

// Fetch all students marks data
$studentsQuery = $conn->prepare("
    SELECT 
        ce.enrollment_id, ce.student_id, ce.batch_id,
        b.batch_code,
        u.firstName, u.lastName,
        s.photo, s.student_number,
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
        'student_number' => $row['student_number'] ?: 'N/A',
        'batch_code' => $row['batch_code'],
        'firstName' => $row['firstName'],
        'lastName' => $row['lastName'],
        'full_name' => $row['firstName'] . ' ' . $row['lastName'],
        'photo' => $row['photo'] ?: 'default-student.jpg',
        'test_1' => $row['test_1'] ?? 'N/A',
        'test_2' => $row['test_2'] ?? 'N/A',
        'test_3' => $row['test_3'] ?? 'N/A',
        'test_4' => $row['test_4'] ?? 'N/A',
        'test_5' => $row['test_5'] ?? 'N/A',
        'test_6' => $row['test_6'] ?? 'N/A',
        'test_7' => $row['test_7'] ?? 'N/A',
        'end_examination' => $row['end_examination'] ?? 'N/A',
        'marks_percent' => $marks_percent,
        'cgpa' => $marks_percent / 10,
        'status' => $status
    ];
}
$studentsQuery->close();

// Fetch basic info for selected student if any
$selected_student_id = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$selectedStudent = null;
if ($selected_student_id > 0) {
    $selQuery = $conn->prepare("
        SELECT s.student_id, s.user_id, s.photo, s.student_number,
               u.firstName, u.lastName, u.email, u.phone, u.dob, u.gender, u.IDNumber,
               b.batch_code
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN course_enrollments ce ON s.student_id = ce.student_id
        JOIN batches b ON ce.batch_id = b.batch_id
        JOIN course_assignments ca ON b.batch_id = ca.batch_id
        WHERE s.student_id = ? AND ca.teacher_id = ? LIMIT 1
    ");
    $selQuery->bind_param("ii", $selected_student_id, $teacher_id);
    $selQuery->execute();
    $selectedStudent = $selQuery->get_result()->fetch_assoc();
    $selQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students Marks - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-header { background: linear-gradient(90deg, #7b2cbf, #5a189a); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #7b2cbf, #5a189a); position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-link { transition: all 0.3s ease; }
        .sidebar-link:hover { background: rgba(255, 255, 255, 0.1); padding-left: 1.5rem; }
        .sidebar-link.active { background: rgba(255, 255, 255, 0.2); border-left: 4px solid white; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .student-img { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; }
        .mobile-toggle { display: none; }
        .student-sidebar { width: 300px; background: white; position: fixed; right: -300px; height: 100vh; overflow-y: auto; transition: right 0.3s ease; z-index: 1001; box-shadow: -2px 0 10px rgba(0,0,0,0.1); }
        .student-sidebar.open { right: 0; }
        .student-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; }
        .student-overlay.open { display: block; }
        .main-content.expanded { margin-right: 300px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
            .student-sidebar { right: -100%; width: 100%; }
            .student-sidebar.open { right: 0; }
            .main-content.expanded { margin-right: 0; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Left Sidebar Navigation -->
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
                <a href="grades.php" class="sidebar-link flex active items-center text-white py-3 px-4 rounded mb-2">
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

    <!-- Right Sidebar for Student Info -->
    <div class="student-overlay" id="studentOverlay" onclick="toggleStudentSidebar()"></div>
    <aside class="student-sidebar" id="studentSidebar">
        <div class="p-6">
            <button onclick="toggleStudentSidebar()" class="float-right text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
            <?php if ($selectedStudent): ?>
            <div class="text-center mb-6">
                <img src="<?= htmlspecialchars($selectedStudent['photo']) ?>" alt="<?= htmlspecialchars($selectedStudent['firstName']) ?>" class="student-img mx-auto mb-4">
                <h2 class="text-xl font-bold"><?= htmlspecialchars($selectedStudent['firstName'] . ' ' . $selectedStudent['lastName']) ?></h2>
                <p class="text-gray-600">Student Number: <?= htmlspecialchars($selectedStudent['student_number']) ?></p>
                <p class="text-gray-600">Batch: <?= htmlspecialchars($selectedStudent['batch_code']) ?></p>
                <p class="text-gray-600">ID: <?= htmlspecialchars($selectedStudent['IDNumber']) ?></p>
                <p class="text-gray-600">Email: <?= htmlspecialchars($selectedStudent['email']) ?></p>
                <p class="text-gray-600">Phone: <?= htmlspecialchars($selectedStudent['phone']) ?></p>
            </div>
            <a href="teacher_student_information.php?id=<?= $selectedStudent['student_id'] ?>" class="block w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-center">
                Full Profile
            </a>
            <?php else: ?>
            <p class="text-gray-600">Select a student to view info.</p>
            <?php endif; ?>
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
                <h1 class="text-xl font-semibold">All Students Marks</h1>
                <p class="text-sm">Welcome back, <?= htmlspecialchars($teacherInfo['username']) ?>!</p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <div class="table-container bg-white rounded-lg shadow-lg overflow-hidden">
                <table class="table w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Image</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Student Number</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Name</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Last Name</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Batch</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 1</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 2</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 3</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 4</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 5</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 6</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Test 7</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">End Exam</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Marks %</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">CGPA</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Status</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allStudents as $student): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4">
                                <img src="<?= htmlspecialchars($student['photo']) ?>" alt="<?= htmlspecialchars($student['full_name']) ?>" class="student-img">
                            </td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['student_number']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['firstName']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['lastName']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($student['batch_code']) ?></td>
                            <td class="py-3 px-4"><?= $student['test_1'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_2'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_3'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_4'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_5'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_6'] ?></td>
                            <td class="py-3 px-4"><?= $student['test_7'] ?></td>
                            <td class="py-3 px-4"><?= $student['end_examination'] ?></td>
                            <td class="py-3 px-4 font-semibold"><?= $student['marks_percent'] ?>%</td>
                            <td class="py-3 px-4"><?= number_format($student['cgpa'], 2) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $student['status'] === 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= htmlspecialchars($student['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <button onclick="showStudentInfo(<?= $student['student_id'] ?>)" class="text-blue-600 hover:text-blue-800">
                                    View Info
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function showStudentInfo(studentId) {
            window.location.href = `all_students_marks.php?student=${studentId}`;
        }

        function toggleStudentSidebar() {
            const sidebar = document.getElementById('studentSidebar');
            const overlay = document.getElementById('studentOverlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
            mainContent.classList.toggle('expanded');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('mobile-open');
            mainContent.classList.toggle('expanded');
        }

        <?php if ($selected_student_id > 0): ?>
        // Auto open student sidebar if student selected
        document.addEventListener('DOMContentLoaded', function() {
            toggleStudentSidebar();
        });
        <?php endif; ?>

        // Close student sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('studentSidebar');
            const overlay = document.getElementById('studentOverlay');
            const toggleBtn = event.target.closest('button[onclick="showStudentInfo()"]');
            
            if (!sidebar.contains(event.target) && !toggleBtn && sidebar.classList.contains('open')) {
                toggleStudentSidebar();
            }
        });
    </script>
</body>
</html>