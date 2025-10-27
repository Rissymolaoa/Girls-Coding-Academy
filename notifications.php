<?php
// notifications.php
// Admin notifications page: Displays all admin announcements with create, edit, delete options.
// Modern UI with table, modals for add/edit, responsive design.

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
$conn->set_charset("utf8mb4");

// Handle form submissions
$message = '';
if ($_POST) {
    $id = $_POST['announcement_id'] ?? null;
    $msg = trim($_POST['message']);
    $recipients = trim($_POST['recipients']);
    $file_path = $_FILES['file_path']['name'] ? 'uploads/' . basename($_FILES['file_path']['name']) : null;
    $picture_path = $_FILES['picture_path']['name'] ? 'uploads/' . basename($_FILES['picture_path']['name']) : null;

    if (!empty($msg) && !empty($recipients)) {
        if ($id) {
            // Update
            $sql = "UPDATE admin_announcements SET message=?, recipients=?, updated_at=NOW()";
            $params = [$msg, $recipients];
            $types = "ss";
            if ($file_path) {
                $sql .= ", file_path=?";
                $params[] = $file_path;
                $types .= "s";
            }
            if ($picture_path) {
                $sql .= ", picture_path=?";
                $params[] = $picture_path;
                $types .= "s";
            }
            $sql .= " WHERE announcement_id=?";
            $params[] = $id;
            $types .= "i";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = "Announcement updated successfully!";
                // Handle file uploads if present
                if ($file_path) move_uploaded_file($_FILES['file_path']['tmp_name'], $file_path);
                if ($picture_path) move_uploaded_file($_FILES['picture_path']['tmp_name'], $picture_path);
            }
        } else {
            // Insert
            $sql = "INSERT INTO admin_announcements (message, recipients, file_path, picture_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $msg, $recipients, $file_path, $picture_path);
            if ($stmt->execute()) {
                $message = "Announcement created successfully!";
                if ($file_path) {
                    $new_path = 'uploads/' . basename($_FILES['file_path']['name']);
                    move_uploaded_file($_FILES['file_path']['tmp_name'], $new_path);
                    $conn->query("UPDATE admin_announcements SET file_path='$new_path' WHERE announcement_id=LAST_INSERT_ID()");
                }
                if ($picture_path) {
                    $new_path = 'uploads/' . basename($_FILES['picture_path']['name']);
                    move_uploaded_file($_FILES['picture_path']['tmp_name'], $new_path);
                    $conn->query("UPDATE admin_announcements SET picture_path='$new_path' WHERE announcement_id=LAST_INSERT_ID()");
                }
            }
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM admin_announcements WHERE announcement_id=$id");
    $message = "Announcement deleted successfully!";
}

// Fetch all announcements
$announcements = $conn->query("SELECT * FROM admin_announcements ORDER BY created_at DESC");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Notifications & Announcements - Admin Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
  }

  .main {
    padding: 2rem;
  }

  .page-header {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }

  .table-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
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

  .btn-action {
    margin: 0 0.25rem;
  }

  .modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: var(--shadow-lg);
  }

  .modal-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 16px 16px 0 0;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>


<div class="content">
  <main class="main">
    <div class="page-header">
      <h1>Notifications & Announcements</h1>
      <p class="text-muted">Manage announcements sent to students, teachers, and parents.</p>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Create New Announcement
      </button>
    </div>

    <div class="table-section">
      <h2>All Announcements</h2>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Message</th>
              <th>Recipients</th>
              <th>Created At</th>
              <th>Updated At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($ann = $announcements->fetch_assoc()): ?>
              <tr>
                <td><?= $ann['announcement_id'] ?></td>
                <td><?= htmlspecialchars(substr($ann['message'], 0, 50)) ?>...</td>
                <td><span class="badge bg-secondary"><?= ucfirst($ann['recipients']) ?></span></td>
                <td><?= date('M j, Y H:i', strtotime($ann['created_at'])) ?></td>
                <td><?= date('M j, Y H:i', strtotime($ann['updated_at'])) ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editAnnouncement(<?= $ann['announcement_id'] ?>, '<?= addslashes($ann['message']) ?>', '<?= $ann['recipients'] ?>')">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <a href="?delete=<?= $ann['announcement_id'] ?>" class="btn btn-sm btn-outline-danger btn-action" onclick="return confirm('Are you sure?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if ($announcements->num_rows === 0): ?>
              <tr><td colspan="6" class="text-center text-muted">No announcements yet. Create one to get started!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create New Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" class="form-control" rows="4" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Recipients</label>
            <select name="recipients" class="form-select" required>
              <option value="students">Students</option>
              <option value="teachers">Teachers</option>
              <option value="parents">Parents</option>
              <option value="all">All</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Attachment (File)</label>
            <input type="file" name="file_path" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Image</label>
            <input type="file" name="picture_path" class="form-control" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="announcement_id" id="edit_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" id="edit_message" class="form-control" rows="4" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Recipients</label>
            <select name="recipients" id="edit_recipients" class="form-select" required>
              <option value="students">Students</option>
              <option value="teachers">Teachers</option>
              <option value="parents">Parents</option>
              <option value="all">All</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">New Attachment (File)</label>
            <input type="file" name="file_path" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">New Image</label>
            <input type="file" name="picture_path" class="form-control" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function editAnnouncement(id, message, recipients) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_message').value = message;
    document.getElementById('edit_recipients').value = recipients;
  }
</script>

<?php $conn->close(); ?>
</body>
</html>