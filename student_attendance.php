<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_GET['student_id'];
$parent_id = $_SESSION['user_id'];

// Check ownership
$sql = "SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $parent_id, $student_id);
$stmt->execute();
if(!$stmt->get_result()->num_rows) {
    die("Unauthorized access.");
}

// Fetch attendance
$att_sql = "SELECT a.*, c.course_name, b.batch_name, t.teacher_name
            FROM attendance a
            JOIN courses c ON a.course_id = c.course_id
            JOIN batches b ON a.batch_id = b.batch_id
            JOIN teachers t ON a.teacher_id = t.teacher_id
            WHERE a.student_id = ?";
$stmt2 = $conn->prepare($att_sql);
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$attendance = $stmt2->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Attendance Records</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Course</th>
                <th>Batch</th>
                <th>Teacher</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $attendance->fetch_assoc()): ?>
            <tr>
                <td><?= $row['session_id'] ?></td>
                <td><?= $row['course_name'] ?></td>
                <td><?= $row['batch_name'] ?></td>
                <td><?= $row['teacher_name'] ?></td>
                <td><?= $row['status'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
