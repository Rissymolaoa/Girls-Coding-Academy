<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.html");
  exit();
}

include("db.php");

// Include navigations
include("top_navigation.php");
include("admin_navigation.php");

$current_page = basename($_SERVER['PHP_SELF']);

// Handle event edit POST from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
  $event_id = intval($_POST['event_id']);
  $title = trim($_POST['title']);
  $description = trim($_POST['description']);
  $event_date = $_POST['event_date'];
  $event_time_start = $_POST['event_time_start'];
  $event_time_end = $_POST['event_time_end'];
  $category = trim($_POST['category']);
  $location = trim($_POST['location']);

  $errors = [];
  if ($title === '') $errors[] = "Title is required.";
  if ($event_date === '') $errors[] = "Date is required.";

  $photo_path = null;
  if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['photo']['type'], $allowed_types)) {
      $errors[] = "Only JPG, PNG, GIF images are allowed.";
    } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
      $errors[] = "Image must be less than 2MB.";
    } else {
      $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
      $new_filename = uniqid('event_', true) . "." . $ext;
      $upload_dir = __DIR__ . "/uploads/events/";
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
      $target_file = $upload_dir . $new_filename;
      if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = "uploads/events/" . $new_filename;
      } else {
        $errors[] = "Failed to upload photo.";
      }
    }
  }

  if (empty($errors)) {
    if ($photo_path !== null) {
      $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time_start=?, event_time_end=?, category=?, location=?, photo=? WHERE event_id=?");
      $stmt->bind_param("ssssssssi", $title, $description, $event_date, $event_time_start, $event_time_end, $category, $location, $photo_path, $event_id);
    } else {
      $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time_start=?, event_time_end=?, category=?, location=? WHERE event_id=?");
      $stmt->bind_param("sssssssi", $title, $description, $event_date, $event_time_start, $event_time_end, $category, $location, $event_id);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// Handle publish toggle via GET
if (isset($_GET['toggle_post'])) {
  $event_id = intval($_GET['toggle_post']);
  $current_status = $conn->query("SELECT is_posted FROM events WHERE event_id=$event_id")->fetch_assoc()['is_posted'] ?? 0;
  $new_status = $current_status ? 0 : 1;
  $conn->query("UPDATE events SET is_posted=$new_status WHERE event_id=$event_id");
  header("Location: " . strtok($_SERVER["REQUEST_URI"],'?'));
  exit();
}

// Handle search query
$search = trim($_GET['search'] ?? '');
$search_sql = "";
$param = "";
if ($search !== '') {
  $param = $conn->real_escape_string("%$search%");
  $search_sql = " WHERE title LIKE '$param' OR category LIKE '$param' OR location LIKE '$param' ";
}

// Fetch up to 10 events filtered by search
$events_sql = "SELECT * FROM events $search_sql ORDER BY event_date DESC, event_time_start LIMIT 10";
$events_result = $conn->query($events_sql);

// Fetch 3 events for slider ignoring search
$slider_sql = "SELECT * FROM events ORDER BY event_date DESC, event_time_start LIMIT 3";
$slider_result = $conn->query($slider_sql);
$slider_events = [];
while ($row = $slider_result->fetch_assoc()) {
  $slider_events[] = $row;
}
while(count($slider_events) < 3) {
  $slider_events[] = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin - Manage Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
  body { font-family: Arial, sans-serif; background: #f8f9fa; margin:0; padding: 0; display: flex; min-height: 100vh; }
  table img {
    max-width: 100px;
    max-height: 80px;
    object-fit: cover;
    border-radius: 4px;
  }
  main.main {
    padding: 20px;
    flex: 1;
    min-height: 100vh;
    padding-top: 80px; /* Account for fixed navbar height */
  }
  .events-table {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  .header-bar {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  .header-bar input[type="search"] {
    max-width: 600px; width: 100%; padding: 10px 15px; font-size: 16px; border-radius: 50px; border: 1px solid #ddd;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    transition: border-color 0.3s, box-shadow 0.3s;
  }
  .header-bar input[type="search"]:focus {
    outline:none; border-color: #3b82f6; box-shadow: 0 0 8px rgba(59,130,246,0.5);
  }
  .event-slider {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 40px;
    margin-bottom: 40px;
    overflow: hidden;
    position: relative;
  }
  .event-card {
    flex: 0 0 30%;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background: #fff;
    text-align: center;
    color: #333;
    filter: brightness(70%) blur(2px);
    opacity: 0.7;
    cursor: default;
    transition: all 0.3s ease;
  }
  .event-card img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    transition: all 0.3s ease;
  }
  .event-card.center {
    flex: 0 0 40%;
    filter: brightness(100%) blur(0);
    opacity: 1;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 10;
  }
  .event-card.center img {
    height: 200px;
  }
  .event-card.side:hover {
    filter: brightness(75%) blur(1px);
    opacity: 0.85;
    transform: scale(0.9);
  }
  .event-card.center h3 {
    font-size: 1.6rem;
    margin-top: 10px;
  }
  .event-card.side h3 {
    font-size: 1.3rem;
    margin-top: 8px;
  }
  .details {
    padding: 15px;
  }
  .img-placeholder {
    height: 140px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f3f4f6;
    color: #9ca3af;
    font-size: 3rem;
  }
  .event-card.center .img-placeholder {
    height: 200px;
  }
  .slider-nav {
    position: absolute;
    top: 50%;
    font-size: 36px;
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    width: 48px;
    height: 48px;
    transform: translateY(-50%);
    display: flex;
    justify-content: center;
    align-items: center;
    user-select: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: background 0.3s;
  }
  .slider-nav:hover {
    background: #fff;
  }
  .slider-nav#sliderPrev {
    left: -60px;
  }
  .slider-nav#sliderNext {
    right: -60px;
  }
  .modal-content {
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
  }
  .modal-header {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    border-radius: 12px 12px 0 0;
  }
  .modal-body {
    padding: 24px;
  }
  .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
  }
  @media (min-width: 992px) {
      .main {
          margin-left: 280px !important;
      }
  }
</style>
</head>
<body>

<main class="main">
  <div class="header-bar">
    <form method="GET" action="">
      <input type="search" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>" aria-label="Search Events" />
    </form>
  </div>
  
  <h2>All Events</h2>
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul>
    <?php foreach($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  
  <div class="table-responsive">
    <table class="table events-table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>Photo</th>
          <th>Title</th>
          <th>Date</th>
          <th>Time</th>
          <th>Category</th>
          <th>Location</th>
          <th>Status</th>
          <th style="width:160px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while($event = $events_result->fetch_assoc()): ?>
        <tr>
          <td>
            <?php if(!empty($event['photo'])): ?>
            <img src="<?= htmlspecialchars($event['photo']) ?>" alt="Event photo" />
            <?php else: ?>
            <i class="bi bi-image" style="font-size:2.5rem;color:#ccc;"></i>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($event['title']) ?></td>
          <td><?= htmlspecialchars(date("d M Y", strtotime($event['event_date']))) ?></td>
          <td><?= htmlspecialchars($event['event_time_start'] ? date("H:i", strtotime($event['event_time_start'])) : '-') ?> - <?= htmlspecialchars($event['event_time_end'] ? date("H:i", strtotime($event['event_time_end'])) : '-') ?></td>
          <td><?= htmlspecialchars($event['category']) ?></td>
          <td><?= htmlspecialchars($event['location']) ?></td>
          <td><?= $event['is_posted'] ? '<span class="badge bg-success">Posted</span>' : '<span class="badge bg-secondary">Not Posted</span>' ?></td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEventModal" 
                data-id="<?= $event['event_id'] ?>"
                data-title="<?= htmlspecialchars($event['title'], ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($event['description'], ENT_QUOTES) ?>"
                data-date="<?= $event['event_date'] ?>"
                data-start="<?= $event['event_time_start'] ?>"
                data-end="<?= $event['event_time_end'] ?>"
                data-category="<?= htmlspecialchars($event['category'], ENT_QUOTES) ?>"
                data-location="<?= htmlspecialchars($event['location'], ENT_QUOTES) ?>"
                data-photo="<?= htmlspecialchars($event['photo'], ENT_QUOTES) ?>"
                title="Edit Event">
              <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_event.php?id=<?= $event['event_id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Event" onclick="return confirm('Are you sure?')">
              <i class="bi bi-trash"></i>
            </a>
            <?php if($event['is_posted']): ?>
            <a href="?toggle_post=<?= $event['event_id'] ?>" class="btn btn-sm btn-outline-warning" title="Unpublish Event" onclick="return confirm('Unpublish?')">
              <i class="bi bi-eye-slash"></i>
            </a>
            <?php else: ?>
            <a href="?toggle_post=<?= $event['event_id'] ?>" class="btn btn-sm btn-outline-success" title="Publish Event" onclick="return confirm('Publish?')">
              <i class="bi bi-eye"></i>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Slider with prev/next nav -->
  <div class="slider-wrapper" aria-label="Event slideshow">
    <button id="sliderPrev" class="slider-nav slider-nav-prev" aria-label="Previous Event">&#x276E;</button>
    <div class="event-slider" id="eventSlider" tabindex="0" role="listbox" aria-live="polite">
      <?php foreach($slider_events as $i => $event):
        $cls = $i===1 ? 'center' : 'side'; ?>
      <div class="event-card <?= $cls ?>" role="option" aria-selected="<?= $i === 1 ? 'true' : 'false' ?>">
        <?php if($event && !empty($event['photo'])): ?>
          <img src="<?= htmlspecialchars($event['photo']) ?>" alt="Event photo" />
        <?php else: ?>
          <div class="img-placeholder"><i class="bi bi-image"></i></div>
        <?php endif ?>
        <?php if($event): ?>
        <div class="details">
          <h3><?= $cls==='center' ? htmlspecialchars($event['title']) : '<strong>' . htmlspecialchars($event['title']) . '</strong>' ?></h3>
          <p>Date: <?= htmlspecialchars(date('d M Y', strtotime($event['event_date']))) ?></p>
          <p>Category: <?= htmlspecialchars($event['category']) ?></p>
          <p>Location: <?= htmlspecialchars($event['location']) ?></p>
        </div>
        <?php endif ?>
      </div>
      <?php endforeach; ?>
    </div>
    <button id="sliderNext" class="slider-nav slider-nav-next" aria-label="Next Event">&#x276F;</button>
  </div>

</main>

<!-- Edit Modal (same modal code as before) -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="" enctype="multipart/form-data" id="editEventForm">
        <input type="hidden" name="edit_event" value="1" />
        <input type="hidden" name="event_id" id="editEventId" />
        <div class="modal-header">
          <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-3">
            <img src="" alt="Event photo preview" id="editPhotoPreview" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 2px solid #3b82f6; object-fit: cover; display:none;">
          </div>
          <div class="mb-3">
            <label for="editEventTitle" class="form-label">Event Title</label>
            <input type="text" id="editEventTitle" name="title" class="form-control" required />
          </div>
          <div class="mb-3">
            <label for="editEventDescription" class="form-label">Description</label>
            <textarea id="editEventDescription" name="description" class="form-control" rows="4"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="editEventDate" class="form-label">Date</label>
              <input type="date" id="editEventDate" name="event_date" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label for="editTimeStart" class="form-label">Start Time</label>
              <input type="time" id="editTimeStart" name="event_time_start" class="form-control" />
            </div>
            <div class="col-md-4">
              <label for="editTimeEnd" class="form-label">End Time</label>
              <input type="time" id="editTimeEnd" name="event_time_end" class="form-control" />
            </div>
          </div>
          <div class="mb-3">
            <label for="editEventCategory" class="form-label">Category</label>
            <input type="text" id="editEventCategory" name="category" class="form-control" />
          </div>
          <div class="mb-3">
            <label for="editEventLocation" class="form-label">Location</label>
            <input type="text" id="editEventLocation" name="location" class="form-control" />
          </div>
          <div class="mb-3">
            <label for="editEventPhoto" class="form-label">Change Photo</label>
            <input type="file" id="editEventPhoto" name="photo" class="form-control" accept="image/*" />
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  var editModal = document.getElementById('editEventModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('editEventId').value = button.getAttribute('data-id');
    document.getElementById('editEventTitle').value = button.getAttribute('data-title');
    document.getElementById('editEventDescription').value = button.getAttribute('data-description');
    document.getElementById('editEventDate').value = button.getAttribute('data-date');
    document.getElementById('editTimeStart').value = button.getAttribute('data-start');
    document.getElementById('editTimeEnd').value = button.getAttribute('data-end');
    document.getElementById('editEventCategory').value = button.getAttribute('data-category');
    document.getElementById('editEventLocation').value = button.getAttribute('data-location');

    var photo = button.getAttribute('data-photo');
    var preview = document.getElementById('editPhotoPreview');
    if (photo && photo !== '') {
      preview.src = photo;
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
  });

  document.getElementById('editEventPhoto').addEventListener('change', function () {
    var preview = document.getElementById('editPhotoPreview');
    if (this.files && this.files[0]) {
      preview.src = URL.createObjectURL(this.files[0]);
      preview.style.display = 'block';
    }
  });

  const slider = document.getElementById('eventSlider');
  const slides = slider.children;
  const totalSlides = slides.length;
  let current = 1;

  function updateSlides() {
    for (let i=0; i<totalSlides; i++) {
      slides[i].classList.remove('center', 'side');
      if (i === current) slides[i].classList.add('center');
      else slides[i].classList.add('side');
    }
  }

  document.getElementById('sliderPrev').addEventListener('click', () => {
    current = (current - 1 + totalSlides) % totalSlides;
    updateSlides();
  });

  document.getElementById('sliderNext').addEventListener('click', () => {
    current = (current + 1) % totalSlides;
    updateSlides();
  });

  updateSlides();
</script>

</body>
</html>