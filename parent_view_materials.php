<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Step 1: Get parent_id from parents table
$user_id = $_SESSION['user_id'];
$parent_sql = "SELECT parent_id FROM parents WHERE user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();
$parent_id = $parent['parent_id'] ?? 0;

// Step 2: Get children linked to this parent_id
$children_sql = "
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = ?
";
$stmt = $conn->prepare($children_sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_result = $stmt->get_result();
$children = $children_result->fetch_all(MYSQLI_ASSOC);

// Step 3: Pick selected child (or default first)
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($children[0]['student_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parent View - Materials</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { display:flex; min-height:100vh; }
    .sidebar { width:250px; background:#343a40; color:white; padding:20px; }
    .sidebar a { display:block; color:white; padding:10px; text-decoration:none; border-radius:5px; }
    .sidebar a:hover, .sidebar a.active { background:#495057; }
    .sidebar img { width:90px; border-radius:50%; margin:0 auto 15px; display:block; border:2px solid #6c757d; }
    .main-content { flex:1; padding:20px; background:#f8f9fa; }
    .card { border:2px solid #dee2e6; border-radius:12px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="admin.png" alt="Parent Picture">
    <h3 class="text-center">Parent Panel</h3>
    <a href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php"><i class="bi bi-people"></i> My Children</a>
    <a href="parent_view_attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="parent_view_performance.php"><i class="bi bi-graph-up"></i> Performance</a>
    <a href="parent_view_materials.php" class="active"><i class="bi bi-folder"></i> Materials</a>
    <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h2><i class="bi bi-folder"></i> View Materials</h2>

    <!-- Child Selector -->
    <?php if (count($children) > 0): ?>
    <form method="get" class="mb-3">
        <div class="input-group" style="max-width:400px;">
            <label class="input-group-text" for="student_id">Select Child</label>
            <select class="form-select" id="student_id" name="student_id" onchange="this.form.submit()">
                <?php foreach ($children as $c): ?>
                    <option value="<?= $c['student_id'] ?>" <?= $c['student_id']==$student_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['firstName'].' '.$c['lastName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-warning">No children linked to your account.</div>
    <?php endif; ?>

    <?php if ($student_id): ?>
        <?php
        // Fetch materials for student's enrolled courses
        $sql = "
            SELECT m.*, c.courseName, b.batch_code, u.firstName AS teacherFirst, u.lastName AS teacherLast
            FROM materials m
            JOIN batches b ON m.batch_id = b.batch_id
            JOIN courses c ON b.course_id = c.course_id
            JOIN course_enrollments e ON e.batch_id = b.batch_id
            JOIN teachers t ON m.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            WHERE e.student_id = ?
            ORDER BY m.uploaded_at DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $materials_result = $stmt->get_result();
        ?>

        <div class="row">
            <?php if ($materials_result->num_rows > 0): ?>
                <?php while ($m = $materials_result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-file-earmark-text"></i>
                                <?= htmlspecialchars($m['title']) ?>
                            </h5>
                            <p class="card-text small text-muted">
                                <?= htmlspecialchars($m['courseName'].' - '.$m['batch_code']) ?><br>
                                Teacher: <?= htmlspecialchars($m['teacherFirst'].' '.$m['teacherLast']) ?><br>
                                Date: <?= date("M d, Y", strtotime($m['uploaded_at'])) ?>
                            </p>
                            <p><?= nl2br(htmlspecialchars($m['description'])) ?></p>
                            <?php if (!empty($m['file_path'])): ?>
                                <a href="<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn btn-primary btn-sm">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No materials found for this child.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
