<?php
include("db.php");

$message = "";

$courses_result = $conn->query("SELECT * FROM courses");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch'])) {
    $course_id  = $_POST['course_id'];
    $batch_code = $_POST['batch_code'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO batches (batch_code, course_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $batch_code, $course_id, $start_date, $end_date, $status);

    if($stmt->execute()){
        $message = "Batch added successfully!";
    } else {
        $message = "Error: ".$stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch'])) {
    $batch_id   = $_POST['batch_id'];
    $course_id  = $_POST['course_id'];
    $batch_code = $_POST['batch_code'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = $_POST['status'];

    $stmt = $conn->prepare("UPDATE batches SET course_id=?, batch_code=?, start_date=?, end_date=?, status=? WHERE batch_id=?");
    $stmt->bind_param("issssi", $course_id, $batch_code, $start_date, $end_date, $status, $batch_id);

    if($stmt->execute()){
        $message = "Batch updated successfully!";
    } else {
        $message = "Error updating: ".$stmt->error;
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM batches WHERE batch_id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Batch deleted successfully!";
    } else {
        $message = "Error deleting batch: " . $stmt->error;
    }
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "1";
if($search !== '') {
    $where .= " AND (b.batch_code LIKE '%$search%' OR c.courseName LIKE '%$search%')";
}

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page-1)*$limit;

$total_result = $conn->query("SELECT COUNT(*) as total 
                              FROM batches b 
                              JOIN courses c ON c.course_id=b.course_id 
                              WHERE $where");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

$batches_q = "SELECT b.*, c.courseName, c.course_id 
              FROM batches b 
              JOIN courses c ON c.course_id=b.course_id 
              WHERE $where 
              ORDER BY c.courseName, b.batch_code 
              LIMIT $limit OFFSET $offset";
$batches = $conn->query($batches_q);

$qs = '';
if(!empty($_GET)){
    $params = $_GET;
    unset($params['page']);
    $qs = !empty($params) ? '&'.http_build_query($params) : '';
}

$course_colors = [];
$color_palette = ['#FFEBEE','#E3F2FD','#E8F5E9','#FFF3E0','#F3E5F5','#E0F7FA'];
$color_index = 0;
$courses_result->data_seek(0);
while($course = $courses_result->fetch_assoc()) {
    $course_colors[$course['courseName']] = $color_palette[$color_index % count($color_palette)];
    $color_index++;
}
$courses_result->data_seek(0); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Batch - Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial,sans-serif; background:#f5f6fa; color:#2c3e50; }

    header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }
    
    h1{margin:0;
    font-size:20px;
    font-weight:600}

.container { display:flex; min-height: calc(100vh - 70px); }

.sidebar { width:220px; background:#34495e; padding:20px; display:flex; flex-direction:column; align-items:center;}
.sidebar img { width:100px; height:100px; border-radius:50%; margin-bottom:15px; border:3px solid #7b2cbf; object-fit:cover; }

.sidebar h3{font-size:13px;margin:0 0 12px;color:white}

.sidebar a { width:100%; display:block; color:white; text-decoration:none; padding:10px 15px; margin:5px 0; border-radius:5px; font-size:14px; }
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }


.content { flex:1; padding:30px; display:flex; gap:30px; align-items:flex-start; }

.form-wrapper { width:450px; }
form { width:100%; background:white; padding:25px; box-shadow:0 2px 6px rgba(0,0,0,0.1); border-radius:8px; }
label { display:block; margin:10px 0 5px; font-weight:bold; }
input, select { width:100%; padding:12px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px; }
button { width:100%; padding:12px; background:#7b2cbf; color:white; border:none; border-radius:5px; cursor:pointer; font-size:16px; transition:0.3s; }
button:hover { background:#5a189a; }

.table-wrapper { flex:1; overflow-x:auto; margin-top:10px; }
table { width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden; margin-top:0; }
th,td { padding:10px; border-bottom:1px solid #ddd; text-align:left; font-size:14px; }
th { background: linear-gradient(90deg,#7b2cbf,#5a189a); color:white; }
.pagination { margin-top:15px; text-align:center; }
.pagination a, .pagination span { display:inline-block; margin:0 5px; padding:6px 10px; border-radius:5px; text-decoration:none; color:#7b2cbf; border:1px solid #7b2cbf; }
.pagination span.disabled { color:#999; border-color:#999; }
.pagination a:hover { background:#7b2cbf; color:white; }

.message { text-align:center; color:green; font-weight:bold; margin-bottom:10px; }

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: flex-start; 
}

.search-form input {
    flex: 1;
    padding: 8px 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
    font-size: 14px;
}

.search-form button {
    padding: 5px 5px;     
    height: auto;          
    border: none;
    border-radius: 1px;
    background: #7b2cbf;
    color: white;
    cursor: pointer;
    font-size: 13px;         
    align-self: center;      
    transition: 0.3s;
}

.search-form1 button:hover {
    background: #5a189a;
}


.modal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
}
.modal-content {
    background:#fff;
    padding:20px;
    border-radius:8px;
    width:400px;
    position:relative;
}
.close {
    position:absolute;
    top:10px; right:15px;
    font-size:18px;
    cursor:pointer;
    color:red;
}
</style>
</head>
<body>

<header>
<h1>Girls Coding Academy - Admin Dashboard</h1>
</header>

<div class="container">
    <div class="sidebar">
        <img src="admin.png" alt="Admin Picture">
        <h3>GIRLS CODING ACADEMY</h3>
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

    <div class="content">
        <!-- Add Batch Form -->
        <div class="form-wrapper">
            <h2>Add Batch</h2>
            <?php if($message) echo "<p class='message'>$message</p>"; ?>

            <form method="POST">
                <input type="hidden" name="add_batch" value="1">
                <label>Select Course:</label>
                <select name="course_id" required>
                    <option value="">-- Select Course --</option>
                    <?php
                    $courses_result->data_seek(0);
                    while($course = $courses_result->fetch_assoc()) {
                        echo "<option value='".$course['course_id']."'>".$course['courseName']."</option>";
                    }
                    ?>
                </select>
                <label>Batch Code:</label><input type="text" name="batch_code" required>
                <label>Start Date:</label><input type="date" name="start_date" required>
                <label>End Date:</label><input type="date" name="end_date" required>
                <label>Status:</label>
                <select name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <button type="submit">Add Batch</button>
            </form>
        </div>

        <!-- Batches Table -->
        <div class="table-wrapper">
            <form method="GET" class="search-form1">
                <input type="text" name="search" placeholder="Search batches or courses..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit">Search</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Batch Code</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($batches->num_rows > 0): ?>
                        <?php while($b = $batches->fetch_assoc()): 
                            $bg_color = $course_colors[$b['courseName']] ?? '#fff';
                        ?>
                            <tr style="background:<?= $bg_color ?>">
                                <td><?= htmlspecialchars($b['courseName']) ?></td>
                                <td><?= htmlspecialchars($b['batch_code']) ?></td>
                                <td><?= htmlspecialchars($b['start_date']) ?></td>
                                <td><?= htmlspecialchars($b['end_date']) ?></td>
                                <td><?= htmlspecialchars($b['status']) ?></td>
                                <td>
                                    <a href="javascript:void(0)" onclick="openEditModal(
                                        '<?= $b['batch_id'] ?>',
                                        '<?= $b['course_id'] ?>',
                                        '<?= htmlspecialchars($b['batch_code'], ENT_QUOTES) ?>',
                                        '<?= $b['start_date'] ?>',
                                        '<?= $b['end_date'] ?>',
                                        '<?= $b['status'] ?>'
                                    )" style="color:blue; text-decoration:none;">✏️ Edit</a> | 
                                    <a href="?delete=<?= $b['batch_id'] ?>" style="color:red; text-decoration:none;" onclick="return confirm('Are you sure you want to delete this batch?');">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No batches found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="modal" id="editModal">
                <div class="modal-content">
                    <span class="close" onclick="closeEditModal()">&times;</span>
                    <h3>Edit Batch</h3>
                    <form method="POST">
                        <input type="hidden" name="update_batch" value="1">
                        <input type="hidden" name="batch_id" id="edit_batch_id">
                        <label>Course:</label>
                        <select name="course_id" id="edit_course_id" required>
                            <?php
                            $courses_result->data_seek(0);
                            while ($row = $courses_result->fetch_assoc()):
                            ?>
                                <option value="<?= $row['course_id'] ?>"><?= htmlspecialchars($row['courseName']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <label>Batch Code:</label>
                        <input type="text" name="batch_code" id="edit_batch_code" required>
                        <label>Start Date:</label>
                        <input type="date" name="start_date" id="edit_start_date" required>
                        <label>End Date:</label>
                        <input type="date" name="end_date" id="edit_end_date" required>
                        <label>Status:</label>
                        <select name="status" id="edit_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Completed">Completed</option>
                            <option value="Pending">Pending</option>
                        </select>
                        <button type="submit">Update Batch</button>
                    </form>
                </div>
            </div>

            <script>
            function openEditModal(id, course_id, code, start, end, status) {
                document.getElementById('edit_batch_id').value = id;
                document.getElementById('edit_course_id').value = course_id;
                document.getElementById('edit_batch_code').value = code;
                document.getElementById('edit_start_date').value = start;
                document.getElementById('edit_end_date').value = end;
                document.getElementById('edit_status').value = status;
                document.getElementById('editModal').style.display = 'flex';
            }
            function closeEditModal() {
                document.getElementById('editModal').style.display = 'none';
            }
            </script>

            <!-- Pagination -->
            <div class="pagination">
                <?php if($page>1): ?>
                    <a href="?page=<?= ($page-1) . $qs ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $total_pages ?></span>

                <?php if($page<$total_pages): ?>
                    <a href="?page=<?= ($page+1) . $qs ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
