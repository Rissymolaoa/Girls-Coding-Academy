<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) die("Invalid student");

// Fetch student personal info
$info_sql = "
    SELECT u.firstName, u.lastName, u.email, u.phone,
           a.address1, a.streetName, a.district, a.country,
           m.blood_type, m.allergies, m.chronic_conditions, m.medications,
           m.emergency_contact_name, m.emergency_contact_phone,
           t.transport_mode, t.pick_up_point, t.drop_off_point, t.guardian_contact
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN student_medical_info m ON m.student_id = s.student_id
    LEFT JOIN student_transport_info t ON t.student_id = s.student_id
    WHERE s.student_id = ?
";
$stmt = $conn->prepare($info_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc() ?: die("Student not found");

// Fetch all enrollments + grades
$enroll_sql = "
    SELECT c.courseName, b.batch_code, b.start_date, b.end_date,
           ig.test_1, ig.test_2, ig.test_3, ig.test_4, ig.test_5, ig.test_6, ig.test_7, ig.end_examination
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN internal_grades ig ON ig.student_id = ce.student_id AND ig.batch_id = ce.batch_id
    WHERE ce.student_id = ? AND ce.status = 'active'
    ORDER BY b.start_date DESC
";
$stmt = $conn->prepare($enroll_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$enrollments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcript - <?= htmlspecialchars($info['firstName'] . ' ' . $info['lastName']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .border-primary { border-color: var(--primary); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8">
    <div class="max-w-6xl mx-auto">

        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-8">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800">
                        <?= htmlspecialchars($info['firstName'] . ' ' . $info['lastName']) ?>
                    </h1>
                    <p class="text-xl text-gray-600 mt-2">Student ID: <span class="font-mono font-bold">#<?= $student_id ?></span></p>
                </div>
                <div class="text-right">
                    <p class="text-gray-600"><strong>Email:</strong> <?= htmlspecialchars($info['email'] ?? '—') ?></p>
                    <p class="text-gray-600"><strong>Phone:</strong> <?= htmlspecialchars($info['phone'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-100 text-sm">Total Courses</p>
                        <p class="text-3xl font-bold mt-1"><?= count($enrollments) ?></p>
                    </div>
                    <i class="fas fa-book-open text-4xl opacity-50"></i>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm">Health Status</p>
                        <p class="text-2xl font-bold mt-1"><?= $info['blood_type'] ? "Active" : "Not Set" ?></p>
                    </div>
                    <i class="fas fa-heartbeat text-4xl opacity-50"></i>
                </div>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm">Transport</p>
                        <p class="text-2xl font-bold mt-1"><?= $info['transport_mode'] ?: "Self" ?></p>
                    </div>
                    <i class="fas fa-bus text-4xl opacity-50"></i>
                </div>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm">Location</p>
                        <p class="text-xl font-bold mt-1"><?= htmlspecialchars($info['district'] ?? '—') ?></p>
                    </div>
                    <i class="fas fa-map-marker-alt text-4xl opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Health & Transport Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- Health Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-notes-medical text-primary mr-3"></i> Medical Information
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Blood Type:</span> <span class="font-medium"><?= $info['blood_type'] ?? 'Not specified' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Allergies:</span> <span class="font-medium"><?= $info['allergies'] ?? 'None' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Chronic Conditions:</span> <span class="font-medium"><?= $info['chronic_conditions'] ?? 'None' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Medications:</span> <span class="font-medium"><?= $info['medications'] ?? 'None' ?></span></div>
                    <div class="border-t pt-3 mt-3">
                        <p class="text-sm text-gray-600">Emergency Contact:</p>
                        <p class="font-medium"><?= $info['emergency_contact_name'] ?? '—' ?> (<?= $info['emergency_contact_phone'] ?? '—' ?>)</p>
                    </div>
                </div>
            </div>

            <!-- Transport Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-shuttle-van text-primary mr-3"></i> Transport Information
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Mode:</span> <span class="font-medium"><?= $info['transport_mode'] ?? 'Self-arranged' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Pick-up Point:</span> <span class="font-medium"><?= $info['pick_up_point'] ?? '—' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Drop-off Point:</span> <span class="font-medium"><?= $info['drop_off_point'] ?? '—' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Guardian Contact:</span> <span class="font-medium"><?= $info['guardian_contact'] ?? '—' ?></span></div>
                </div>
            </div>
        </div>

        <!-- Academic Transcript -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6">
                <h2 class="text-2xl font-bold text-center">Academic Transcript</h2>
            </div>
            <div class="p-8">
                <?php if (empty($enrollments)): ?>
                    <p class="text-center text-gray-500 py-10">No course enrollments found.</p>
                <?php else: ?>
                    <div class="space-y-10">
                        <?php foreach ($enrollments as $e): 
                            $scores = array_filter([$e['test_1'],$e['test_2'],$e['test_3'],$e['test_4'],$e['test_5'],$e['test_6'],$e['test_7'],$e['end_examination']]);
                            $avg = count($scores) ? round(array_sum($scores)/count($scores), 1) : 0;
                            $grade = $avg >= 80 ? 'A' : ($avg >= 70 ? 'B' : ($avg >= 60 ? 'C' : ($avg >= 50 ? 'D' : 'F')));
                        ?>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($e['courseName']) ?></h3>
                                    <p class="text-sm text-gray-600">Batch: <?= htmlspecialchars($e['batch_code']) ?> • <?= date('M Y', strtotime($e['start_date'])) ?> - <?= date('M Y', strtotime($e['end_date'])) ?></p>
                                </div>
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Assessment</th>
                                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Score (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $tests = ['Test 1','Test 2','Test 3','Test 4','Test 5','Test 6','Test 7','Final Examination'];
                                        foreach ($tests as $i => $name):
                                            $field = $i < 7 ? "test_" . ($i + 1) : "end_examination";
                                            $score = $e[$field];
                                        ?>
                                        <tr class="border-t border-gray-200">
                                            <td class="px-6 py-4"><?= $name ?></td>
                                            <td class="px-6 py-4 text-center font-bold <?= $score >= 70 ? 'text-green-600' : ($score >= 50 ? 'text-orange-600' : 'text-red-600') ?>">
                                                <?= $score !== null ? $score : '—' ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="bg-gradient-to-r from-indigo-50 to-indigo-100 font-bold">
                                            <td class="px-6 py-5 text-right">Final Grade</td>
                                            <td class="px-6 py-5 text-center text-2xl <?= $grade === 'A' || $grade === 'B' ? 'text-green-600' : 'text-orange-600' ?>">
                                                <?= $grade ?> (<?= $avg ?>%)
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-10 text-center">
            <a href="academics.php" class="inline-block px-10 py-4 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition shadow-md">
                Back to All Students
            </a>
        </div>
    </div>
</div>
</body>
</html>