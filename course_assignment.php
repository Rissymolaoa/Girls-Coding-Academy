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
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Teacher - Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
  }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding-top: 56px;
  }

  .content {
    min-height: calc(100vh - 56px);
    transition: all 0.3s ease;
  }

  .main {
    padding: 2rem 2rem 2rem 1rem;
  }

  .section-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .section-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .form-row .form-control {
    padding: 0.75rem;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
  }

  .btn-primary {
    background: var(--primary-gradient);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
  }

  .table {
    margin-bottom: 0;
  }

  .table th {
    background: var(--primary-gradient);
    color: white;
    border: none;
    font-weight: 600;
    padding: 1rem;
  }

  .table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: rgba(0,0,0,0.05);
  }

  .table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
  }

  .btn-warning, .btn-danger {
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    margin-right: 0.5rem;
  }

  footer {
    background: rgba(31, 41, 55, 0.8);
    color: #fff;
    text-align: center;
    padding: 1.5rem;
    margin-top: 2rem;
    border-radius: 16px 16px 0 0;
  }

  /* Enhanced Sidebar Styles - Adjusted for Dashboard */
  .sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    position: fixed;
    top: 56px;
    height: calc(100vh - 56px);
    left: 0;
    overflow-y: auto;
    transition: all 0.3s ease;
    box-shadow: 4px 0 15px rgba(0,0,0,0.2);
    z-index: 1030;
  }

  @media (min-width: 992px) {
    .main {
      padding-left: 1rem;
      padding-right: 2rem;
    }
    .content {
      margin-left: 280px;
    }
  }

  @media (max-width: 991px) {
    .sidebar {
      top: 0;
      height: 100vh;
      left: -280px;
    }
    .sidebar.show {
      left: 0;
    }
    .main {
      padding: 1rem;
    }
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .form-row {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="section-card">
      <h2>Assign Teacher to Batch</h2>
      <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
      <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

      <form method="POST" class="form-row">
        <select name="teacher_user_id" class="form-control" required>
          <option value="">-- Choose Teacher --</option>
          <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
            <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
          <?php endwhile; ?>
        </select>
        <select name="batch_id" class="form-control" required>
          <option value="">-- Choose Batch --</option>
          <?php $batches->data_seek(0); while ($row = $batches->fetch_assoc()): ?>
            <option value="<?= $row['batch_id'] ?>"><?= $row['batch_code'] ?></option>
          <?php endwhile; ?>
        </select>
        <button type="submit" name="add_assignment" class="btn btn-primary">Assign Teacher</button>
      </form>
    </div>

    <div class="section-card">
      <h2>Existing Assignments</h2>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
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
                <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editModal"
                  data-id="<?= $a['assignment_id'] ?>" data-batch="<?= $a['batch_id'] ?>" data-teacher="<?= $a['teacher_id'] ?>">
                  <i class="bi bi-pencil"></i> Edit
                </button>
                <a href="delete_assignment.php?id=<?= $a['assignment_id'] ?>" 
                   class="btn btn-danger btn-sm"
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
  </main>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header" style="background: var(--primary-gradient); color: white;">
          <h5 class="modal-title">Edit Assignment</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body form-row">
          <input type="hidden" name="assignment_id" id="edit-assignment-id">
          <select name="teacher_user_id" id="edit-teacher-id" class="form-control" required>
            <option value="">-- Choose Teacher --</option>
            <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
              <option value="<?= $row['user_id'] ?>"><?= $row['firstName'] . " " . $row['lastName'] ?></option>
            <?php endwhile; ?>
          </select>
          <select name="batch_id" id="edit-batch-id" class="form-control" required>
            <option value="">-- Choose Batch --</option>
            <?php $batches->data_seek(0); while ($row = $batches->fetch_assoc()): ?>
              <option value="<?= $row['batch_id'] ?>"><?= $row['batch_code'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_assignment" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

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