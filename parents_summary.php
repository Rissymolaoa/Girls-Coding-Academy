<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

// Get top 5 parents with their linked student name(s)
$parents_sql = "
    SELECT u.user_id, u.firstName, u.lastName, u.gender, u.phone, u.email, s.firstName AS studentName
    FROM users u
    JOIN parents p ON p.user_id = u.user_id
    LEFT JOIN parent_students ps ON ps.parent_id = u.user_id
    LEFT JOIN users s ON ps.student_id = s.user_id
    WHERE u.role='parent'
    ORDER BY u.created_at DESC
    LIMIT 5
";
$parents = $conn->query($parents_sql);

// Collect student images (for top 4 parents only)
$students_images = [];
$i = 0;
while ($row = $parents->fetch_assoc()) {
    if ($i < 4 && !empty($row['studentPhoto'])) {
        $students_images[] = $row['studentPhoto'];
    }
    $parents_data[] = $row;
    $i++;
}

// Summary counts
$total_parents = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='parent'")->fetch_assoc()['total'];
$total_relations = $conn->query("SELECT COUNT(*) AS total FROM parent_students")->fetch_assoc()['total'];
$total_students = $conn->query("SELECT COUNT(DISTINCT student_id) AS total FROM parent_students")->fetch_assoc()['total'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Parents</title>
<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
<style>
:root{--primary:#7b2cbf;--accent:#5a189a;--muted:#f4f4f8;--card:#fff;--text:#222;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--muted);color:var(--text);}
header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px;text-align:center;font-size:20px;font-weight:600;}
.layout{display:flex;min-height:calc(100vh - 60px);}
.sidebar{width:220px;background:#34495e;padding:20px;color:#fff;}
.sidebar h3{margin:12px 0;}
.nav a{display:block;padding:10px;color:#fff;text-decoration:none;border-radius:6px;margin-bottom:6px;}
.nav a.active,.nav a:hover{background:#1abc9c;}
.main{flex:1;padding:20px;}
.flex-row{display:flex;gap:20px;}
.table-card{flex:1;background:#fff;padding:15px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.image-card{flex:1;background:#fff;padding:15px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
table{width:100%;border-collapse:collapse;font-size:14px;}
th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left;}
.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px;}
th{background:var(--primary);color:#fff;}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
.grid img{width:100%;height:120px;object-fit:cover;border-radius:8px;}
.summary{margin-top:20px;display:flex;gap:20px;}
.card{flex:1;background:#fff;padding:15px;border-radius:8px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.card h3{margin-bottom:6px;color:var(--accent);}
</style>
</head>
<body>
<header>Girls Coding Academy - Admin Dashboard</header>
<div class="layout">
    <aside class="sidebar">
        <img src="admin.jpg" alt="Admin">
        <h3>GIRLS CODING ACADEMY</h3>
        <nav class="nav">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
      <a href="approve_users.php">📝 Approve Users</a>
      <a href="manage_courses.php">📚 Manage Courses</a>
      <a href="manage_students.php">👩‍🎓 Manage Students</a>
      <a href="manage_teachers.php">👨‍🏫 Manage Teacher</a>
      <a href="parents_summary.php" class="active">👪 Parent Summary</a>
      <a href="manage_parents.php">👪 Manage Parents</a>
      <a href="assign_parent_student.php">👨‍🏫 Assign Students</a>
      <a href="course_assignment.php">👨‍🏫 Assign Courses</a>
      <a href="add_batch.php">➕ Add Batch</a>
      <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>
    <main class="main">
        <h2>Manage Parents</h2>
        <div class="flex-row">
            <!-- Parents Table -->
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Student Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parents_data as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['firstName']) ?></td>
                            <td><?= htmlspecialchars($p['lastName']) ?></td>
                            <td><?= htmlspecialchars($p['gender']) ?></td>
                            <td><?= htmlspecialchars($p['phone']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars($p['studentName']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Students Images -->
            <div class="image-card">
                <h3>Students</h3>
                <div class="grid">
                    <?php foreach ($students_images as $img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="Student">
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!-- Summary Cards -->
        <div class="summary">
            <div class="card"><h3>Number of Parents</h3><p><?= $total_parents ?></p></div>
            <div class="card"><h3>Relations</h3><p><?= $total_relations ?></p></div>
            <div class="card"><h3>Total Students</h3><p><?= $total_students ?></p></div>
        </div>
    </main>
</div>
</body>
</html>
