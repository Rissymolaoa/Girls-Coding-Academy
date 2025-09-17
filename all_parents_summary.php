<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$parents_sql = "
    SELECT 
        u.user_id, 
        u.firstName, 
        u.lastName, 
        u.gender, 
        u.phone, 
        u.email, 
        su.firstName AS studentFirstName,
        su.lastName  AS studentLastName,
        st.photo     AS studentPhoto
    FROM users u
    INNER JOIN parents p ON p.user_id = u.user_id
    LEFT JOIN parent_students ps ON ps.parent_id = p.parent_id
    LEFT JOIN students st ON ps.student_id = st.student_id
    LEFT JOIN users su ON st.user_id = su.user_id
    WHERE u.role = 'parent'
";

if($search){
    $search_param = "%$search%";
    $stmt = $conn->prepare($parents_sql . " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ?) ORDER BY u.created_at DESC");
    $stmt->bind_param("sss",$search_param,$search_param,$search_param);
    $stmt->execute();
    $parents = $stmt->get_result();
} else {
    $parents = $conn->query($parents_sql . " ORDER BY u.created_at DESC");
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Parents</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background: #f4f4f8; }
.sidebar { width: 220px; background: #343a40; color: #fff; min-height: 100vh; padding-top: 20px; position: fixed; }
.sidebar a { display: flex; align-items: center; padding: 10px 15px; color: #fff; text-decoration: none; border-radius: 5px; margin-bottom: 4px; }
.sidebar a:hover, .sidebar a.active { background: #495057; }
.sidebar a i { margin-right: 10px; }
.main { margin-left: 240px; padding: 20px; }
.topbar { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="text-center mb-3">
      <img src="admin.jpg" alt="Admin" class="rounded-circle" style="width:92px; height:92px; object-fit:cover; border:3px solid #1abc9c;">
    </div>
    <h4 class="text-center mb-4">Administration</h4>
    <a href="admin_dashboard.php" class="active"><i class="bi bi-house-door-fill"></i> Dashboard</a>
    <a href="approve_users.php"><i class="bi bi-person-check-fill"></i> Approve Users</a>
    <a href="manage_courses.php"><i class="bi bi-journal-bookmark-fill"></i> Manage Courses</a>
    <a href="manage_students.php"><i class="bi bi-people-fill"></i> Manage Students</a>
    <a href="manage_teachers.php"><i class="bi bi-person-badge-fill"></i> Manage Teachers</a>
    <a href="parents_summary.php"><i class="bi bi-people"></i> Parent Summary</a>
    <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Manage Parents</a>
    <a href="assign_parent_student.php"><i class="bi bi-person-plus-fill"></i> Assign Students</a>
    <a href="course_assignment.php"><i class="bi bi-book-half"></i> Assign Courses</a>
    <a href="add_batch.php"><i class="bi bi-plus-circle-fill"></i> Add Batch</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">
    <div class="topbar">
        <h2>Girls Coding Academy - All Parents</h2>
        <form method="get" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search parents..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Firstname</th>
                    <th>Lastname</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Student Name</th>
                    <th>Photo</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $parents->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($p['firstName']) ?></td>
                    <td><?= htmlspecialchars($p['lastName']) ?></td>
                    <td><?= htmlspecialchars($p['gender']) ?></td>
                    <td><?= htmlspecialchars($p['phone']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['studentFirstName'] . ' ' . $p['studentLastName']) ?></td>
                    <td>
                        <?php if (!empty($p['studentPhoto'])): ?>
                            <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" width="50" height="60" style="object-fit:cover;">
                        <?php else: ?>
                            <span>No Photo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="parents_summary.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left"></i> Back to Summary</a>
</div>
</body>
</html>
