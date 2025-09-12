<?php
session_start();

// Only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle adding assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_assignment'])) {
    $user_id  = $_POST['teacher_user_id'];
    $batch_id = $_POST['batch_id'];

    if (!empty($user_id) && !empty($batch_id)) {
        $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
        if ($teacher) {
            $teacher_id = $teacher['teacher_id'];
            $stmt = $conn->prepare("INSERT INTO course_assignments (teacher_id, batch_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $teacher_id, $batch_id);

            if ($stmt->execute()) {
                $success = "✅ Teacher assigned to batch successfully!";
            } else {
                $error = "❌ Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "⚠️ Selected user is not registered as a teacher.";
        }
    } else {
        $error = "⚠️ Please select both teacher and batch.";
    }
}

// Handle editing assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_assignment'])) {
    $assignment_id = $_POST['assignment_id'];
    $batch_id = $_POST['batch_id'];
    $user_id = $_POST['teacher_user_id'];

    if (!empty($assignment_id) && !empty($batch_id) && !empty($user_id)) {
        $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
        if ($teacher) {
            $teacher_id = $teacher['teacher_id'];
            $stmt = $conn->prepare("UPDATE course_assignments SET batch_id=?, teacher_id=? WHERE assignment_id=?");
            $stmt->bind_param("iii", $batch_id, $teacher_id, $assignment_id);

            if ($stmt->execute()) {
                $success = "✅ Assignment updated successfully!";
            } else {
                $error = "❌ Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "⚠️ Selected user is not registered as a teacher.";
        }
    } else {
        $error = "⚠️ Please select both teacher and batch.";
    }
}

// Fetch teachers
$teachers = $conn->query("SELECT user_id, firstName, lastName FROM users WHERE role='teacher'");

// Fetch batches
$batches = $conn->query("SELECT batch_id, batch_code FROM batches");

// Fetch existing assignments
$assignments = $conn->query("
    SELECT ca.assignment_id, u.firstName, u.lastName, b.batch_code, b.batch_id, ca.teacher_id, ca.created_at
    FROM course_assignments ca
    JOIN teachers t ON ca.teacher_id = t.teacher_id
    JOIN users u ON t.user_id = u.user_id
    JOIN batches b ON ca.batch_id = b.batch_id
    ORDER BY ca.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Teacher - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Reset and base */
body { font-family: Arial, sans-serif; background: #f5f6fa; color: #2c3e50; overflow-x:hidden; }

/* Header */
header {
    background: linear-gradient(90deg, #7b2cbf, #5a189a);
    color: white; padding: 20px 30px; text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
header h1 { font-weight: 600; font-size: 24px; }

/* Layout */
.container { display: flex; }


/* Sidebar */
.sidebar {
    width: 220px;
    background-color: #2c3e50;
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    padding-top: 20px;
    margin: 0; /* 👈 ensure no unwanted gap */
}


.admin-pic { width: 100px; height: 100px; border-radius: 50%; margin-bottom: 15px; border: 3px solid #7b2cbf; object-fit: cover; }
.sidebar h3 { color: white; margin-bottom: 20px; text-align: center; font-size: 16px; }
.sidebar a {
    width: 100%; display: block; color: white; text-decoration: none;
    padding: 10px 15px; margin: 5px 0; border-radius: 5px; font-size: 14px;
}
.sidebar a:hover, .sidebar a.active { background: #1abc9c; }

/* Content */
.content { flex: 1; padding: 30px; }

/* Card styling */
.card {
    background: white; padding: 20px; margin-bottom: 30px;
    border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Table */
table { width: 100%; }
th { background: linear-gradient(90deg, #7b2cbf, #5a189a); color: white; }
td, th { padding: 12px; text-align: center; }
</style>
</head>
<body>
<header>
    <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>

<div class="container">
    <div class="sidebar">
        <img src="admin.jpg" alt="Admin Picture" class="admin-pic">
        <h3>Girls Coding Academy</h3>
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="approve_users.php">📝 Approve Users</a>
        <a href="manage_courses.php">📚 Manage Courses</a>
        <a href="manage_students.php">👩‍🎓 Manage Students</a>
        <a href="manage_teachers.php">👨‍🏫 Manage Teachers</a>
        <a href="parents_summary.php">👪 Parents Summary</a>
        <a href="manage_parents.php">👪 Manage Parents</a>
            <a href="assign_parent_student.php">👨‍🏫 Assign Students</a>
        <a href="course_assignment.php" class="active">👨‍🏫 Assign Courses</a>
        <a href="add_batch.php">➕ Add Batch</a>
        <a href="logout.php">🚪 Logout</a>
    </div>

    <div class="content">
        <div class="card form-container">
            <h2>Assign Teacher to Batch</h2>
            <?php if (isset($success)) echo "<p class='text-success'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='text-danger'>$error</p>"; ?>

            <form method="POST">
                <label for="teacher_user_id">Select Teacher:</label>
                <select name="teacher_user_id" class="form-control" required>
                    <option value="">-- Choose Teacher --</option>
                    <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
                        <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
                    <?php endwhile; ?>
                </select>

                <label for="batch_id" class="mt-2">Select Batch:</label>
                <select name="batch_id" class="form-control" required>
                    <option value="">-- Choose Batch --</option>
                    <?php 
                    $batches->data_seek(0);
                    while ($row = $batches->fetch_assoc()): ?>
                        <option value="<?= $row['batch_id'] ?>"><?= $row['batch_code'] ?></option>
                    <?php endwhile; ?>
                </select>

                <button type="submit" name="add_assignment" class="btn btn-primary mt-3">Assign Teacher</button>
            </form>
        </div>

        <div class="card">
            <h2>Existing Assignments</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Teacher</th>
                        <th>Batch</th>
                        <th>Assigned On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = $assignments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $a['assignment_id'] ?></td>
                            <td><?= $a['firstName'] . " " . $a['lastName'] ?></td>
                            <td><?= $a['batch_code'] ?></td>
                            <td><?= date("d F Y H:i", strtotime($a['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal" 
                                    data-id="<?= $a['assignment_id'] ?>"
                                    data-batch="<?= $a['batch_id'] ?>"
                                    data-teacher="<?= $a['teacher_id'] ?>">✏️ Edit
                                </button>
                                <a href="delete_assignment.php?id=<?= $a['assignment_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this assignment?');">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Assignment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="assignment_id" id="edit-assignment-id">

          <label for="teacher_user_id">Select Teacher:</label>
          <select name="teacher_user_id" id="edit-teacher-id" class="form-control" required>
            <option value="">-- Choose Teacher --</option>
            <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
              <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
            <?php endwhile; ?>
          </select>

          <label for="batch_id" class="mt-2">Select Batch:</label>
          <select name="batch_id" id="edit-batch-id" class="form-control" required>
            <option value="">-- Choose Batch --</option>
            <?php $batches->data_seek(0); while ($row = $batches->fetch_assoc()): ?>
              <option value="<?= $row['batch_id'] ?>"><?= $row['batch_code'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" name="edit_assignment" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (event) {
  let button = event.relatedTarget;
  document.getElementById('edit-assignment-id').value = button.getAttribute('data-id');
  document.getElementById('edit-batch-id').value = button.getAttribute('data-batch');
  document.getElementById('edit-teacher-id').value = button.getAttribute('data-teacher');
});
</script>
</body>
</html>
