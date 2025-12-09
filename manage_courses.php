<?php
session_start();

// Only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// DB connection
$host = "localhost:3307";
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

   // $conn->query("DELETE FROM courses WHERE course_id=$course_id");
    // NEW (soft delete - moves to recycle bin)
    $conn->query("UPDATE courses SET deleted_at = NOW() and status = 'deleted' WHERE course_id = $course_id");

    $course_deleted = true;
}

$limit = 10;
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .course-card {
            transition: all 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }
        .course-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .main-content {
            margin-left: 220px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'top_navigation.php'; ?>
    <?php include 'admin_navigation.php'; ?>

    <div class="main-content">
        <main class="p-6" style="padding-top: 80px;">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Manage Courses</h2>
                <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Add New Course
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Active Courses</p>
                            <p class="text-3xl font-bold mt-2"><?= $active_courses ?></p>
                        </div>
                        <i class="fas fa-check-circle text-5xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Inactive Courses</p>
                            <p class="text-3xl font-bold mt-2"><?= $inactive_courses ?></p>
                        </div>
                        <i class="fas fa-times-circle text-5xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Total Enrollments</p>
                            <p class="text-3xl font-bold mt-2"><?= $total_enrollments ?></p>
                        </div>
                        <i class="fas fa-users text-5xl opacity-30"></i>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search courses by name, category, or level..." 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <!-- Courses Grid -->
            <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($row = $result->fetch_assoc()) { ?>
                <div class="course-card bg-white rounded-lg shadow-md overflow-hidden">
                    <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Course Image" class="course-image">
                    <div class="p-5">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-xl font-bold text-gray-800 line-clamp-2"><?= htmlspecialchars($row['courseName']) ?></h3>
                            <span class="<?= $row['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs font-semibold px-3 py-1 rounded-full">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2"><i class="fas fa-bookmark mr-2 text-blue-500"></i><?= htmlspecialchars($row['category']) ?></p>
                        <p class="text-sm text-gray-600 mb-2"><i class="fas fa-signal mr-2 text-purple-500"></i><?= htmlspecialchars($row['level']) ?></p>
                        <p class="text-gray-700 text-sm mb-3 line-clamp-2"><?= htmlspecialchars($row['description']) ?></p>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-gray-600"><i class="fas fa-calendar mr-1"></i><?= date('M d, Y', strtotime($row['start_date'])) ?></span>
                            <span class="text-lg font-bold text-blue-600">M<?= $row['price'] ?></span>
                        </div>
                        <div class="flex gap-2">
                            <button onclick='openEditModal(<?= json_encode($row) ?>)' class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="confirmDelete(<?= $row['course_id'] ?>)" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center items-center gap-4 mt-8">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="bg-white hover:bg-gray-100 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow transition">
                        <i class="fas fa-chevron-left mr-2"></i>Previous
                    </a>
                <?php else: ?>
                    <span class="bg-gray-200 text-gray-500 font-semibold py-2 px-6 rounded-lg cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-2"></i>Previous
                    </span>
                <?php endif; ?>

                <span class="text-gray-700 font-medium">Page <?= $page ?> of <?= $total_pages ?></span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="bg-white hover:bg-gray-100 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow transition">
                        Next<i class="fas fa-chevron-right ml-2"></i>
                    </a>
                <?php else: ?>
                    <span class="bg-gray-200 text-gray-500 font-semibold py-2 px-6 rounded-lg cursor-not-allowed">
                        Next<i class="fas fa-chevron-right ml-2"></i>
                    </span>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="courseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 flex justify-between items-center rounded-t-lg">
                <h3 id="modalTitle" class="text-xl font-bold">Add New Course</h3>
                <button onclick="closeModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="course_id" id="course_id">
                <input type="hidden" name="existing_image_path" id="existing_image_path">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Title</label>
                        <input type="text" name="title" id="title" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Course Name</label>
                        <input type="text" name="courseName" id="courseName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Description</label>
                    <textarea name="description" id="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Category</label>
                        <input type="text" name="category" id="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Level</label>
                        <select name="level" id="level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Status</label>
                        <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Price (M)</label>
                        <input type="number" step="0.01" name="price" id="price" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Course Image (JPG/PNG, max 5MB)</label>
                    <input type="file" name="course_image" id="course_image" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" accept="image/jpeg,image/png">
                    <img id="image_preview" src="" alt="Preview" class="mt-3 max-w-xs rounded-lg" style="display: none;">
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" name="add_course" id="submitBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                        <i class="fas fa-save mr-2"></i>Save Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const cards = document.querySelectorAll('.course-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Course';
            document.getElementById('course_id').value = '';
            document.getElementById('submitBtn').name = 'add_course';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>Save Course';
            document.querySelector('form').reset();
            document.getElementById('image_preview').style.display = 'none';
            document.getElementById('courseModal').style.display = 'flex';
        }

        function openEditModal(course) {
            document.getElementById('modalTitle').textContent = 'Edit Course';
            document.getElementById('course_id').value = course.course_id;
            document.getElementById('title').value = course.title;
            document.getElementById('courseName').value = course.courseName;
            document.getElementById('description').value = course.description;
            document.getElementById('category').value = course.category;
            document.getElementById('level').value = course.level;
            document.getElementById('start_date').value = course.start_date;
            document.getElementById('end_date').value = course.end_date;
            document.getElementById('price').value = course.price;
            document.getElementById('status').value = course.status;
            document.getElementById('existing_image_path').value = course.image_path;
            document.getElementById('image_preview').src = course.image_path;
            document.getElementById('image_preview').style.display = 'block';
            document.getElementById('submitBtn').name = 'update_course';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>Update Course';
            document.getElementById('courseModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('courseModal').style.display = 'none';
        }

        // Image preview
        document.getElementById('course_image').addEventListener('change', function() {
            const preview = document.getElementById('image_preview');
            if (this.files && this.files[0]) {
                preview.src = URL.createObjectURL(this.files[0]);
                preview.style.display = 'block';
            }
        });

        // Delete confirmation
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