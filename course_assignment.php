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
$db = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle adding assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_assignment'])) {
    $user_id = $_POST['teacher_user_id'];
    $batch_id = $_POST['batch_id'];

    if (!empty($user_id) && !empty($batch_id)) {
        $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc();

        if ($teacher) {
            $teacher_id = $teacher['teacher_id'];

            // ✅ Check for duplicates before inserting
            $check = $conn->prepare("SELECT * FROM course_assignments WHERE teacher_id=? AND batch_id=?");
            $check->bind_param("ii", $teacher_id, $batch_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "⚠️ This teacher is already assigned to the selected batch.";
            } else {
                $stmt = $conn->prepare("INSERT INTO course_assignments (teacher_id, batch_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $teacher_id, $batch_id);

                if ($stmt->execute()) {
                    $success = "✅ Teacher assigned to batch successfully!";
                } else {
                    $error = "❌ Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
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

            // ✅ Prevent duplicate assignment when editing
            $check = $conn->prepare("SELECT * FROM course_assignments WHERE teacher_id=? AND batch_id=? AND assignment_id != ?");
            $check->bind_param("iii", $teacher_id, $batch_id, $assignment_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "⚠️ This teacher is already assigned to the selected batch.";
            } else {
                $stmt = $conn->prepare("UPDATE course_assignments SET batch_id=?, teacher_id=? WHERE assignment_id=?");
                $stmt->bind_param("iii", $batch_id, $teacher_id, $assignment_id);

                if ($stmt->execute()) {
                    $success = "✅ Assignment updated successfully!";
                } else {
                    $error = "❌ Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family: Arial, sans-serif;
    background: #f8f9fa;
    margin: 0; /* prevent unwanted gaps */
}

header {
    background: #2c3e50;
    padding: 5px 15px; /* small height */
    position: fixed; /* stays fixed on top */
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

header .search-box {
    display: flex;
    justify-content: center;
}

.sidebar {
    width: 220px;
    background-color: #2c3e50;
    color: white;
    position: fixed;
    top: 45px; /* starts right under header */
    bottom: 0;
    left: 0;
    overflow-y: auto;
    padding-top: 20px;
}

.sidebar a {
    display: block;
    color: white;
    padding: 10px;
    text-decoration: none;
}

.sidebar a:hover, .sidebar a.active {
    background: #1abc9c;
}

.content {
    margin-left: 240px;
    margin-top: 55px; /* push content below header */
    padding: 20px;
}

.content {
    margin-left: 240px;
    padding: 20px;
}
.card {
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.table th {
    background: #2c3e50;
    color: white;
}
.table-striped tbody tr:nth-of-type(odd) {
    background-color: #f2f2f2;
}
  .sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px}

</style>
</head>
<body>
<header>
    <form class="search-box d-flex" role="search">
        <input class="form-control me-2" type="search" placeholder="Search..." aria-label="Search">
        <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
    </form>
</header>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
    <img src="admin.jpg" alt="Admin">
            <h4 class="text-center">Administration</h4>
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

        <!-- Main Content -->
        <div class="col-md-10 content">
            <div class="card p-4">
                <h2>Assign Teacher to Batch</h2>
                <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="teacher_user_id" class="form-label">Select Teacher</label>
                        <select name="teacher_user_id" class="form-select" required>
                            <option value="">-- Choose Teacher --</option>
                            <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
                                <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="batch_id" class="form-label">Select Batch</label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Choose Batch --</option>
                            <?php $batches->data_seek(0); while ($row = $batches->fetch_assoc()): ?>
                                <option value="<?= $row['batch_id'] ?>"><?= $row['batch_code'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_assignment" class="btn btn-primary">Assign Teacher</button>
                    </div>
                </form>
            </div>

            <div class="card p-4">
                <h2>Existing Assignments</h2>
                <table class="table table-bordered table-striped align-middle">
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
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id="<?= $a['assignment_id'] ?>" data-batch="<?= $a['batch_id'] ?>" data-teacher="<?= $a['teacher_id'] ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <a href="delete_assignment.php?id=<?= $a['assignment_id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this assignment?');">
                                   <i class="bi bi-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
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
          <select name="teacher_user_id" id="edit-teacher-id" class="form-select" required>
            <option value="">-- Choose Teacher --</option>
            <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
              <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
            <?php endwhile; ?>
          </select>
          <label for="batch_id" class="mt-2">Select Batch:</label>
          <select name="batch_id" id="edit-batch-id" class="form-select" required>
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
