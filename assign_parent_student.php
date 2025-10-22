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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

  .message {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    text-align: center;
    font-weight: 500;
  }

  .message.success {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.2);
  }

  .message.error {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.2);
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

  .modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 1050;
  }

  .modal-content {
    background: #fff;
    padding: 2rem;
    border-radius: 16px;
    width: 400px;
    max-width: 90%;
    position: relative;
    box-shadow: var(--shadow-lg);
  }

  .close {
    position: absolute;
    top: 1rem; right: 1.5rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.3s ease;
  }

  .close:hover {
    color: #1f2937;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="section-card">
      <h2>Assign Student to Parent</h2>
      <?php if(isset($success)): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
      <?php elseif(isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="form-row">
        <input type="hidden" name="action" value="add">
        <select name="parent_id" class="form-control" required>
          <option value="">-- Choose Parent --</option>
          <?php $parents->data_seek(0); while($p = $parents->fetch_assoc()): ?>
            <option value="<?= $p['parent_id'] ?>">
              <?= htmlspecialchars($p['firstName']." ".$p['lastName']." (".$p['relationship'].")") ?>
            </option>
          <?php endwhile; ?>
        </select>
        <select name="student_id" class="form-control" required>
          <option value="">-- Choose Student --</option>
          <?php $students->data_seek(0); while($s = $students->fetch_assoc()): ?>
            <option value="<?= $s['student_id'] ?>">
              <?= htmlspecialchars($s['firstName']." ".$s['lastName']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <select name="relationship" class="form-control" required>
          <option value="">-- Choose Relationship --</option>
          <option value="Mother">Mother</option>
          <option value="Father">Father</option>
          <option value="Guardian">Guardian</option>
        </select>
        <button type="submit" class="btn btn-primary">Assign Student</button>
      </form>
    </div>

    <div class="section-card">
      <h2>Existing Assignments</h2>
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="assignmentsTable">
          <thead>
            <tr>
              <th>Parent</th>
              <th>Student</th>
              <th>Relationship</th>
              <th>Assigned At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($a = $assignments->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($a['parentFirst']." ".$a['parentLast']) ?></td>
              <td><?= htmlspecialchars($a['studentFirst']." ".$a['studentLast']) ?></td>
              <td><?= htmlspecialchars($a['relationship']) ?></td>
              <td><?= htmlspecialchars($a['created_at']) ?></td>
              <td>
                <button class="editBtn btn btn-warning btn-sm me-2" data-id="<?= $a['id'] ?>" data-relationship="<?= htmlspecialchars($a['relationship']) ?>"><i class="bi bi-pencil"></i> Edit</button>
                <form method="POST" style="display:inline;" class="d-inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this assignment?');"><i class="bi bi-trash"></i> Delete</button>
                </form>
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
<div id="editModal" class="modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label for="edit_relationship" class="form-label">Relationship:</label>
          <select name="relationship" id="edit_relationship" class="form-control" required>
            <option value="Mother">Mother</option>
            <option value="Father">Father</option>
            <option value="Guardian">Guardian</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const editModal = new bootstrap.Modal(document.getElementById('editModal'));
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', function(){
    const id = this.dataset.id;
    const relationship = this.dataset.relationship;
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_relationship').value = relationship;
    editModal.show();
  });
});
</script>
</body>
</html>