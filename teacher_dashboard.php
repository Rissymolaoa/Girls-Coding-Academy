<?php
session_start();

// Only allow teachers
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher'){
    header("Location: login.html");
    exit();
}

include("db.php"); // your DB connection

$teacher_id = $_SESSION['user_id'];

// Get teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
$teacherQuery->bind_param("i",$teacher_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
$teacherQuery->close();

// Get courses assigned to teacher
$courseQuery = $conn->prepare("
    SELECT ca.assignment_id, b.batch_code, b.start_date, b.end_date, b.status, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = ?
    ORDER BY b.start_date DESC
");
$courseQuery->bind_param("i",$teacher_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
$courseQuery->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teacher Dashboard</title>
<style>
:root{
    --primary:#7b2cbf;
    --accent:#5a189a;
    --muted:#f4f4f8;
    --card:#ffffff;
    --text:#222;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--muted);color:var(--text);}
header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px 24px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12);}
header h1{margin:0;font-size:22px;}
header p{margin:4px 0 0;font-size:14px;}
.layout{display:flex;min-height:calc(100vh - 72px);}
.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff;}
.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px;}
.sidebar h3{font-size:14px;margin:0 0 12px;text-align:center;}
.nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left;}
.nav a.active, .nav a:hover{background:#1abc9c;color:#062018;}
.main{flex:1;padding:26px;}
.table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06);}
table{width:100%;border-collapse:collapse;font-size:14px;}
th,td{padding:10px;border-bottom:1px solid #732d91;text-align:left;}
th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;}
footer{background:#34495e;color:#fff;padding:12px;text-align:center;margin-top:auto;}
.status-active{color:green;font-weight:bold;}
.status-inactive{color:red;font-weight:bold;}
</style>
</head>
<body>
<header>
<h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
<p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
</header>

<div class="layout">
    <aside class="sidebar">
        <img src="admin.jpg" alt="Teacher">
        <h3>Teacher Dashboard</h3>
        <nav class="nav">
            <a href="teacher_dashboard.php" class="active">🏠 Dashboard</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main">
        <h2>Assigned Courses/Batches</h2>
        <div class="table-card">
        <?php if($assignedCourses->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Batch Code</th>
                    <th>Course Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $assignedCourses->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['batch_code']) ?></td>
                    <td><?= htmlspecialchars($row['courseName']) ?></td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?></td>
                    <td class="<?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($row['status']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>You are not assigned to any courses yet.</p>
        <?php endif; ?>
        </div>
    </main>
</div>

<footer>
&copy; <?= date('Y') ?> Girls Coding Academy
</footer>

</body>
</html>
