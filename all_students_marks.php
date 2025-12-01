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

// Get filter parameters
$filter_course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$filter_batch = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Fetch courses assigned to teacher
$coursesQuery = $conn->prepare("
    SELECT DISTINCT c.course_id, c.courseName, b.batch_id, b.batch_code
    FROM course_assignments ca
    JOIN batches b ON ca.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = ?
    ORDER BY c.courseName, b.batch_code
");
$coursesQuery->bind_param("i", $teacher_id);
$coursesQuery->execute();
$coursesResult = $coursesQuery->get_result();
$courses = [];
$batches = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[$row['course_id']] = $row['courseName'];
    $batches[] = $row;
}
$coursesQuery->close();

// Build query with filters
$whereClause = "ca.teacher_id = ? AND ce.status = 'active'";
$params = [$teacher_id];
$types = "i";

if ($filter_batch > 0) {
    $whereClause .= " AND ce.batch_id = ?";
    $params[] = $filter_batch;
    $types .= "i";
}

// Fetch all students marks data
$studentsQuery = $conn->prepare("
    SELECT 
        ce.enrollment_id, ce.student_id, ce.batch_id,
        b.batch_code, c.courseName, c.course_id,
        u.firstName, u.lastName,
        s.photo, s.student_number,
        g.test_1, g.test_2, g.test_3, g.test_4, g.test_5, g.test_6, g.test_7, g.end_examination
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN internal_grades g ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
    WHERE $whereClause
    ORDER BY c.courseName, b.batch_code, u.lastName, u.firstName
");
$studentsQuery->bind_param($types, ...$params);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();

$allStudents = [];
$courseGroups = [];
while ($row = $studentsResult->fetch_assoc()) {
    $scores = [$row['test_1'], $row['test_2'], $row['test_3'], $row['test_4'], $row['test_5'], $row['test_6'], $row['test_7'], $row['end_examination']];
    $validScores = array_filter($scores, fn($score) => $score !== null && $score !== '');
    $marks = !empty($validScores) ? array_sum($validScores) / count($validScores) : 0;
    $marks_percent = round($marks, 2);

    $status = ($marks_percent >= 50) ? 'Pass' : 'Fail';

    $student = [
        'enrollment_id' => $row['enrollment_id'],
        'student_id' => $row['student_id'],
        'student_number' => $row['student_number'] ?: 'N/A',
        'batch_id' => $row['batch_id'],
        'batch_code' => $row['batch_code'],
        'course_id' => $row['course_id'],
        'courseName' => $row['courseName'],
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
    
    $allStudents[] = $student;
    
    // Group by course and batch
    $key = $row['course_id'] . '_' . $row['batch_id'];
    if (!isset($courseGroups[$key])) {
        $courseGroups[$key] = [
            'course_name' => $row['courseName'],
            'batch_code' => $row['batch_code'],
            'students' => []
        ];
    }
    $courseGroups[$key]['students'][] = $student;
}
$studentsQuery->close();

// Sort students if needed
if ($sort_by === 'marks') {
    usort($allStudents, fn($a, $b) => $b['marks_percent'] <=> $a['marks_percent']);
    foreach ($courseGroups as &$group) {
        usort($group['students'], fn($a, $b) => $b['marks_percent'] <=> $a['marks_percent']);
    }
}

// Get top 5 students overall
$topStudents = array_slice($allStudents, 0, 5);
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
        .student-img { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; }
        .mobile-toggle { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
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
            <!-- Filters and Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                <!-- Filter Card -->
                <div class="lg:col-span-3 bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-filter mr-2"></i>Filters</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Batch</label>
                            <select name="batch" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" onchange="this.form.submit()">
                                <option value="0">All Batches</option>
                                <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['batch_id'] ?>" <?= $filter_batch == $batch['batch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($batch['courseName']) ?> - <?= htmlspecialchars($batch['batch_code']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" onchange="this.form.submit()">
                                <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="marks" <?= $sort_by === 'marks' ? 'selected' : '' ?>>Highest Marks</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Card -->
                <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-lg shadow-lg p-6 text-white">
                    <h3 class="text-sm font-semibold mb-2 opacity-90">Total Students</h3>
                    <p class="text-4xl font-bold"><?= count($allStudents) ?></p>
                    <p class="text-sm mt-2 opacity-90">
                        <i class="fas fa-check-circle mr-1"></i>
                        <?= count(array_filter($allStudents, fn($s) => $s['status'] === 'Pass')) ?> Passing
                    </p>
                </div>
            </div>

            <!-- Top Students Card -->
            <?php if ($sort_by === 'marks' && count($topStudents) > 0): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top 5 Students
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <?php foreach ($topStudents as $index => $student): ?>
                    <div class="text-center p-4 rounded-lg <?= $index === 0 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : 'bg-gray-100' ?>">
                        <div class="text-3xl mb-2">
                            <?php if ($index === 0): ?>
                                <i class="fas fa-trophy text-white"></i>
                            <?php elseif ($index === 1): ?>
                                <i class="fas fa-medal text-gray-400"></i>
                            <?php elseif ($index === 2): ?>
                                <i class="fas fa-medal text-orange-600"></i>
                            <?php else: ?>
                                <i class="fas fa-star text-purple-600"></i>
                            <?php endif; ?>
                        </div>
                        <img src="<?= htmlspecialchars($student['photo']) ?>" alt="Student" class="w-16 h-16 rounded-full mx-auto mb-2 border-4 <?= $index === 0 ? 'border-white' : 'border-gray-300' ?>">
                        <p class="font-bold <?= $index === 0 ? 'text-white' : 'text-gray-800' ?>"><?= htmlspecialchars($student['full_name']) ?></p>
                        <p class="text-sm <?= $index === 0 ? 'text-white' : 'text-gray-600' ?>"><?= htmlspecialchars($student['batch_code']) ?></p>
                        <p class="text-lg font-bold mt-2 <?= $index === 0 ? 'text-white' : 'text-purple-600' ?>"><?= $student['marks_percent'] ?>%</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grouped by Course -->
            <?php foreach ($courseGroups as $key => $group): ?>
            <div class="bg-white rounded-lg shadow-lg mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-4">
                    <h3 class="text-xl font-bold">
                        <i class="fas fa-book-open mr-2"></i>
                        <?= htmlspecialchars($group['course_name']) ?> - <?= htmlspecialchars($group['batch_code']) ?>
                    </h3>
                    <p class="text-sm opacity-90 mt-1"><?= count($group['students']) ?> Students</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600">Photo</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600">Student #</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600">Name</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T1</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T2</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T3</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T4</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T5</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T6</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">T7</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">Exam</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">Avg %</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">CGPA</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">Status</th>
                                <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['students'] as $student): ?>
                            <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <img src="<?= htmlspecialchars($student['photo']) ?>" alt="Student" class="student-img">
                                </td>
                                <td class="py-3 px-4 text-sm"><?= htmlspecialchars($student['student_number']) ?></td>
                                <td class="py-3 px-4">
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($student['full_name']) ?></p>
                                </td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_1'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_2'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_3'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_4'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_5'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_6'] ?></td>
                                <td class="py-3 px-4 text-center text-sm"><?= $student['test_7'] ?></td>
                                <td class="py-3 px-4 text-center text-sm font-semibold"><?= $student['end_examination'] ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="font-bold text-lg text-purple-600"><?= $student['marks_percent'] ?>%</span>
                                </td>
                                <td class="py-3 px-4 text-center text-sm"><?= number_format($student['cgpa'], 2) ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?= $student['status'] === 'Pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($student['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="teacher_student_information.php?id=<?= $student['student_id'] ?>" class="text-purple-600 hover:text-purple-800 font-semibold text-sm">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (count($courseGroups) === 0): ?>
            <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">No students found with the selected filters.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
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