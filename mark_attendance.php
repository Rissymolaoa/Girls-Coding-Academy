<?php
session_start();
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_user_id = $_SESSION['user_id'];
// Fetch teacher info including teacher_id
$teacher_query = $conn->prepare("
    SELECT t.teacher_id, u.username, u.email, u.gender, u.phone 
    FROM teachers t 
    INNER JOIN users u ON t.user_id = u.user_id 
    WHERE u.user_id = ?
");
$teacher_query->bind_param("i", $teacher_user_id);
$teacher_query->execute();
$teacherInfo = $teacher_query->get_result()->fetch_assoc();
$teacher_query->close();

$message = "";
$current_day = date('Y-m-d');

// Handle form submission for attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_week'])) {
    $batch_id = intval($_POST['batch_id']);
    $attendance = $_POST['attendance'] ?? [];
    $marked_by = $teacherInfo['teacher_id'];

    foreach ($attendance as $student_id => $days) {
        foreach ($days as $day => $status) {
            if ($day !== $current_day) continue;
            $stmt = $conn->prepare("
                INSERT INTO attendance (student_id, batch_id, session_id, status, marked_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)
            ");
            $stmt->bind_param("iissi", $student_id, $batch_id, $day, $status, $marked_by);
            $stmt->execute();
            $stmt->close();
        }
    }
    $message = "✅ Attendance for today saved successfully.";
}

// Fetch assigned batches with student counts
$batch_query = $conn->prepare("
    SELECT 
        b.batch_id, 
        b.batch_code, 
        c.courseName,
        c.title as courseCode,
        COUNT(DISTINCT ce.student_id) as student_count,
        b.status
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN course_enrollments ce ON b.batch_id = ce.batch_id AND ce.status = 'active'
    WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
    GROUP BY b.batch_id, b.batch_code, c.courseName, c.title, b.status
    ORDER BY b.batch_code
");
$batch_query->bind_param("i", $teacher_user_id);
$batch_query->execute();
$assigned_batches = $batch_query->get_result();
$batch_query->close();

// Fetch top 3 attendees across all teacher's batches
$top_attendees_query = $conn->prepare("
    SELECT 
        s.student_id,
        u.firstName,
        u.lastName,
        s.photo,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(a.attendance_id) as total_sessions,
        ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) / COUNT(a.attendance_id)) * 100, 2) as attendance_rate
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN attendance a ON s.student_id = a.student_id
    INNER JOIN course_assignments ca ON a.batch_id = ca.batch_id
    WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
    GROUP BY s.student_id, u.firstName, u.lastName, s.photo
    HAVING total_sessions > 0
    ORDER BY attendance_rate DESC, present_count DESC
    LIMIT 3
");
$top_attendees_query->bind_param("i", $teacher_user_id);
$top_attendees_query->execute();
$top_attendees = $top_attendees_query->get_result();
$top_attendees_query->close();

$selected_batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$students = [];
$week_days = [];

if ($selected_batch_id > 0) {
    // Get students
    $student_query = $conn->prepare("
        SELECT s.student_id, u.firstName, u.lastName, u.email, s.photo
        FROM course_enrollments ce
        INNER JOIN students s ON ce.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE ce.batch_id = ? AND ce.status='active'
        ORDER BY u.firstName, u.lastName
    ");
    $student_query->bind_param("i", $selected_batch_id);
    $student_query->execute();
    $students_result = $student_query->get_result();
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    $student_query->close();

    // Generate week dates (Monday to Sunday)
    $week_start = date('Y-m-d', strtotime('monday this week'));
    for ($i=0; $i<7; $i++) {
        $day = date('Y-m-d', strtotime("$week_start +$i days"));
        $week_days[] = $day;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Mark Attendance - Girls Coding Academy</title>
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
.student-img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 50%;
}
.top-attendee-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
}
.status-select {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
}
.status-select:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
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
.trophy-gold { color: #ffd700; }
.trophy-silver { color: #c0c0c0; }
.trophy-bronze { color: #cd7f32; }
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
                <a href="manage_own_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
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
                <a href="mark_attendance.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
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
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Attendance Management</h2>
                <p class="text-gray-600">Select a batch to mark attendance for today (<?= date('l, F j, Y') ?>)</p>
            </div>

            <!-- Top 3 Attendees -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top Attendees
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php 
                    $medals = ['trophy-gold', 'trophy-silver', 'trophy-bronze'];
                    $positions = ['1st', '2nd', '3rd'];
                    $index = 0;
                    while ($attendee = $top_attendees->fetch_assoc()): 
                    ?>
                        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                            <div class="relative inline-block mb-4">
                                <img src="<?= htmlspecialchars($attendee['photo'] ?: 'default-student.jpg') ?>" 
                                     alt="<?= htmlspecialchars($attendee['firstName'] . ' ' . $attendee['lastName']) ?>" 
                                     class="top-attendee-img mx-auto border-4 border-purple-200">
                                <div class="absolute -top-2 -right-2 bg-white rounded-full p-2 shadow-lg">
                                    <i class="fas fa-trophy <?= $medals[$index] ?> text-2xl"></i>
                                </div>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-800 mb-1">
                                <?= htmlspecialchars($attendee['firstName'] . ' ' . $attendee['lastName']) ?>
                            </h4>
                            <p class="text-sm text-gray-600 mb-2"><?= $positions[$index] ?> Place</p>
                            <div class="bg-purple-100 rounded-lg p-3">
                                <p class="text-2xl font-bold text-purple-600 mb-1"><?= $attendee['attendance_rate'] ?>%</p>
                                <p class="text-xs text-gray-600"><?= $attendee['present_count'] ?> of <?= $attendee['total_sessions'] ?> sessions</p>
                            </div>
                        </div>
                    <?php 
                    $index++;
                    endwhile; 
                    ?>
                </div>
            </div>

            <!-- Batch Selection Cards -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-folder-open mr-2 text-purple-600"></i>Select Batch
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    $assigned_batches->data_seek(0);
                    while ($batch = $assigned_batches->fetch_assoc()): 
                    ?>
                        <a href="?batch_id=<?= $batch['batch_id'] ?>" 
                           class="batch-card bg-white rounded-lg shadow-lg p-6 no-underline <?= $batch['batch_id'] == $selected_batch_id ? 'selected' : '' ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">
                                        <?= htmlspecialchars($batch['courseCode']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($batch['courseName']) ?></p>
                                </div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?= $batch['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= htmlspecialchars($batch['status']) ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-users mr-2 text-purple-600"></i>
                                    <span class="text-sm"><?= $batch['student_count'] ?> students</span>
                                </div>
                                <div class="text-purple-600">
                                    <span class="text-xs font-medium"><?= htmlspecialchars($batch['batch_code']) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Attendance Form -->
            <?php if ($selected_batch_id > 0 && !empty($students)): ?>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <form method="POST" action="">
                        <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                        
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-calendar-week mr-2 text-purple-600"></i>Weekly Attendance
                            </h3>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                You can only mark attendance for today
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Student
                                        </th>
                                        <?php foreach ($week_days as $day): ?>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider <?= $day === $current_day ? 'bg-purple-50' : '' ?>">
                                                <?= date('D', strtotime($day)) ?><br>
                                                <span class="text-xs font-normal"><?= date('M j', strtotime($day)) ?></span>
                                                <?php if ($day === $current_day): ?>
                                                    <br><span class="inline-block px-2 py-1 bg-purple-600 text-white rounded text-xs mt-1">Today</span>
                                                <?php endif; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($students as $student): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <img src="<?= htmlspecialchars($student['photo'] ?: 'default-student.jpg') ?>" 
                                                         alt="<?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?>" 
                                                         class="student-img mr-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($student['firstName'].' '.$student['lastName']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?= htmlspecialchars($student['email']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php foreach ($week_days as $day): ?>
                                                <?php
                                                    $is_today = ($day === $current_day);
                                                    $selected_value = $_POST['attendance'][$student['student_id']][$day] ?? 'Present';
                                                ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-center <?= $day === $current_day ? 'bg-purple-50' : '' ?>">
                                                    <select name="attendance[<?= $student['student_id'] ?>][<?= $day ?>]" 
                                                            class="status-select <?= $is_today ? '' : 'text-gray-400' ?>" 
                                                            <?= $is_today ? '' : 'disabled' ?>>
                                                        <option value="Present" <?= ($selected_value=='Present')?'selected':'' ?>>✓ Present</option>
                                                        <option value="Absent" <?= ($selected_value=='Absent')?'selected':'' ?>>✗ Absent</option>
                                                        <option value="Late" <?= ($selected_value=='Late')?'selected':'' ?>>⏰ Late</option>
                                                        <option value="Sick" <?= ($selected_value=='Sick')?'selected':'' ?>>🏥 Sick</option>
                                                    </select>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="mark_week" class="px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Today's Attendance
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif ($selected_batch_id > 0): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
                    <p>No active students enrolled in this batch.</p>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
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
    </script>
</body>
</html>