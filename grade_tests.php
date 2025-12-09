<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$teacher_id = $stmt->get_result()->fetch_assoc()['teacher_id'] ?? 0;
$stmt->close();

if ($teacher_id === 0) {
    die("Teacher profile not found.");
}

$success = $error = "";

// Handle grading - saves to internal_grades.test_1 (no updated_at column)
if (isset($_POST['action']) && $_POST['action'] === 'grade') {
    $submission_id = (int)$_POST['submission_id'];
    $score = (float)$_POST['score'];

    // Get student_id and batch_id
    $stmt = $conn->prepare("
        SELECT ts.student_id, t.batch_id 
        FROM test_submissions ts
        JOIN tests t ON ts.test_id = t.test_id
        WHERE ts.submission_id = ? AND t.teacher_id = ?
    ");
    $stmt->bind_param("ii", $submission_id, $teacher_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($info) {
        $student_id = $info['student_id'];
        $batch_id = $info['batch_id'];

        // Save grade - NO updated_at column
        $stmt = $conn->prepare("
            INSERT INTO internal_grades (student_id, batch_id, test_1)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE test_1 = VALUES(test_1)
        ");
        $stmt->bind_param("iid", $student_id, $batch_id, $score);
        $success = $stmt->execute() ? "Grade saved successfully!" : "Error saving grade.";
        $stmt->close();
    } else {
        $error = "Invalid submission.";
    }
}

// Fetch all tests by this teacher
$tests = $conn->query("
    SELECT t.test_id, t.title, t.max_score, t.due_date,
           b.batch_code, c.courseName,
           COUNT(ts.submission_id) as total_submissions
    FROM tests t
    JOIN batches b ON t.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN test_submissions ts ON t.test_id = ts.test_id
    WHERE t.teacher_id = $teacher_id
    GROUP BY t.test_id
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$selected_test_id = $_GET['test_id'] ?? ($tests[0]['test_id'] ?? 0);

// Fetch submissions for selected test
$submissions = [];
$test_info = null;
if ($selected_test_id) {
    $stmt = $conn->prepare("SELECT title, max_score, batch_id FROM tests WHERE test_id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $selected_test_id, $teacher_id);
    $stmt->execute();
    $test_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($test_info) {
        $batch_id = $test_info['batch_id'];
        $submissions = $conn->query("
            SELECT 
                ts.submission_id,
                ts.submission_text,
                ts.submission_file,
                ts.submitted_at,
                u.firstName,
                u.lastName,
                u.email,
                ig.test_1 AS score
            FROM test_submissions ts
            JOIN students s ON ts.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            LEFT JOIN internal_grades ig ON ig.student_id = ts.student_id AND ig.batch_id = $batch_id
            WHERE ts.test_id = $selected_test_id
            ORDER BY u.lastName, u.firstName
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Tests • Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Inter',sans-serif;background:#f8fafc}
        .gradient-header {background: linear-gradient(90deg, #7b2cbf, #5a189a);}
        .sidebar {
            width:250px;
            background: linear-gradient(180deg, #7b2cbf, #5a189a);
            position:fixed;height:100vh;overflow-y:auto;
            z-index:1000;
        }
        .sidebar-link {transition:all 0.3s;padding:0.75rem 1.5rem;color:white;}
        .sidebar-link:hover {background:rgba(255,255,255,0.1);padding-left:2rem;}
        .sidebar-link.active {background:rgba(255,255,255,0.25);border-left:4px solid white;font-weight:600;}
        .main-content {margin-left:250px;padding-top:80px;}
        .card:hover {transform:translateY(-6px);box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);}
        @media (max-width:768px) {
            .sidebar {transform:translateX(-100%);}
            .sidebar.mobile-open {transform:translateX(0);}
            .main-content {margin-left:0;}
            .mobile-toggle {display:block;}
        }
        .mobile-toggle {display:none;}
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="p-6">
        <div class="flex items-center mb-8">
            <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
            <h2 class="text-white text-xl font-bold">GCA Portal</h2>
        </div>
        <nav>
            <a href="teacher_dashboard.php" class="sidebar-link flex items-center">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="manage_teacher_courses.php" class="sidebar-link flex items-center">
                <i class="fas fa-chalkboard-teacher mr-3"></i> My Courses
            </a>
            <a href="schedule_class.php" class="sidebar-link flex items-center">
                <i class="fas fa-calendar-plus mr-3"></i> Schedule Class
            </a>
            <a href="upload_materials.php" class="sidebar-link flex items-center">
                <i class="fas fa-book mr-3"></i> Upload Materials
            </a>
            <a href="grade_tests.php" class="sidebar-link active flex items-center">
                <i class="fas fa-clipboard-check mr-3"></i> Grade Tests
            </a>
            <a href="mark_attendance.php" class="sidebar-link flex items-center">
                <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
            </a>
            <a href="teacher_profile.php" class="sidebar-link flex items-center">
                <i class="fas fa-user mr-3"></i> My Profile
            </a>
            <a href="logout.php" class="sidebar-link flex items-center">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
        </nav>
    </div>
</aside>

<!-- HEADER -->
<header class="gradient-header text-white py-4 px-6 flex justify-between items-center fixed top-0 left-0 right-0 z-40">
    <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="text-right">
        <h1 class="text-xl font-bold">Grade Tests</h1>
        <p class="text-sm opacity-90">Review and grade student submissions</p>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <main class="p-6 max-w-7xl mx-auto">

        <h2 class="text-4xl font-bold text-gray-800 mb-8">Grade Tests</h2>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                Success: <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                Error: <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (empty($tests)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-16 text-center">
                <i class="fas fa-file-alt text-8xl text-gray-200 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-700 mb-4">No Tests Found</h3>
                <p class="text-gray-600">You haven't created any tests yet.</p>
            </div>
        <?php else: ?>

            <!-- Test Cards -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Select Test to Grade</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($tests as $test): ?>
                        <a href="?test_id=<?= $test['test_id'] ?>" 
                           class="block p-6 rounded-xl border-2 <?= $selected_test_id == $test['test_id'] ? 'border-purple-600 bg-purple-50' : 'border-gray-200 hover:border-purple-400' ?> transition">
                            <div class="flex justify-between items-start mb-4">
                                <h4 class="font-bold text-lg text-purple-700"><?= htmlspecialchars($test['title']) ?></h4>
                                <span class="text-xs bg-purple-100 text-purple-800 px-3 py-1 rounded-full">
                                    <?= $test['total_submissions'] ?> submitted
                                </span>
                            </div>
                            <p class="text-gray-700 font-medium">
                                <?= htmlspecialchars($test['batch_code']) ?> • <?= htmlspecialchars($test['courseName']) ?>
                            </p>
                            <div class="mt-4 text-sm">
                                <div>Due: <?= date('M d, Y', strtotime($test['due_date'])) ?></div>
                                <div>Max Score: <?= $test['max_score'] ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Submissions -->
            <?php if ($selected_test_id && $test_info): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-8">
                        <h3 class="text-3xl font-bold">Grading: <?= htmlspecialchars($test_info['title']) ?></h3>
                        <p class="text-lg mt-2">Maximum Score: <?= $test_info['max_score'] ?> points</p>
                    </div>
                    <div class="p-8">
                        <?php if (empty($submissions)): ?>
                            <div class="text-center py-16 text-gray-500">
                                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                <p class="text-xl">No submissions yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Student</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Submitted</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">File</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Score</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($submissions as $sub): ?>
                                            <tr class="hover:bg-purple-50">
                                                <td class="px-6 py-4">
                                                    <div class="font-medium"><?= htmlspecialchars($sub['firstName'] . ' ' . $sub['lastName']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= $sub['email'] ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-sm">
                                                    <?= $sub['submitted_at'] ? date('M d, Y h:i A', strtotime($sub['submitted_at'])) : '—' ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if($sub['submission_file']): ?>
                                                        <a href="<?= htmlspecialchars($sub['submission_file']) ?>" target="_blank" class="text-purple-600 hover:underline">View File</a>
                                                    <?php else: ?>
                                                        <span class="text-gray-500 italic">Text only</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if($sub['score'] !== null): ?>
                                                        <span class="text-green-600 font-bold text-xl"><?= $sub['score'] ?></span>
                                                        <span class="text-gray-500">/ <?= $test_info['max_score'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">Not graded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button onclick="openModal(<?= $sub['submission_id'] ?>, '<?= addslashes($sub['firstName'] . ' ' . $sub['lastName']) ?>', <?= $sub['score'] ?? 'null' ?>, <?= $test_info['max_score'] ?>)"
                                                            class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-bold py-2 px-5 rounded-lg transition">
                                                        Grade
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<!-- Grade Modal -->
<div id="gradeModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-8 rounded-t-3xl">
            <h3 class="text-3xl font-bold">Grade Submission</h3>
        </div>
        <form method="POST" class="p-8 space-y-8">
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" id="modal_id">

            <div class="text-center">
                <h4 id="modal_name" class="text-2xl font-bold text-gray-800"></h4>
                <p class="text-gray-600">Maximum: <span id="modal_max" class="font-bold text-purple-600"></span> points</p>
            </div>

            <div>
                <label class="block text-lg font-semibold text-gray-700 mb-3">Score *</label>
                <input type="number" name="score" id="modal_score" required min="0" step="0.01"
                       class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-100 text-lg">
            </div>

            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeModal()" class="px-8 py-4 bg-gray-200 text-gray-800 rounded-xl font-bold hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" class="px-10 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-bold hover:from-purple-700 hover:to-pink-700">
                    Save Grade
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}
function openModal(id, name, score, max) {
    document.getElementById('modal_id').value = id;
    document.getElementById('modal_name').textContent = name;
    document.getElementById('modal_max').textContent = max;
    document.getElementById('modal_score').value = score || '';
    document.getElementById('gradeModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('gradeModal').classList.add('hidden');
}
document.getElementById('gradeModal').addEventListener('click', e => {
    if (e.target === document.getElementById('gradeModal')) closeModal();
});
</script>
</body>
</html>