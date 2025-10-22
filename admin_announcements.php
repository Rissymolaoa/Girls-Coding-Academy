<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_announcement'])) {
    $message = trim($_POST['message'] ?? '');
    $recipients = $_POST['recipients'] ?? [];

    if ($message === '') {
        $errors[] = "Message cannot be empty.";
    }
    if (empty($recipients)) {
        $errors[] = "Please select at least one recipient group.";
    }

    $file_path = null;
    $picture_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed_file_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($_FILES['file']['type'], $allowed_file_types)) {
            $errors[] = "Invalid file format. Allowed: PDF, DOC, DOCX.";
        } else {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('announcement_file_') . "." . $ext;
            $upload_dir = __DIR__ . "/uploads/announcements/files/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                $file_path = "uploads/announcements/files/" . $new_filename;
            } else {
                $errors[] = "Failed to upload the file.";
            }
        }
    }

    // Handle picture upload
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['picture']['type'], $allowed_img_types)) {
            $errors[] = "Invalid image format. Allowed: JPEG, PNG, GIF.";
        } else {
            $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $new_img_name = uniqid('announcement_img_') . "." . $ext;
            $upload_dir_img = __DIR__ . "/uploads/announcements/images/";
            if (!is_dir($upload_dir_img)) mkdir($upload_dir_img, 0755, true);
            $target_img = $upload_dir_img . $new_img_name;
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $target_img)) {
                $picture_path = "uploads/announcements/images/" . $new_img_name;
            } else {
                $errors[] = "Failed to upload the image.";
            }
        }
    }

    if (empty($errors)) {
        $recipients_str = implode(',', $recipients);
        $stmt = $conn->prepare("INSERT INTO admin_announcements (message, file_path, picture_path, recipients) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $message, $file_path, $picture_path, $recipients_str);
        if ($stmt->execute()) {
            $success = "Announcement published successfully!";
        } else {
            $errors[] = "Database error: Could not save announcement.";
        }
        $stmt->close();
    }
}

$announcements_sql = "SELECT * FROM admin_announcements";
if ($search !== '') {
    $search_param = $conn->real_escape_string("%$search%");
    $announcements_sql .= " WHERE message LIKE '$search_param' OR recipients LIKE '$search_param' ";
}
$announcements_sql .= " ORDER BY created_at DESC";
$announcements = $conn->query($announcements_sql);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Announcements - Girls Coding Academy</title>
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

  .form-row textarea {
    grid-column: span 2;
    min-height: 100px;
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

  .announcement-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-gradient);
  }

  .announcement-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
  }

  .announcement-card .recipients {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
  }

  .announcement-card .date {
    font-size: 0.875rem;
    color: #6b7280;
    float: right;
  }

  .announcement-card img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 1rem;
  }

  .announcement-card .attachment-link {
    display: inline-block;
    margin-top: 0.5rem;
    color: var(--primary-gradient);
    text-decoration: none;
    font-weight: 500;
  }

  .announcement-card .attachment-link:hover {
    text-decoration: underline;
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
    .form-row textarea {
      grid-column: span 1;
    }
    .search-form {
      flex-direction: column;
    }
    .announcement-card {
      padding: 1rem;
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
      <div class="search-form">
        <input type="text" name="search" class="form-control" placeholder="Search announcements..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
      </div>

      <h2>Post New Announcement</h2>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="form-row">
        <textarea name="message" class="form-control" rows="4" placeholder="Enter announcement message..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Send To</label>
            <div class="d-flex flex-column gap-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="recStudents" name="recipients[]" value="students" <?= isset($_POST['recipients']) && in_array('students', $_POST['recipients']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="recStudents">Students</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="recTeachers" name="recipients[]" value="teachers" <?= isset($_POST['recipients']) && in_array('teachers', $_POST['recipients']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="recTeachers">Teachers</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="recParents" name="recipients[]" value="parents" <?= isset($_POST['recipients']) && in_array('parents', $_POST['recipients']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="recParents">Parents</label>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label for="picture" class="form-label">Attach Picture (optional)</label>
            <input type="file" class="form-control" id="picture" name="picture" accept="image/*" />
            <label for="file" class="form-label mt-3">Attach File (optional)</label>
            <input type="file" class="form-control" id="file" name="file" accept=".pdf,.doc,.docx" />
          </div>
        </div>
        <button type="submit" name="submit_announcement" class="btn btn-primary">Publish Announcement</button>
      </form>
    </div>

    <div class="section-card">
      <h2>All Announcements</h2>
      <?php if ($announcements && $announcements->num_rows > 0): ?>
        <?php while ($ann = $announcements->fetch_assoc()): ?>
          <div class="announcement-card">
            <div class="d-flex justify-content-between align-items-start">
              <div class="recipients">Recipients: <?= htmlspecialchars($ann['recipients']) ?></div>
              <div class="date"><?= date("F j, Y, g:i A", strtotime($ann['created_at'])) ?></div>
            </div>
            <p class="mb-3"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>

            <?php if (!empty($ann['picture_path'])): ?>
              <img src="<?= htmlspecialchars($ann['picture_path']) ?>" alt="Announcement picture" class="img-fluid rounded" />
            <?php endif; ?>
            <?php if (!empty($ann['file_path'])): ?>
              <a href="<?= htmlspecialchars($ann['file_path']) ?>" target="_blank" rel="noopener noreferrer" class="attachment-link">
                <i class="bi bi-download me-1"></i>Download attachment
              </a>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center py-4 text-muted">No announcements posted yet.</div>
      <?php endif; ?>
    </div>
  </main>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>