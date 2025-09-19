<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

// Handle new assignment
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $parent_id = $_POST['parent_id'];
    $student_id = $_POST['student_id'];
    $relationship = $_POST['relationship'];

    if (!empty($parent_id) && !empty($student_id) && !empty($relationship)) {
        // Prevent duplicates
        $check = $conn->prepare("SELECT * FROM parent_students WHERE parent_id=? AND student_id=?");
        $check->bind_param("ii", $parent_id, $student_id);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "This student is already assigned to this parent.";
        } else {
            $stmt = $conn->prepare("INSERT INTO parent_students (parent_id, student_id, relationship, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $parent_id, $student_id, $relationship);
            if ($stmt->execute()) {
                $success = "Student successfully assigned to parent.";
            } else {
                $error = "Error assigning student: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    } else {
        $error = "All fields are required.";
    }
}

// Handle edit
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'];
    $relationship = $_POST['relationship'];
    if(!empty($id) && !empty($relationship)){
        $stmt = $conn->prepare("UPDATE parent_students SET relationship=? WHERE id=?");
        $stmt->bind_param("si", $relationship, $id);
        if($stmt->execute()){
            $success = "Assignment updated successfully.";
        } else {
            $error = "Error updating assignment: ".$conn->error;
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'];
    if(!empty($id)){
        $stmt = $conn->prepare("DELETE FROM parent_students WHERE id=?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){
            $success = "Assignment deleted successfully.";
        } else {
            $error = "Error deleting assignment: ".$conn->error;
        }
        $stmt->close();
    }
}

// Fetch parents
$parents = $conn->query("
    SELECT p.parent_id, u.firstName, u.lastName, p.relationship 
    FROM parents p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY u.firstName
");

// Fetch students
$students = $conn->query("
    SELECT s.student_id, u.firstName, u.lastName 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE u.role='student'
    ORDER BY u.firstName
");

// Fetch existing assignments
$assignments = $conn->query("
    SELECT ps.id, u1.firstName AS parentFirst, u1.lastName AS parentLast, 
           u2.firstName AS studentFirst, u2.lastName AS studentLast, ps.relationship, ps.created_at
    FROM parent_students ps
    JOIN parents p ON ps.parent_id = p.parent_id
    JOIN users u1 ON p.user_id = u1.user_id
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u2 ON s.user_id = u2.user_id
    ORDER BY ps.created_at DESC
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Assign Student to Parent</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style> :root{ --primary:#7b2cbf; --accent:#5a189a; --muted:#f4f4f8; --card:#ffffff; --text:#222; } 
*{box-sizing:border-box} body{font-family:Inter,Arial,Helvetica,sans-serif;margin:0;background:var(--muted);color:var(--text)} header{ background:linear-gradient(90deg,var(--primary),var(--accent)); color:#fff; padding:18px 24px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.12) } 
header h1{margin:0;font-size:20px;font-weight:600} /* Layout */ .layout{display:flex;min-height:calc(100vh - 72px)} .sidebar{ width:220px; background:#34495e; padding:20px; display:flex; flex-direction:column; align-items:center; color:#fff; } 
.sidebar img{ width:92px; height:92px; border-radius:50%; object-fit:cover; border:3px solid #1abc9c; margin-bottom:12px; } 
.sidebar h3{font-size:13px;margin:0 0 12px} 
    header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }
    
    h1{margin:0;
    font-size:20px;
    font-weight:600}
.nav a{ width:100%; display:block; color:#fff; text-decoration:none; padding:10px; border-radius:6px; margin:6px 0; text-align:left; } 
.nav a.active, .nav a:hover{background:#1abc9c;color:#062018} 
.main{ flex:1; padding:26px; } h2{margin-bottom:16px;color:#333} 
form{display:flex;flex-direction:column;gap:15px;margin-bottom:25px;} 
label{font-weight:bold;} select,input{padding:10px;border:1px solid #ccc;border-radius:6px;width:100%;} 
button{background:var(--accent);color:#fff;padding:12px;border:none;border-radius:6px;cursor:pointer;font-size:16px;}
 button:hover{background:var(--primary);} .message{margin:10px 0;padding:10px;border-radius:6px;text-align:center;} 
 .success{background:#d4edda;color:#155724;} .error{background:#f8d7da;color:#721c24;} table{width:100%;border-collapse:collapse;margin-top:20px;} 
 th,td{border:1px solid #ddd;padding:10px;text-align:left;} th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;} 
 tr:hover{background:#f9f9f9;} footer{background:#2c3e50;color:#fff;text-align:center;padding:15px;margin-top:30px;} @media(max-width:900px){.sidebar{display:none}} 
 </style>
</head>
<body>

<header>
  <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>

<div class="layout">
  <aside class="sidebar">
    <img src="admin.jpg" alt="Admin">
    <h3>GIRLS CODING ACADEMY</h3>
    <nav class="nav">
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
    </nav>
  </aside>

  <main class="main">
    <h2>Assign Student to Parent</h2>
    <?php if(isset($success)): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php elseif(isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="add">
    <label for="parent_id">Select Parent:</label>
    <select name="parent_id" id="parent_id" required>
        <option value="">-- Choose Parent --</option>
        <?php while($p = $parents->fetch_assoc()): ?>
            <option value="<?= $p['parent_id'] ?>">
                <?= htmlspecialchars($p['firstName']." ".$p['lastName']." (".$p['relationship'].")") ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label for="student_id">Select Student:</label>
    <select name="student_id" id="student_id" required>
        <option value="">-- Choose Student --</option>
        <?php while($s = $students->fetch_assoc()): ?>
            <option value="<?= $s['student_id'] ?>">
                <?= htmlspecialchars($s['firstName']." ".$s['lastName']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label for="relationship">Relationship to Student:</label>
    <select name="relationship" id="relationship" required>
        <option value="">-- Choose Relationship --</option>
        <option value="Mother">Mother</option>
        <option value="Father">Father</option>
        <option value="Guardian">Guardian</option>
    </select>

    <button type="submit">Assign Student</button>
</form>

<h2>Existing Assignments</h2>
<table id="assignmentsTable">
    <tr>
        <th>Parent</th>
        <th>Student</th>
        <th>Relationship</th>
        <th>Assigned At</th>
        <th>Actions</th>
    </tr>
    <?php while($a = $assignments->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($a['parentFirst']." ".$a['parentLast']) ?></td>
        <td><?= htmlspecialchars($a['studentFirst']." ".$a['studentLast']) ?></td>
        <td><?= htmlspecialchars($a['relationship']) ?></td>
        <td><?= htmlspecialchars($a['created_at']) ?></td>
        <td>
            <button class="editBtn" data-id="<?= $a['id'] ?>" data-relationship="<?= htmlspecialchars($a['relationship']) ?>">✏️ Edit</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" onclick="return confirm('Are you sure you want to delete this assignment?');">🗑️ Delete</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
  <div style="background:#fff;padding:20px;border-radius:10px;min-width:300px;position:relative;">
    <h3>Edit Assignment</h3>
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <label for="edit_relationship">Relationship:</label>
        <select name="relationship" id="edit_relationship" required>
            <option value="Mother">Mother</option>
            <option value="Father">Father</option>
            <option value="Guardian">Guardian</option>
        </select>
        <div style="margin-top:15px;text-align:right;">
            <button type="button" id="closeModal" style="margin-right:10px;">Cancel</button>
            <button type="submit">Save Changes</button>
        </div>
    </form>
  </div>
</div>

<script>
const editModal = document.getElementById('editModal');
const closeModal = document.getElementById('closeModal');

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function(){
        const id = this.dataset.id;
        const relationship = this.dataset.relationship;

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_relationship').value = relationship;

        editModal.style.display = 'flex';
    });
});

closeModal.addEventListener('click', () => editModal.style.display = 'none');
</script>

<footer>
  &copy; <?= date('Y') ?> Girls Coding Academy. All rights reserved.
</footer>

</body>
</html>
