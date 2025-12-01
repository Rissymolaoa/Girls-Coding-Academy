<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all students with course & batch
$sql = "
    SELECT DISTINCT 
        s.student_id, u.firstName, u.lastName, u.email,
        c.courseName, c.course_id, b.batch_code, b.batch_id
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN course_enrollments ce ON s.student_id = ce.student_id AND ce.status = 'active'
    LEFT JOIN batches b ON ce.batch_id = b.batch_id
    LEFT JOIN courses c ON b.course_id = c.course_id
    ORDER BY u.firstName
";

$result = $conn->query($sql);
$all_students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get unique courses & batches for filters
$courses = $conn->query("SELECT DISTINCT course_id, courseName FROM courses WHERE courseName IS NOT NULL ORDER BY courseName")->fetch_all(MYSQLI_ASSOC);
$batches = $conn->query("SELECT DISTINCT batch_id, batch_code FROM batches WHERE batch_code IS NOT NULL ORDER BY batch_code DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: #4338ca; }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-800">Student Records</h1>
            <p class="text-gray-600 mt-2">Search, filter, and view academic & payment details</p>
        </div>

        <!-- Search & Filters -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Student</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                           placeholder="Name or ID..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                    <select name="course" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['course_id'] ?>" <?= ($_GET['course'] ?? '') == $c['course_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['courseName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Batch</label>
                    <select name="batch" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Batches</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['batch_id'] ?>" <?= ($_GET['batch'] ?? '') == $b['batch_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['batch_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                </div>
            </div>
        </form>

        <!-- Apply Filters -->
        <?php
        $students = $all_students;
        if (!empty($_GET['search'])) {
            $search = strtolower($_GET['search']);
            $students = array_filter($students, function($s) use ($search) {
                return strpos(strtolower($s['firstName'] . ' ' . $s['lastName'] . ' ' . $s['student_id']), $search) !== false;
            });
        }
        if (!empty($_GET['course'])) {
            $course_id = (int)$_GET['course'];
            $students = array_filter($students, fn($s) => $s['course_id'] == $course_id);
        }
        if (!empty($_GET['batch'])) {
            $batch_id = (int)$_GET['batch'];
            $students = array_filter($students, fn($s) => $s['batch_id'] == $batch_id);
        }
        $students = array_values($students);
        ?>

        <!-- Student Cards -->
        <?php if (empty($students)): ?>
            <div class="text-center py-20 bg-white rounded-xl shadow-sm border border-gray-200">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500">No students found matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($students as $s): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-lg transition-shadow">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="font-semibold text-lg text-gray-800">
                                <?= htmlspecialchars($s['firstName'] . ' ' . $s['lastName']) ?>
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                <?= htmlspecialchars($s['courseName'] ?? 'Not Enrolled') ?>
                            </p>
                            <span class="inline-block mt-3 px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full">
                                <?= htmlspecialchars($s['batch_code'] ?? '—') ?>
                            </span>
                        </div>
                        <div class="p-4 bg-gray-50 flex gap-3">
                            <a href="view_transcript.php?student_id=<?= $s['student_id'] ?>"
                               class="flex-1 text-center py-3 bg-white border border-indigo-600 text-indigo-600 font-medium rounded-lg hover:bg-indigo-50 transition">
                                Transcript
                            </a>
                            <a href="view_payment.php?student_id=<?= $s['student_id'] ?>"
                               class="flex-1 text-center py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                                Payments
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>