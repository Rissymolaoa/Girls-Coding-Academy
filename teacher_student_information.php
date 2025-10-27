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
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

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

// Fetch student basic info
$studentQuery = $conn->prepare("
    SELECT s.student_id, s.user_id, s.photo,
           u.firstName, u.lastName, u.email, u.phone, u.dob, u.gender, u.IDNumber,
           a.address1, a.streetName, a.postalCode, a.district, a.country
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    WHERE s.student_id = ?
");
$studentQuery->bind_param("i", $student_id);
$studentQuery->execute();
$student = $studentQuery->get_result()->fetch_assoc();
$studentQuery->close();

if (!$student) {
    die("Student not found.");
}

// Fetch parents
$parentsQuery = $conn->prepare("
    SELECT p.parent_id, p.relationship, p.photo,
           u.firstName, u.lastName, u.email, u.phone
    FROM parents p
    JOIN parent_students ps ON p.parent_id = ps.parent_id
    JOIN users u ON p.user_id = u.user_id
    WHERE ps.student_id = ?
");
$parentsQuery->bind_param("i", $student_id);
$parentsQuery->execute();
$parentsResult = $parentsQuery->get_result();
$parents = [];
while ($parent = $parentsResult->fetch_assoc()) {
    $parents[] = $parent;
}
$parentsQuery->close();

// Fetch health info
$healthQuery = $conn->prepare("SELECT * FROM student_medical_info WHERE student_id = ?");
$healthQuery->bind_param("i", $student_id);
$healthQuery->execute();
$health = $healthQuery->get_result()->fetch_assoc();
$healthQuery->close();

// Fetch marks (internal grades) - across teacher's batches
$marksQuery = $conn->prepare("
    SELECT g.*, b.batch_code, c.courseName
    FROM internal_grades g
    JOIN batches b ON g.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN course_assignments ca ON b.batch_id = ca.batch_id
    JOIN course_enrollments ce ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
    WHERE g.student_id = ? AND ca.teacher_id = ? AND ce.status = 'active'
    ORDER BY b.batch_code
");
$marksQuery->bind_param("ii", $student_id, $teacher_id);
$marksQuery->execute();
$marksResult = $marksQuery->get_result();
$marksData = [];
$totalScore = 0;
$testCount = 0;
while ($mark = $marksResult->fetch_assoc()) {
    $scores = array_filter([
        $mark['test_1'], $mark['test_2'], $mark['test_3'], $mark['test_4'],
        $mark['test_5'], $mark['test_6'], $mark['test_7'], $mark['end_examination']
    ], fn($s) => $s !== null && $s !== '');
    $avg = !empty($scores) ? array_sum($scores) / count($scores) : 0;
    $mark['average'] = round($avg, 2);
    $marksData[] = $mark;
    $totalScore += $avg;
    $testCount += count($scores);
}
$overallAverage = $testCount > 0 ? round($totalScore / $testCount, 2) : 0;
$marksQuery->close();

// Fetch attendance - for teacher's batches
$attendanceQuery = $conn->prepare("
    SELECT att.*, b.batch_code
    FROM attendance att
    JOIN batches b ON att.batch_id = b.batch_id
    JOIN course_assignments ca ON b.batch_id = ca.batch_id
    WHERE att.student_id = ? AND ca.teacher_id = ? 
    ORDER BY att.session_id DESC
");
$attendanceQuery->bind_param("ii", $student_id, $teacher_id);
$attendanceQuery->execute();
$attendanceResult = $attendanceQuery->get_result();
$attendanceData = [];
$totalSessions = 0;
$presentCount = 0;
while ($att = $attendanceResult->fetch_assoc()) {
    $attendanceData[] = $att;
    $totalSessions++;
    if (in_array($att['status'], ['Present', 'Late'])) { // Assuming Late counts as present
        $presentCount++;
    }
}
$attendancePercentage = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 2) : 0;
$attendanceQuery->close();

// Fetch enrollments for performance overview
$enrollmentsQuery = $conn->prepare("
    SELECT ce.enrollment_id, ce.status as enrollment_status, b.batch_code, c.courseName, b.start_date, b.end_date
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN course_assignments ca ON b.batch_id = ca.batch_id
    WHERE ce.student_id = ? AND ca.teacher_id = ? AND ce.status = 'active'
");
$enrollmentsQuery->bind_param("ii", $student_id, $teacher_id);
$enrollmentsQuery->execute();
$enrollmentsResult = $enrollmentsQuery->get_result();
$enrollments = [];
while ($enroll = $enrollmentsResult->fetch_assoc()) {
    $enrollments[] = $enroll;
}
$enrollmentsQuery->close();

// Behaviour: For now, derive from attendance and grades (e.g., good if high attendance and marks)
$behaviour = ($attendancePercentage > 80 && $overallAverage > 70) ? 'Excellent' : (($attendancePercentage > 60 || $overallAverage > 50) ? 'Good' : 'Needs Improvement');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Girls Coding Academy</title>
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
        .student-img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; }
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
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center mb-8">
                <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
                <h2 class="text-white text-xl font-bold">GCA Portal</h2>
            </div>
            <nav>
                <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-home mr-3"></i> Dashboard
                </a>
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i> Manage Courses
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i> Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-clipboard-check mr-3"></i> Grade
                </a>
                <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
                </a>
                <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-envelope mr-3"></i> Message Students
                </a>
                <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-user mr-3"></i> Profile
                </a>
                <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
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
                <h1 class="text-xl font-semibold">Student Profile: <?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></h1>
                <p class="text-sm">Welcome back, <?= htmlspecialchars($teacherInfo['username']) ?>!</p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Student Basic Info -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-1 card bg-white rounded-lg shadow-lg p-6 text-center">
                    <img src="<?= htmlspecialchars($student['photo'] ?: 'default-student.jpg') ?>" alt="<?= htmlspecialchars($student['firstName']) ?>" class="student-img mx-auto mb-4">
                    <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></h2>
                    <p class="text-gray-600">ID: <?= htmlspecialchars($student['IDNumber']) ?></p>
                    <p class="text-gray-600">DOB: <?= htmlspecialchars($student['dob']) ?></p>
                    <p class="text-gray-600">Gender: <?= htmlspecialchars($student['gender']) ?></p>
                    <p class="text-gray-600">Email: <?= htmlspecialchars($student['email']) ?></p>
                    <p class="text-gray-600">Phone: <?= htmlspecialchars($student['phone']) ?></p>
                    <p class="text-gray-600">Address: <?= htmlspecialchars(($student['address1'] ?? '') . ' ' . ($student['streetName'] ?? '') . ', ' . ($student['district'] ?? '') . ', ' . ($student['country'] ?? '')) ?></p>
                </div>

                <!-- Performance Overview -->
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="card bg-white rounded-lg shadow-lg p-6 text-center">
                        <i class="fas fa-chart-line text-3xl text-blue-600 mb-2"></i>
                        <h3 class="text-lg font-semibold text-gray-700">Overall Average</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $overallAverage ?>%</p>
                    </div>
                    <div class="card bg-white rounded-lg shadow-lg p-6 text-center">
                        <i class="fas fa-calendar-check text-3xl text-green-600 mb-2"></i>
                        <h3 class="text-lg font-semibold text-gray-700">Attendance</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $attendancePercentage ?>%</p>
                    </div>
                    <div class="card bg-white rounded-lg shadow-lg p-6 text-center">
                        <i class="fas fa-smile text-3xl text-yellow-600 mb-2"></i>
                        <h3 class="text-lg font-semibold text-gray-700">Behaviour</h3>
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= $behaviour === 'Excellent' ? 'bg-green-100 text-green-800' : ($behaviour === 'Good' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= $behaviour ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Parents -->
            <div class="card bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-users text-purple-600 mr-2"></i> Parents/Guardians
                </h2>
                <?php if (empty($parents)): ?>
                    <p class="text-gray-600">No parents/guardians linked.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($parents as $parent): ?>
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold"><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h3>
                            <p class="text-sm text-gray-600">Relationship: <?= htmlspecialchars($parent['relationship']) ?></p>
                            <p class="text-sm text-gray-600">Email: <?= htmlspecialchars($parent['email']) ?></p>
                            <p class="text-sm text-gray-600">Phone: <?= htmlspecialchars($parent['phone']) ?></p>
                            <?php if ($parent['photo'] && $parent['photo'] !== 'NULL'): ?>
                                <img src="<?= htmlspecialchars($parent['photo']) ?>" alt="<?= htmlspecialchars($parent['firstName']) ?>" class="w-16 h-16 object-cover rounded-full mt-2">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Health Information -->
            <div class="card bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-heartbeat text-red-600 mr-2"></i> Health Information
                </h2>
                <?php if (!$health): ?>
                    <p class="text-gray-600">No health information available.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><strong>Blood Type:</strong> <?= htmlspecialchars($health['blood_type'] ?? 'N/A') ?></div>
                        <div><strong>Allergies:</strong> <?= htmlspecialchars($health['allergies'] ?? 'None') ?></div>
                        <div><strong>Chronic Conditions:</strong> <?= htmlspecialchars($health['chronic_conditions'] ?? 'None') ?></div>
                        <div><strong>Medications:</strong> <?= htmlspecialchars($health['medications'] ?? 'None') ?></div>
                        <div><strong>Emergency Contact:</strong> <?= htmlspecialchars($health['emergency_contact_name'] ?? 'N/A') ?> (<?= htmlspecialchars($health['emergency_contact_phone'] ?? 'N/A') ?>)</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Enrollments/Performance Overview -->
            <div class="card bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-book-open text-green-600 mr-2"></i> Enrollments & Performance
                </h2>
                <?php if (empty($enrollments)): ?>
                    <p class="text-gray-600">No active enrollments in your batches.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left">Batch</th>
                                    <th class="px-4 py-2 text-left">Course</th>
                                    <th class="px-4 py-2 text-left">Start Date</th>
                                    <th class="px-4 py-2 text-left">End Date</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enroll): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($enroll['batch_code']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($enroll['courseName']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($enroll['start_date']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($enroll['end_date']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $enroll['enrollment_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= htmlspecialchars($enroll['enrollment_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Marks -->
            <div class="card bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clipboard-list text-blue-600 mr-2"></i> Marks
                </h2>
                <?php if (empty($marksData)): ?>
                    <p class="text-gray-600">No marks recorded yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left">Batch</th>
                                    <th class="px-4 py-2 text-left">Course</th>
                                    <th class="px-4 py-2 text-left">Test 1</th>
                                    <th class="px-4 py-2 text-left">Test 2</th>
                                    <th class="px-4 py-2 text-left">Test 3</th>
                                    <th class="px-4 py-2 text-left">Test 4</th>
                                    <th class="px-4 py-2 text-left">Test 5</th>
                                    <th class="px-4 py-2 text-left">Test 6</th>
                                    <th class="px-4 py-2 text-left">Test 7</th>
                                    <th class="px-4 py-2 text-left">End Exam</th>
                                    <th class="px-4 py-2 text-left">Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marksData as $mark): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($mark['batch_code']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($mark['courseName']) ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_1'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_2'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_3'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_4'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_5'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_6'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['test_7'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2"><?= $mark['end_examination'] ?? 'N/A' ?></td>
                                    <td class="px-4 py-2 font-semibold"><?= $mark['average'] ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attendance -->
            <div class="card bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calendar-alt text-orange-600 mr-2"></i> Attendance Records
                </h2>
                <?php if (empty($attendanceData)): ?>
                    <p class="text-gray-600">No attendance records.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Batch</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceData as $att): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($att['session_id']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($att['batch_code']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $att['status'] === 'Present' ? 'bg-green-100 text-green-800' : ($att['status'] === 'Late' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= htmlspecialchars($att['status']) ?>
                                        </span>
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