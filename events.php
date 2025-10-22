<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

$errors = [];
$success = "";

// Handle file upload & form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time_start = $_POST['event_time_start'] ?? '';
    $event_time_end = $_POST['event_time_end'] ?? '';
    $event_category = $_POST['event_category'] ?? '';
    $event_location = trim($_POST['event_location'] ?? '');
    $post_immediately = isset($_POST['post_immediately']) ? 1 : 0;

    // Validate required fields
    if ($event_title === '') {
        $errors[] = "Event title is required.";
    }
    if ($event_date === '') {
        $errors[] = "Event date is required.";
    }

    // Prepare photo upload
    $photo_path = null;
    if (!empty($_FILES['event_photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['event_photo']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed for the event photo.";
        } elseif ($_FILES['event_photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = "Event photo must be less than 2MB.";
        } else {
            $ext = pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('event_', true) . "." . $ext;
            $upload_dir = __DIR__ . "/uploads/events/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $target_file)) {
                $photo_path = "uploads/events/" . $new_filename;
            } else {
                $errors[] = "Failed to upload event photo.";
            }
        }
    }

    // Duplicate event check
    if (empty($errors)) {
        $dup_check_stmt = $conn->prepare("SELECT event_id FROM events WHERE title=? AND event_date=? AND event_time_start=?");
        $dup_check_stmt->bind_param("sss", $event_title, $event_date, $event_time_start);
        $dup_check_stmt->execute();
        $dup_check_stmt->store_result();

        if ($dup_check_stmt->num_rows > 0) {
            $errors[] = "An event with the same title, date, and start time already exists.";
        }
        $dup_check_stmt->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time_start, event_time_end, category, location, photo, is_posted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssi", $event_title, $event_description, $event_date, $event_time_start, $event_time_end, $event_category, $event_location, $photo_path, $post_immediately);
        if ($stmt->execute()) {
            $success = "Event posted successfully.";
            $_POST = [];  // Clear form on success
        } else {
            $errors[] = "Failed to post event. Please try again.";
        }
    }
}

// Fetch summary counts for cards
$total_posted_events = $conn->query("SELECT COUNT(*) AS total FROM events WHERE is_posted=1")->fetch_assoc()['total'];
$total_available_events = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc()['total'];

// Event categories for dropdown
$categories = ['Competition', 'Festival', 'Graduation', 'Other'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin - Post Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
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

  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
  }

  .summary-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
  }

  .summary-card i {
    font-size: 2rem;
    color: var(--primary-gradient);
    margin-bottom: 0.5rem;
  }

  .summary-card h6 {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
  }

  .summary-card p {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
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

  .preview-image {
    width: 200px;
    height: 150px;
    object-fit: cover;
    margin: 1rem auto;
    display: block;
    border-radius: 8px;
    border: 2px solid var(--primary-gradient);
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

  .alert {
    border-radius: 8px;
    border: none;
    padding: 1rem;
    margin-bottom: 1.5rem;
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
    .summary-cards {
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="summary-cards">
      <a href="posted_events.php" class="summary-card">
        <i class="bi bi-check-circle"></i>
        <h6>Posted Events</h6>
        <p><?= intval($total_posted_events) ?></p>
      </a>
      <a href="all_events.php" class="summary-card">
        <i class="bi bi-calendar3"></i>
        <h6>Available Events</h6>
        <p><?= intval($total_available_events) ?></p>
      </a>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="section-card">
      <form method="POST" action="" enctype="multipart/form-data" novalidate class="form-row">
        <img src="#" id="photoPreview" class="preview-image" alt="Event photo preview" style="display:none;" />

        <input type="file" id="event_photo" name="event_photo" accept="image/*" class="form-control" />
        <input type="text" id="event_title" name="event_title" class="form-control" placeholder="Event Title *" required value="<?= htmlspecialchars($_POST['event_title'] ?? '') ?>" />
        <textarea id="event_description" name="event_description" class="form-control" rows="4" placeholder="Description"><?= htmlspecialchars($_POST['event_description'] ?? '') ?></textarea>
        <div class="row">
          <div class="col-md-4">
            <input type="date" id="event_date" name="event_date" class="form-control" placeholder="Event Date *" required value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" />
          </div>
          <div class="col-md-4">
            <input type="time" id="event_time_start" name="event_time_start" class="form-control" placeholder="Start Time" value="<?= htmlspecialchars($_POST['event_time_start'] ?? '') ?>" />
          </div>
          <div class="col-md-4">
            <input type="time" id="event_time_end" name="event_time_end" class="form-control" placeholder="End Time" value="<?= htmlspecialchars($_POST['event_time_end'] ?? '') ?>" />
          </div>
        </div>
        <select id="event_category" name="event_category" class="form-control" placeholder="Category">
          <?php foreach ($categories as $category): ?>
            <option value="<?= htmlspecialchars($category) ?>" <?= (($_POST['event_category'] ?? '') === $category) ? 'selected' : '' ?>>
              <?= htmlspecialchars($category) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" id="event_location" name="event_location" class="form-control" placeholder="Location" value="<?= htmlspecialchars($_POST['event_location'] ?? '') ?>" />
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="post_immediately" name="post_immediately" <?= isset($_POST['post_immediately']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="post_immediately">Post Immediately</label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Post Event</button>
      </form>
    </div>
  </main>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Image preview logic
document.getElementById('event_photo').addEventListener('change', function(e) {
  const preview = document.getElementById('photoPreview');
  if (this.files && this.files[0]) {
    preview.src = URL.createObjectURL(this.files[0]);
    preview.style.display = 'block';
  } else {
    preview.src = '#';
    preview.style.display = 'none';
  }
});
</script>
</body>
</html>