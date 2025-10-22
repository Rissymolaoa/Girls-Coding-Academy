<?php
include("db.php");

$message = "";

$courses_result = $conn->query("SELECT * FROM courses");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch'])) {
    $course_id  = $_POST['course_id'];
    $batch_code = $_POST['batch_code'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO batches (batch_code, course_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $batch_code, $course_id, $start_date, $end_date, $status);

    if($stmt->execute()){
        $message = "Batch added successfully!";
    } else {
        $message = "Error: ".$stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch'])) {
    $batch_id   = $_POST['batch_id'];
    $course_id  = $_POST['course_id'];
    $batch_code = $_POST['batch_code'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = $_POST['status'];

    $stmt = $conn->prepare("UPDATE batches SET course_id=?, batch_code=?, start_date=?, end_date=?, status=? WHERE batch_id=?");
    $stmt->bind_param("issssi", $course_id, $batch_code, $start_date, $end_date, $status, $batch_id);

    if($stmt->execute()){
        $message = "Batch updated successfully!";
    } else {
        $message = "Error updating: ".$stmt->error;
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM batches WHERE batch_id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Batch deleted successfully!";
    } else {
        $message = "Error deleting batch: " . $stmt->error;
    }
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "1";
if($search !== '') {
    $where .= " AND (b.batch_code LIKE '%$search%' OR c.courseName LIKE '%$search%')";
}

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page-1)*$limit;

$total_result = $conn->query("SELECT COUNT(*) as total 
                              FROM batches b 
                              JOIN courses c ON c.course_id=b.course_id 
                              WHERE $where");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

$batches_q = "SELECT b.*, c.courseName, c.course_id 
              FROM batches b 
              JOIN courses c ON c.course_id=b.course_id 
              WHERE $where 
              ORDER BY c.courseName, b.batch_code 
              LIMIT $limit OFFSET $offset";
$batches = $conn->query($batches_q);

$qs = '';
if(!empty($_GET)){
    $params = $_GET;
    unset($params['page']);
    $qs = !empty($params) ? '&'.http_build_query($params) : '';
}

$course_colors = [];
$color_palette = ['#FFEBEE','#E3F2FD','#E8F5E9','#FFF3E0','#F3E5F5','#E0F7FA'];
$color_index = 0;
$courses_result->data_seek(0);
while($course = $courses_result->fetch_assoc()) {
    $course_colors[$course['courseName']] = $color_palette[$color_index % count($color_palette)];
    $color_index++;
}
$courses_result->data_seek(0); 
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Batch - Admin Dashboard</title>
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

  .search-form {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .search-form .form-control {
    flex: 1;
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

  .pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
  }

  .pagination a, .pagination span {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    background: rgba(255,255,255,0.8);
    color: #1f2937;
    font-weight: 500;
  }

  .pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    width: 500px;
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
    .search-form {
      flex-direction: column;
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="row">
      <div class="col-lg-4">
        <div class="section-card">
          <h2>Add Batch</h2>
          <?php if($message) echo "<div class='alert alert-success mb-3'>$message</div>"; ?>

          <form method="POST" class="form-row">
            <input type="hidden" name="add_batch" value="1">
            <select name="course_id" class="form-control" required>
              <option value="">-- Select Course --</option>
              <?php
              $courses_result->data_seek(0);
              while($course = $courses_result->fetch_assoc()) {
                  echo "<option value='".$course['course_id']."'>".$course['courseName']."</option>";
              }
              ?>
            </select>
            <input type="text" name="batch_code" class="form-control" placeholder="Batch Code" required>
            <input type="date" name="start_date" class="form-control" required>
            <input type="date" name="end_date" class="form-control" required>
            <select name="status" class="form-control" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary">Add Batch</button>
          </form>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="section-card">
          <div class="search-form">
            <input type="text" name="search" placeholder="Search batches or courses..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
          </div>

          <h2>Existing Batches</h2>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Batch Code</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if($batches->num_rows > 0): ?>
                  <?php while($b = $batches->fetch_assoc()): 
                      $bg_color = $course_colors[$b['courseName']] ?? 'rgba(255,255,255,0.8)';
                  ?>
                    <tr style="background:<?= $bg_color ?>;">
                      <td><?= htmlspecialchars($b['courseName']) ?></td>
                      <td><?= htmlspecialchars($b['batch_code']) ?></td>
                      <td><?= htmlspecialchars($b['start_date']) ?></td>
                      <td><?= htmlspecialchars($b['end_date']) ?></td>
                      <td><span class="badge bg-success"><?= htmlspecialchars($b['status']) ?></span></td>
                      <td>
                        <button class="btn btn-warning btn-sm me-2" onclick="openEditModal(
                          '<?= $b['batch_id'] ?>',
                          '<?= $b['course_id'] ?>',
                          '<?= htmlspecialchars($b['batch_code'], ENT_QUOTES) ?>',
                          '<?= $b['start_date'] ?>',
                          '<?= $b['end_date'] ?>',
                          '<?= $b['status'] ?>'
                        )"><i class="bi bi-pencil"></i> Edit</button>
                        <a href="?delete=<?= $b['batch_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this batch?');"><i class="bi bi-trash"></i> Delete</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">No batches found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="pagination">
            <?php if($page>1): ?>
              <a href="?page=<?= ($page-1) . $qs ?>">&laquo; Prev</a>
            <?php else: ?>
              <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>

            <span class="align-self-center mx-3">Page <?= $page ?> of <?= $total_pages ?></span>

            <?php if($page<$total_pages): ?>
              <a href="?page=<?= ($page+1) . $qs ?>">Next &raquo;</a>
            <?php else: ?>
              <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background: var(--primary-gradient); color: white;">
        <h5 class="modal-title">Edit Batch</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="modal-body form-row">
        <input type="hidden" name="update_batch" value="1">
        <input type="hidden" name="batch_id" id="edit_batch_id">
        <select name="course_id" id="edit_course_id" class="form-control" required>
          <?php
          $courses_result->data_seek(0);
          while ($row = $courses_result->fetch_assoc()):
          ?>
            <option value="<?= $row['course_id'] ?>"><?= htmlspecialchars($row['courseName']) ?></option>
          <?php endwhile; ?>
        </select>
        <input type="text" name="batch_code" id="edit_batch_code" class="form-control" placeholder="Batch Code" required>
        <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
        <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
        <select name="status" id="edit_status" class="form-control" required>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Completed">Completed</option>
          <option value="Pending">Pending</option>
        </select>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Batch</button>
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
function openEditModal(id, course_id, code, start, end, status) {
  document.getElementById('edit_batch_id').value = id;
  document.getElementById('edit_course_id').value = course_id;
  document.getElementById('edit_batch_code').value = code;
  document.getElementById('edit_start_date').value = start;
  document.getElementById('edit_end_date').value = end;
  document.getElementById('edit_status').value = status;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>