<?php
session_start();
include 'db.php'; // DB connection

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_user_id = $_SESSION['user_id'];

try {
    // Fetch teacher's ID from teachers table
    $teacher_query = "
        SELECT teacher_id
        FROM teachers
        WHERE user_id = ?
    ";
    $teacher_stmt = $conn->prepare($teacher_query);
    $teacher_stmt->execute([$teacher_user_id]);
    $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        die("Error: Teacher not found.");
    }
    $teacher_id = $teacher['teacher_id'];

    // Fetch assigned courses (batches) for the teacher
    $course_query = "
        SELECT DISTINCT ca.batch_id, ca.batch_code, ca.course_name, ca.start_date, ca.end_date, ca.status
        FROM course_assignments ca
        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
        WHERE t.user_id = ?
        ORDER BY ca.start_date
    ";
    $course_stmt = $conn->prepare($course_query);
    $course_stmt->execute([$teacher_user_id]);
    $courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch enrolled students for each batch
    $student_query = "
        SELECT ce.enrollment_id, ce.batch_id, ce.status, u.user_id, u.username, u.email, u.firstName, u.lastName
        FROM course_enrollments ce
        INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
        INNER JOIN students s ON ce.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
        WHERE t.user_id = ? AND ce.status = 'active'
        ORDER BY ce.batch_id, u.username
    ";
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->execute([$teacher_user_id]);
    $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group students by batch_id
    $students_by_batch = [];
    foreach ($students as $student) {
        $students_by_batch[$student['batch_id']][] = $student;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Own Courses</title>
    <link rel="stylesheet" href="teacher_dashboard.css">
    <style>
        .course-card {
            background: #fff;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .course-card h3 {
            margin: 0 0 10px;
            font-size: 1.2em;
        }
        .course-card p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .course-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .course-actions a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .course-actions a:hover {
            text-decoration: underline;
        }
        .student-list {
            margin-top: 10px;
            padding-left: 20px;
        }
        .student-list li {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .no-data {
            color: #555;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Teacher Dashboard</h2>
        <ul class="nav">
            <li><a href="teacher_dashboard.php">🏠 Dashboard</a></li>
            <li><a href="manage_courses.php" class="active">📚 Manage Own Courses</a></li>
            <li><a href="upload_materials.php">📂 Upload Materials</a></li>
            <li><a href="grade.php">📝 Grade</a></li>
            <li><a href="mark_attendance.php">✅ Mark Attendance</a></li>
            <li><a href="message_students.php">💬 Message Students</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="header">
            <h1>Manage Own Courses</h1>
        </div>

        <div class="card">
            <?php if (count($courses) > 0): ?>
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <h3><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['batch_code']) ?>)</h3>
                        <p><strong>Batch ID:</strong> <?= htmlspecialchars($course['batch_id']) ?></p>
                        <p><strong>Start Date:</strong> <?= htmlspecialchars($course['start_date']) ?></p>
                        <p><strong>End Date:</strong> <?= htmlspecialchars($course['end_date']) ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($course['status']) ?></p>
                        <p><strong>Enrolled Students:</strong></p>
                        <?php if (isset($students_by_batch[$course['batch_id']]) && count($students_by_batch[$course['batch_id']]) > 0): ?>
                            <ul class="student-list">
                                <?php foreach ($students_by_batch[$course['batch_id']] as $student): ?>
                                    <li>
                                        <?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?> 
                                        (<?= htmlspecialchars($student['username']) ?>, <?= htmlspecialchars($student['email']) ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-data">No students enrolled in this batch.</p>
                        <?php endif; ?>
                        <div class="course-actions">
                            <a href="view_course.php?batch_id=<?= urlencode($course['batch_id']) ?>">View Details</a>
                            <a href="edit_course.php?batch_id=<?= urlencode($course['batch_id']) ?>">Edit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">No courses assigned.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?= date('Y') ?> School Management System</p>
    </div>
</body>
</html>