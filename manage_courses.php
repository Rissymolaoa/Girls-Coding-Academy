<?php
session_start();

// Only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Flags for SweetAlert
$course_added = false;
$course_updated = false;
$update_error = false;
$course_deleted = false;
$image_error = false;

// Ensure upload directory exists
$upload_dir = 'Uploads/courses/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle add course
if (isset($_POST['add_course'])) {
    $title = $_POST['title'];
    $courseName = $_POST['courseName'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $level = $_POST['level'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    $image_path = 'Uploads/courses/course_default.jpg'; // Default image

    // Handle image upload
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_type = $_FILES['course_image']['type'];
        $file_size = $_FILES['course_image']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $ext = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
            $image_path = $upload_dir . 'course_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['course_image']['tmp_name'], $image_path)) {
                $image_error = true;
            }
        } else {
            $image_error = true;
        }
    }

    if (!$image_error) {
        $stmt = $conn->prepare("INSERT INTO courses (title, courseName, description, category, level, start_date, end_date, price, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssssss",
            $title,
            $courseName,
            $description,
            $category,
            $level,
            $start_date,
            $end_date,
            $price,
            $status,
            $image_path
        );
        $stmt->execute();
        $stmt->close();
        $course_added = true;
    }
}

// Handle update course
if (isset($_POST['update_course'])) {
    if (!empty($_POST['title']) && !empty($_POST['courseName'])) {
        $title = $_POST['title'];
        $courseName = $_POST['courseName'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $level = $_POST['level'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $price = $_POST['price'];
        $status = $_POST['status'];
        $course_id = $_POST['course_id'];
        $image_path = $_POST['existing_image_path']; // Keep existing image by default

        // Handle image upload
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_type = $_FILES['course_image']['type'];
            $file_size = $_FILES['course_image']['size'];

            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $ext = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
                $image_path = $upload_dir . 'course_' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['course_image']['tmp_name'], $image_path)) {
                    $image_error = true;
                }
                // Delete old image if not default
                if ($_POST['existing_image_path'] != 'Uploads/courses/course_default.jpg') {
                    @unlink($_POST['existing_image_path']);
                }
            } else {
                $image_error = true;
            }
        }

        if (!$image_error) {
            $stmt = $conn->prepare("UPDATE courses SET title=?, courseName=?, description=?, category=?, level=?, start_date=?, end_date=?, price=?, status=?, image_path=? WHERE course_id=?");
            $stmt->bind_param(
                "ssssssssssi",
                $title,
                $courseName,
                $description,
                $category,
                $level,
                $start_date,
                $end_date,
                $price,
                $status,
                $image_path,
                $course_id
            );
            $stmt->execute();
            $stmt->close();
            $course_updated = true;
        } else {
            $update_error = true;
        }
    } else {
        $update_error = true;
    }
}

// Handle delete course
if (isset($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    // Get image path to delete
    $stmt = $conn->prepare("SELECT image_path FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['image_path'] != 'Uploads/courses/course_default.jpg') {
        @unlink($row['image_path']);
    }
    $stmt->close();

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
<html lang="en">
<head>
    <title>Manage Courses | Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
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

        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 400px;
        }

        .search-form .form-control {
            flex: 1;
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

        .form-row img {
            max-width: 100px;
            border-radius: 8px;
            margin-top: 0.5rem;
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

        .table img {
            max-width: 50px;
            border-radius: 4px;
        }

        .btn-edit {
            background: var(--info-gradient);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-right: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-edit:hover {
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--danger-gradient);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .btn-delete:hover {
            transform: translateY(-1px);
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

        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stats-section h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .chart-container {
            position: relative;
            height: 200px;
            margin-bottom: 1rem;
        }

        footer {
            background: rgba(31, 41, 55, 0.8);
            color: #fff;
            text-align: center;
            padding: 1.5rem;
            margin-top: 2rem;
            border-radius: 16px 16px 0 0;
        }

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

        @media (max-width: 768px) {
            .top-row {
                flex-direction: column;
                align-items: stretch;
            }
            .search-form {
                max-width: none;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .form-row textarea {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
    <main class="main">
        <div class="top-row">
            <h2>Manage Courses</h2>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="section-card">
                    <h3>Add New Course</h3>
                    <form method="POST" class="form-row" enctype="multipart/form-data">
                        <input type="text" name="title" placeholder="Title" class="form-control" required>
                        <input type="text" name="courseName" placeholder="Course Name" class="form-control" required>
                        <textarea name="description" placeholder="Description" class="form-control" required></textarea>
                        <input type="text" name="category" placeholder="Category (e.g., Construction Coding)" class="form-control" required>
                        <input type="text" name="level" placeholder="Level (e.g., Beginner)" class="form-control" required>
                        <input type="date" name="start_date" class="form-control" required>
                        <input type="date" name="end_date" class="form-control" required>
                        <input type="number" step="0.01" name="price" placeholder="Price" class="form-control" required>
                        <select name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div>
                            <label for="course_image">Course Image (JPG/PNG, max 5MB)</label>
                            <input type="file" name="course_image" id="course_image" class="form-control" accept="image/jpeg,image/png">
                        </div>
                        <button type="submit" name="add_course" class="btn btn-primary">Add Course</button>
                    </form>
                </div>

                <div class="section-card">
                    <input type="text" id="searchInput" placeholder="Search courses..." class="form-control mb-3">
                    <h3>Existing Courses</h3>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="coursesTable">
                            <thead>
                                <tr><th>ID</th><th>Image</th><th>Title</th><th>Course Name</th><th>Category</th><th>Level</th><th>Start</th><th>End</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= $row['course_id'] ?></td>
                                    <td><img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Course Image"></td>
                                    <td><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars($row['courseName']) ?></td>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td><?= htmlspecialchars($row['level']) ?></td>
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
                    </div>

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
            </div>

            <div class="col-lg-4">
                <div class="stats-section">
                    <h3>Course Statistics</h3>
                    <div class="chart-container">
                        <canvas id="barChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                <h5 class="modal-title">Edit Course</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeEditModal()"></button>
            </div>
            <form method="POST" class="modal-body form-row" enctype="multipart/form-data">
                <input type="hidden" name="course_id" id="edit_course_id">
                <input type="hidden" name="existing_image_path" id="edit_image_path">
                <input type="text" name="title" id="edit_title" placeholder="Title" class="form-control" required>
                <input type="text" name="courseName" id="edit_courseName" placeholder="Course Name" class="form-control" required>
                <textarea name="description" id="edit_description" placeholder="Description" class="form-control" required></textarea>
                <input type="text" name="category" id="edit_category" placeholder="Category" class="form-control" required>
                <input type="text" name="level" id="edit_level" placeholder="Level" class="form-control" required>
                <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                <input type="number" step="0.01" name="price" id="edit_price" placeholder="Price" class="form-control" required>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <div>
                    <label for="edit_course_image">Course Image (JPG/PNG, max 5MB)</label>
                    <img id="edit_image_preview" src="" alt="Current Image" style="display: none;">
                    <input type="file" name="course_image" id="edit_course_image" class="form-control" accept="image/jpeg,image/png">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_course" class="btn btn-primary">Update</button>
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
    });
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
    document.getElementById('edit_image_path').value = course.image_path;
    document.getElementById('edit_image_preview').src = course.image_path;
    document.getElementById('edit_image_preview').style.display = 'block';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function closeEditModal() {
    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
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
<script>Swal.fire('Error','Course title, name, or image upload failed!','error');</script>
<?php endif; ?>
<?php if ($course_deleted): ?>
<script>Swal.fire('Deleted','Course deleted successfully!','success');</script>
<?php endif; ?>
<?php if ($image_error): ?>
<script>Swal.fire('Error','Invalid image file or size exceeds 5MB!','error');</script>
<?php endif; ?>
</body>
</html>