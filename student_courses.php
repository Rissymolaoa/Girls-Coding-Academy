<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get student info
$user_id = $_SESSION['user_id'];
$stmt_student = $conn->prepare("
    SELECT s.student_id, s.photo, u.username, u.email, u.role
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$studentInfo = $result_student->fetch_assoc();
$student_id = $studentInfo['student_id'];

// Handle filter and search parameters from GET
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, active, completed
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare SQL with filters
$sql = "
SELECT c.course_id, c.courseName, c.description, c.image_path,
       CONCAT(u.firstName, ' ', u.lastName) AS teacherName,
       b.batch_id, b.batch_code AS batchCode,
       IFNULL(ce.status, 'active') AS enrollment_status,
       CASE WHEN f.student_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
FROM course_enrollments ce
JOIN batches b ON ce.batch_id = b.batch_id
JOIN courses c ON b.course_id = c.course_id
LEFT JOIN course_assignments ca ON ca.batch_id = b.batch_id
LEFT JOIN teachers t ON ca.teacher_id = t.teacher_id
LEFT JOIN users u ON t.user_id = u.user_id
LEFT JOIN course_favorites f ON f.batch_id = b.batch_id AND f.student_id = ?
WHERE ce.student_id = ?
";

$params = [$student_id, $student_id];
$types = "ii";

if ($status_filter === 'active') {
    $sql .= " AND ce.status = 'active' ";
} elseif ($status_filter === 'completed') {
    $sql .= " AND ce.status = 'completed' ";
}

if ($search_term !== '') {
    $sql .= " AND c.courseName LIKE ? ";
    $search_term_wild = "%$search_term%";
    $params[] = $search_term_wild;
    $types .= "s";
}

$sql .= " ORDER BY is_favorite DESC, c.courseName ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['image_path'])) $row['image_path'] = 'uploads/courses/default.jpg';
    if (empty($row['teacherName'])) $row['teacherName'] = 'TBA';
    $courses[] = $row;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Courses - Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }
    .container-flex {
        display: flex;
        height: 100vh;
    }
    .content {
        flex: 1;
        padding: 30px 40px;
        margin-left: 280px;
        overflow-y: auto;
        height: 100vh;
    }
    h2 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
    .filter-bar {
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        background: rgba(255,255,255,0.8);
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .view-toggle {
        text-align: right;
    }
    .view-toggle button {
        background: rgba(52, 73, 94, 0.1);
        border: 2px solid #34495e;
        color: #34495e;
        font-size: 1.4rem;
        cursor: pointer;
        margin-left: 12px;
        padding: 8px 12px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }
    .view-toggle button:hover {
        background: #34495e;
        color: white;
        transform: scale(1.1);
    }
    .view-toggle button.active {
        background: #3498db;
        color: white;
        border-color: #2980b9;
    }
    .grid-view {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }
    .list-view {
        display: block;
    }
    .course {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 1px solid #eee;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .course:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .course img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 15px;
        flex-shrink: 0;
    }
    .course h3 {
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 1.25rem;
    }
    .course p {
        font-size: 14px;
        margin: 3px 0;
        color: #7f8c8d;
    }
    .list-view .course {
        flex-direction: row;
        text-align: left;
        height: 120px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .list-view .course img {
        width: 180px;
        height: 100%;
        margin-right: 20px;
        margin-bottom: 0;
        border-radius: 8px;
    }
    .list-view .course-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .favorite-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(255,255,255,0.9);
        border: none;
        border-radius: 50%;
        padding: 5px 8px;
        cursor: pointer;
        font-size: 1.2rem;
        color: #888;
        transition: color 0.3s ease;
        z-index: 2;
    }
    .favorite-btn.favorited {
        color: #f39c12;
    }
    .no-courses {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
        padding: 40px;
        background: white;
        border-radius: 10px;
        border: 1px dashed #bdc3c7;
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
  <!--  <?php include("student_navigation.php"); ?> -->
  

    <main class="content" role="main">
        <h2><i class="bi bi-journal-bookmark"></i> My Courses</h2>

        <form id="filterForm" method="GET" class="filter-bar" aria-label="Filter and search courses">
            <div>
                <label for="statusFilter">Status:</label>
                <select id="statusFilter" name="status" onchange="document.getElementById('filterForm').submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div>
                <label for="searchInput" class="visually-hidden">Search courses</label>
                <input id="searchInput" type="search" name="search" placeholder="Search courses..." value="<?= htmlspecialchars($search_term) ?>" />
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </div>
        </form>

        <div class="view-toggle" aria-label="Toggle view">
            <button id="gridViewBtn" class="active" aria-pressed="true" title="Grid View"><i class="bi bi-grid-3x3-gap-fill"></i></button>
            <button id="listViewBtn" aria-pressed="false" title="List View"><i class="bi bi-list-ul"></i></button>
        </div>

        <div id="coursesContainer" class="grid-view" role="list">
            <?php if (empty($courses)): ?>
                <div class="no-courses">No courses found. <a href="enroll.php">Enroll now</a> to get started.</div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <a href="course_dashboard.php?course_id=<?= htmlspecialchars($course['course_id']) ?>&batch_id=<?= htmlspecialchars($course['batch_id']) ?>" style="text-decoration:none; color:inherit;" role="listitem">
                        <div class="course" data-course-id="<?= (int)$course['course_id'] ?>">
                            <button class="favorite-btn <?= $course['is_favorite'] ? 'favorited' : '' ?>" aria-label="Toggle favorite" title="Toggle favorite">&#9733;</button>
                            <img src="<?= htmlspecialchars($course['image_path']) ?>" alt="<?= htmlspecialchars($course['courseName']) ?>">
                            <div class="course-details">
                                <h3><?= htmlspecialchars($course['courseName']) ?></h3>
                                <p><i class="bi bi-person-workspace"></i> Teacher: <?= htmlspecialchars($course['teacherName']) ?></p>
                                <p><i class="bi bi-123"></i> Batch: <?= htmlspecialchars($course['batchCode']) ?></p>
                                <p><i class="bi bi-check2-circle"></i> Status: <?= htmlspecialchars($course['enrollment_status']) ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
(function(){
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const container = document.getElementById('coursesContainer');

    function setGridView(){
        container.classList.add('grid-view');
        container.classList.remove('list-view');
        gridBtn.classList.add('active');
        gridBtn.setAttribute('aria-pressed', 'true');
        listBtn.classList.remove('active');
        listBtn.setAttribute('aria-pressed', 'false');
    }
    function setListView(){
        container.classList.remove('grid-view');
        container.classList.add('list-view');
        gridBtn.classList.remove('active');
        gridBtn.setAttribute('aria-pressed', 'false');
        listBtn.classList.add('active');
        listBtn.setAttribute('aria-pressed', 'true');
    }

    gridBtn.addEventListener('click', setGridView);
    listBtn.addEventListener('click', setListView);

    // Favorite toggle handling
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const courseDiv = this.closest('.course');
            const courseId = courseDiv.dataset.courseId;
            const isFavorited = this.classList.contains('favorited');
            this.classList.toggle('favorited');
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ course_id: courseId, action: isFavorited ? 'remove' : 'add' })
            }).then(response => response.json())
              .then(data => {
                  if(!data.success){
                      alert('Error updating favorite status.');
                      this.classList.toggle('favorited');
                  } else {
                      window.location.reload();
                  }
              }).catch(() => {
                  alert('Network error updating favorite status.');
                  this.classList.toggle('favorited');
              });
        });
    });
})();
</script>

</body>
</html>