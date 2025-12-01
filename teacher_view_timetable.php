<!-- teacher_view_timetable.php -->
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php'); exit();
}
include 'db.php';
$user_id = $_SESSION['user_id'];
$teacher_id = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc()['teacher_id'];

$timetable = [];
$res = $conn->query("SELECT tt.*, b.batch_code, c.courseName 
                     FROM teacher_timetables tt
                     JOIN batches b ON tt.batch_id = b.batch_id
                     JOIN courses c ON b.course_id = c.course_id
                     WHERE tt.created_by = $teacher_id
                     ORDER BY tt.day, tt.start_time");

while ($row = $res->fetch_assoc()) {
    $timetable[$row['day']][] = $row;
}
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable | GCA Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-header { background: linear-gradient(90deg, #7b2cbf, #5a189a); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #7b2cbf, #5a189a); position: fixed; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 250px; }
        /* Same sidebar as above */
    </style>
</head>
<body class="bg-gray-100">
    <!-- Same sidebar + header -->
    <div class="main-content p-10">
        <h1 class="text-4xl font-bold text-purple-800 mb-8 text-center">My Weekly Timetable</h1>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <?php foreach ($days as $d => $name): ?>
                <div class="bg-white rounded-2xl shadow-2xl p-8">
                    <h2 class="text-2xl font-bold text-purple-700 text-center mb-6"><?= $name ?></h2>
                    <?php if (!empty($timetable[$d])): ?>
                        <?php foreach ($timetable[$d] as $slot): ?>
                            <div class="bg-gradient-to-br from-purple-600 to-purple-800 text-white p-6 rounded-xl mb-6">
                                <div class="text-sm font-bold">Period <?= $slot['period'] ?></div>
                                <div class="text-xl font-bold mt-2"><?= htmlspecialchars($slot['subject']) ?></div>
                                <div class="text-sm mt-3 opacity-90">
                                    <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($slot['start_time'])) ?> – <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                </div>
                                <div class="text-sm mt-2"><strong>Batch:</strong> <?= $slot['batch_code'] ?></div>
                                <?php if ($slot['room']): ?>
                                    <div class="text-sm mt-2"><i class="fas fa-map-marker-alt"></i> <?= $slot['room'] ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-10">No classes</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>