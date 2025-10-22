<?php
// Always start the session at the very top
session_start();

// Debugging aid (uncomment if you want to see what’s inside the session)
// echo "<pre>"; print_r($_SESSION); echo "</pre>"; exit();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Database connection
include("db.php");

// Include navigations
include("top_navigation.php");
include("admin_navigation.php");

// Get posted events
$sql = "SELECT * FROM events WHERE is_posted = 1 ORDER BY event_date DESC, event_time_start";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Posted Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body { font-family: Arial, sans-serif; background: #f8f9fa; margin:0; padding: 0; display: flex; min-height: 100vh; }
    .card-img-top { height: 150px; object-fit: cover; }
    .events-table th, .events-table td { vertical-align: middle; }
    a.back { margin-bottom: 20px; display: inline-block; }
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
    @media (min-width: 992px) {
        .main {
            margin-left: 300px !important;
        }
    }
  </style>
</head>
<body>
<main class="main">
    <h2>Posted Events</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table events-table table-striped">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($event['photo'])): ?>
                                    <img src="<?= htmlspecialchars($event['photo']) ?>" alt="Event photo" width="100" height="80" />
                                <?php else: ?>
                                    <i class="bi bi-image" style="font-size: 3rem; color:#ccc;"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($event['title']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($event['event_date']))) ?></td>
                            <td>
                              <?= $event['event_time_start'] ? htmlspecialchars(date('H:i', strtotime($event['event_time_start']))) : '-' ?>
                              -
                              <?= $event['event_time_end'] ? htmlspecialchars(date('H:i', strtotime($event['event_time_end']))) : '-' ?>
                            </td>
                            <td><?= htmlspecialchars($event['category']) ?></td>
                            <td><?= htmlspecialchars($event['location']) ?></td>
                            <td><?= nl2br(htmlspecialchars($event['description'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No posted events found.</p>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>