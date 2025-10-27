<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

$search_query = trim($_GET['q'] ?? '');
$results = [];

// If no search query, redirect or show message
if (empty($search_query)) {
    header("Location: dashboard.php");
    exit();
}

// Perform search across multiple tables using UNION
$sql = "
    SELECT 'User' as type, user_id as id, CONCAT(firstName, ' ', lastName) as name, email as detail, username as extra, created_at as timestamp
    FROM users 
    WHERE firstName LIKE ? OR lastName LIKE ? OR email LIKE ? OR username LIKE ?
    
    UNION
    
    SELECT 'Course' as type, course_id as id, title as name, courseName as detail, description as extra, start_date as timestamp
    FROM courses 
    WHERE title LIKE ? OR courseName LIKE ? OR description LIKE ?
    
    UNION
    
    SELECT 'Batch' as type, batch_id as id, batch_code as name, CONCAT('Course ID: ', course_id) as detail, status as extra, start_date as timestamp
    FROM batches 
    WHERE batch_code LIKE ? OR status LIKE ?
    
    UNION
    
    SELECT 'Student' as type, s.student_id as id, CONCAT(u.firstName, ' ', u.lastName) as name, u.email as detail, 'Student' as extra, u.created_at as timestamp
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ?
    
    UNION
    
    SELECT 'Teacher' as type, t.teacher_id as id, CONCAT(u.firstName, ' ', u.lastName) as name, u.email as detail, t.subject_speciality as extra, u.created_at as timestamp
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR t.subject_speciality LIKE ?
    
    UNION
    
    SELECT 'Activity' as type, activity_id as id, title as name, description as detail, batch_id as extra, created_at as timestamp
    FROM activities 
    WHERE title LIKE ? OR description LIKE ?
    
    UNION
    
    SELECT 'Event' as type, event_id as id, title as name, description as detail, location as extra, event_date as timestamp
    FROM events 
    WHERE title LIKE ? OR description LIKE ? OR location LIKE ?
    
    ORDER BY timestamp DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
$like_param = "%$search_query%";
$params = array_fill(0, 21, $like_param); // Corrected count: 4+3+2+3+4+2+3=21
$stmt->bind_param(str_repeat('s', 21), ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Search Results - <?= htmlspecialchars($search_query) ?> - Girls Coding Academy</title>
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

  .search-header {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin: 2rem auto;
    max-width: 800px;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-align: center;
  }

  .search-header h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }

  .results-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
  }

  .results-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-md);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
  }

  .results-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
  }

  .type-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    color: white;
  }

  .type-user { background: #667eea; }
  .type-course { background: #764ba2; }
  .type-batch { background: #f093fb; }
  .type-student { background: #4facfe; }
  .type-teacher { background: #43e97b; }
  .type-activity { background: #fa709a; }
  .type-event { background: #a8edea; }

  .no-results {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
  }

  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 1rem;
  }

  .back-link:hover {
    color: #764ba2;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>


<div class="search-header">
  <a href="admin_dashboard.php" class="back-link">
    <i class="bi bi-arrow-left"></i>
    Back to Dashboard
  </a>
  <h1>Search Results for "<?= htmlspecialchars($search_query) ?>"</h1>
  <p class="text-muted">Found <?= count($results) ?> results across the system.</p>
</div>

<div class="results-section">
  <?php if (empty($results)): ?>
    <div class="no-results">
      <i class="bi bi-search" style="font-size: 4rem; color: #d1d5db; margin-bottom: 1rem;"></i>
      <h3>No results found</h3>
      <p>Try adjusting your search terms or check spelling. You can search students, courses, activities, events, and more.</p>
      <a href="admin_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    </div>
  <?php else: ?>
    <?php foreach ($results as $result): ?>
      <div class="results-card">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5><?= htmlspecialchars($result['name']) ?></h5>
          <span class="type-badge type-<?= strtolower($result['type']) ?>"><?= htmlspecialchars($result['type']) ?></span>
        </div>
        <p class="text-muted mb-1"><?= htmlspecialchars($result['detail']) ?></p>
        <?php if (!empty($result['extra'])): ?>
          <small class="text-muted">Extra: <?= htmlspecialchars($result['extra']) ?></small>
        <?php endif; ?>
        <small class="text-muted d-block mt-2">
          <i class="bi bi-clock-history"></i> <?= date('M j, Y', strtotime($result['timestamp'])) ?>
        </small>
        <!-- Action links based on type -->
        <div class="mt-2">
          <?php if ($result['type'] === 'User'): ?>
            <a href="user_profile.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
          <?php elseif ($result['type'] === 'Course'): ?>
            <a href="manage_courses.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Course</a>
          <?php elseif ($result['type'] === 'Batch'): ?>
            <a href="add_batch.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Batch</a>
          <?php elseif ($result['type'] === 'Student'): ?>
            <a href="student_profile.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Student</a>
          <?php elseif ($result['type'] === 'Teacher'): ?>
            <a href="teacher_profile.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Teacher</a>
          <?php elseif ($result['type'] === 'Activity'): ?>
            <a href="activity_details.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Activity</a>
          <?php elseif ($result['type'] === 'Event'): ?>
            <a href="event_details.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary">View Event</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>