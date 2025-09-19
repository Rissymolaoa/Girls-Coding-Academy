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
$db   = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Flags for SweetAlert
$course_added = false;
$course_updated = false;
$update_error = false;
$course_deleted = false;

// Handle add course
if (isset($_POST['add_course'])) {
    $stmt = $conn->prepare("INSERT INTO courses (title, courseName, description, category, level, start_date, end_date, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssssssss",
        $_POST['title'],
        $_POST['courseName'],
        $_POST['description'],
        $_POST['category'],
        $_POST['level'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['price'],
        $_POST['status']
    );
    $stmt->execute();
    $stmt->close();
    $course_added = true;
}

// Handle update course
if (isset($_POST['update_course'])) {
    if (!empty($_POST['title']) && !empty($_POST['courseName'])) {
        $stmt = $conn->prepare("UPDATE courses SET title=?, courseName=?, description=?, category=?, level=?, start_date=?, end_date=?, price=?, status=? WHERE course_id=?");
        $stmt->bind_param(
            "sssssssssi",
            $_POST['title'],
            $_POST['courseName'],
            $_POST['description'],
            $_POST['category'],
            $_POST['level'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['price'],
            $_POST['status'],
            $_POST['course_id']
        );
        $stmt->execute();
        $stmt->close();
        $course_updated = true;
    } else {
        $update_error = true;
    }
}

// Handle delete course
if (isset($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    $conn->query("DELETE FROM courses WHERE course_id=$course_id");
    $course_deleted = true;
}

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_result = $conn->query("SELECT COUNT(*) as total FROM courses");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

$result = $conn->query("SELECT * FROM courses ORDER BY course_id DESC LIMIT $limit OFFSET $offset");

// Statistics queries
$active_courses = $conn->query("SELECT COUNT(*) AS count FROM courses WHERE status='active'")->fetch_assoc()['count'];
$inactive_courses = $conn->query("SELECT COUNT(*) AS count FROM courses WHERE status='inactive'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as total FROM course_enrollments")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Courses | Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f5f6fa; color: #2c3e50; }


header {
    background: linear-gradient(90deg, #7b2cbf, #5a189a);
    color: white; padding: 20px 30px; text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
header h1 { font-size: 24px; }

.container { display: flex; min-height: calc(100vh - 70px); gap: 20px; }

.sidebar {
    width: 220px; background: #34495e; padding: 20px;
    display: flex; flex-direction: column; align-items: center;
        min-height: 100vh;

}

 
.admin-pic {
    width: 100px; height: 100px; border-radius: 50%;
    margin-bottom: 15px; border: 3px solid #2cbf64ff; object-fit: cover;
}
.sidebar h3 { color: white; margin-bottom: 20px; font-size: 16px; text-align:center;}
.sidebar a {
    width: 100%; color: white; text-decoration: none;
    padding: 10px 15px; margin: 5px 0; border-radius: 5px; font-size: 14px;
}
.sidebar a:hover, .sidebar a.active { background: #1abc9c; }

/* Content and stats wrapper */
.content-wrapper { display: flex; gap: 20px; }

/* Main content */
.content { flex: 3; padding: 10px 0; }

/* Stats panel */
.stats-panel {
    flex: 1; background: white; padding: 15px; border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    height: fit-content;
}
    header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }
    
    h1{margin:0;
    font-size:20px;
    font-weight:600}
/* Forms */
form {
    background: white; padding: 20px; margin-bottom: 20px;
    border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
}
form h3 { grid-column: span 2; }
input, textarea, select { padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%; }
textarea { grid-column: span 2; }
button { grid-column: span 2; background: #7b2cbf; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; }
button:hover { background: #5a189a; }

/* Table styling */
table {
    width: 100%; border-collapse: collapse; border-radius: 8px; overflow: hidden; background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px;
}
th, td { padding: 12px; text-align: center; border-bottom: 1px solid #732d91; font-size: 14px; }
th { background: linear-gradient(90deg, #7b2cbf, #5a189a); color: white; }

/* Action buttons */
.btn { padding: 6px 10px; border-radius: 5px; font-size: 13px; margin: 0 2px; cursor: pointer; border: none; }
.btn-edit { background: #3498db; color: white; }
.btn-edit:hover { background: #2980b9; }
.btn-delete { background: #e74c3c; color: white; }
.btn-delete:hover { background: #c0392b; }

/* Pagination */
.pagination { margin-top:15px; text-align:center; }
.pagination a, .pagination span { padding:6px 14px; margin:0 5px; background:#7b2cbf; color:white; border-radius:4px; text-decoration:none; }
.pagination span.disabled { background:#ccc; color:#666; }
h1{margin:0;
    font-size:20px;
    font-weight:600}
</style>
</head>
<body>
<header><h1>Girls Coding Academy - Admin Dashboard</h1></header>
<div class="container">
    <div class="sidebar">
        <img src="admin.jpg" alt="Admin Picture" class="admin-pic">
    <h4 class="text-center mb-4">Administration</h4>
    <a href="admin_dashboard.php" class="active"><i class="bi bi-house-door-fill"></i> Dashboard</a>
    <a href="approve_users.php"><i class="bi bi-person-check-fill"></i> Approve Users</a>
    <a href="manage_courses.php"><i class="bi bi-journal-bookmark-fill"></i> Manage Courses</a>
    <a href="manage_students.php"><i class="bi bi-people-fill"></i> Manage Students</a>
    <a href="manage_teachers.php"><i class="bi bi-person-badge-fill"></i> Manage Teachers</a>
    <a href="parents_summary.php"><i class="bi bi-people"></i> Parent Summary</a>
    <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Manage Parents</a>
    <a href="assign_parent_student.php"><i class="bi bi-person-plus-fill"></i> Assign Students</a>
    <a href="course_assignment.php"><i class="bi bi-book-half"></i> Assign Courses</a>
    <a href="add_batch.php"><i class="bi bi-plus-circle-fill"></i> Add Batch</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <div class="content-wrapper">
        <div class="content">
            <h2>Manage Courses</h2>

            <!-- Add Course Form -->
            <form method="POST">
                <div class="card mb-4 shadow-sm">
  <div class="card-header bg-gradient text-white" style="background: linear-gradient(90deg, #7b2cbf, #5a189a);">
    <h5 class="mb-0">Add New Course</h5>
  </div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Course Name</label>
        <input type="text" name="courseName" class="form-control" required>
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" required></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Level</label>
        <input type="text" name="level" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" name="price" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="col-12 text-end">
        <button type="submit" name="add_course" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> Add Course
        </button>
      </div>
    </form>
  </div>
</div>
            </form>

            <!-- Search -->
            <input type="text" id="searchInput" placeholder="Search courses..." style="margin-bottom:15px;padding:8px;width:100%;">

            <!-- Existing Courses -->
            <h3>Existing Courses</h3>
            <table id="coursesTable">
                <thead>
                    <tr><th>ID</th><th>Title</th><th>Course Name</th><th>Category</th><th>Level</th><th>Start</th><th>End</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['course_id'] ?></td>
                    <td><?= $row['title'] ?></td>
                    <td><?= $row['courseName'] ?></td>
                    <td><?= $row['category'] ?></td>
                    <td><?= $row['level'] ?></td>
                    <td><?= $row['start_date'] ?></td>
                    <td><?= $row['end_date'] ?></td>
                    <td>$<?= $row['price'] ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                    <td>
                        <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($row) ?>)'>✏</button>
                        <button class="btn btn-delete" onclick="confirmDelete(<?= $row['course_id'] ?>)">🗑</button>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-panel">
            <h3>Course Statistics</h3>
            <canvas id="barChart" height="150"></canvas>
            <canvas id="pieChart" height="150" style="margin-top:20px;"></canvas>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
    background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
    <div style="background:#fff; padding:20px; border-radius:8px; width:500px; max-width:90%;">
        <h3>Edit Course</h3>
        <form method="POST">
            <input type="hidden" name="course_id" id="edit_course_id">
            <input type="text" name="title" id="edit_title" placeholder="Title" required>
            <input type="text" name="courseName" id="edit_courseName" placeholder="Course Name" required>
            <textarea name="description" id="edit_description" placeholder="Description" required></textarea>
            <input type="text" name="category" id="edit_category" placeholder="Category" required>
            <input type="text" name="level" id="edit_level" placeholder="Level" required>
            <label>Start Date: <input type="date" name="start_date" id="edit_start_date" required></label>
            <label>End Date: <input type="date" name="end_date" id="edit_end_date" required></label>
            <input type="number" step="0.01" name="price" id="edit_price" placeholder="Price" required>
            <select name="status" id="edit_status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="closeEditModal()" style="background:#ccc; padding:8px 12px; border:none; border-radius:4px;">Cancel</button>
                <button type="submit" name="update_course" style="background:#7b2cbf; color:white; padding:8px 12px; border:none; border-radius:4px;">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
// Search courses
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#coursesTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});

// SweetAlert delete confirmation
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the course permanently!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete=' + id;
        }
    })
}

function openEditModal(course) {
    document.getElementById('edit_course_id').value = course.course_id;
    document.getElementById('edit_title').value = course.title;
    document.getElementById('edit_courseName').value = course.courseName;
    document.getElementById('edit_description').value = course.description;
    document.getElementById('edit_category').value = course.category;
    document.getElementById('edit_level').value = course.level;
    document.getElementById('edit_start_date').value = course.start_date;
    document.getElementById('edit_end_date').value = course.end_date;
    document.getElementById('edit_price').value = course.price;
    document.getElementById('edit_status').value = course.status;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Charts
const ctxBar = document.getElementById('barChart').getContext('2d');
const barChart = new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: ['Active Courses', 'Inactive Courses', 'Enrollments'],
        datasets: [{
            label: 'Courses Stats',
            data: [<?= $active_courses ?>, <?= $inactive_courses ?>, <?= $total_enrollments ?: 0 ?>],
            backgroundColor: ['#27ae60','#e74c3c','#3498db']
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

const ctxPie = document.getElementById('pieChart').getContext('2d');
const pieChart = new Chart(ctxPie, {
    type: 'pie',
    data: {
        labels: ['Active Courses', 'Inactive Courses'],
        datasets: [{
            data: [<?= $active_courses ?>, <?= $inactive_courses ?>],
            backgroundColor: ['#27ae60','#e74c3c']
        }]
    },
    options: { responsive: true }
});
</script>

<?php if ($course_added): ?>
<script>Swal.fire('Success','Course added successfully!','success');</script>
<?php endif; ?>
<?php if ($course_updated): ?>
<script>Swal.fire('Updated','Course updated successfully!','success');</script>
<?php endif; ?>
<?php if ($update_error): ?>
<script>Swal.fire('Error','Course title and name are required!','error');</script>
<?php endif; ?>
<?php if ($course_deleted): ?>
<script>Swal.fire('Deleted','Course deleted successfully!','success');</script>
<?php endif; ?>

</body>
</html>
