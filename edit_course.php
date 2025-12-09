<?php
session_start();

// Only admins can edit
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get course ID
if (!isset($_GET['id'])) {
    header("Location: manage_courses.php");
    exit();
}

$course_id = intval($_GET['id']);

// Handle update
if (isset($_POST['update_course'])) {
    $title       = $_POST['title'];
    $courseName  = $_POST['courseName'];
    $description = $_POST['description'];
    $category    = $_POST['category'];
    $level       = $_POST['level'];
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];
    $price       = $_POST['price'];
    $status      = $_POST['status'];

    $stmt = $conn->prepare("UPDATE courses SET title=?, courseName=?, description=?, category=?, level=?, start_date=?, end_date=?, price=?, status=? WHERE course_id=?");
    $stmt->bind_param("sssssssssi", $title, $courseName, $description, $category, $level, $start_date, $end_date, $price, $status, $course_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Course updated successfully!'); window.location.href='manage_courses.php';</script>";
    exit();
}

// Fetch course details
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();
$stmt->close();

if (!$course) {
    echo "Course not found!";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Course</title>
    <style>
        /* Modal overlay */
        body { font-family: Arial, sans-serif; margin: 0; }
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        /* Modal box */
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 700px;
            max-width: 95%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-content h2 {
            margin-bottom: 15px;
            color: #2c3e50;
        }

        /* Close button */
        .close-btn {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #e74c3c;
        }
        .close-btn:hover { color: #c0392b; }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        textarea { grid-column: span 2; resize: vertical; }

        input, textarea, select {
            padding: 8px; width: 100%;
            border: 1px solid #ccc; border-radius: 4px;
        }

        button {
            grid-column: span 2;
            background: #1abc9c;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover { background: #16a085; }
    </style>
</head>
<body>
    <!-- Modal -->
    <div class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="window.location.href='manage_courses.php'">&times;</span>
            <h2>Edit Course</h2>
            <form method="POST">
                <input type="text" name="title" value="<?= $course['title'] ?>" required>
                <input type="text" name="courseName" value="<?= $course['courseName'] ?>" required>
                <textarea name="description"><?= $course['description'] ?></textarea>
                <input type="text" name="category" value="<?= $course['category'] ?>">
                <input type="text" name="level" value="<?= $course['level'] ?>">
                <label>Start Date: <input type="date" name="start_date" value="<?= $course['start_date'] ?>"></label>
                <label>End Date: <input type="date" name="end_date" value="<?= $course['end_date'] ?>"></label>
                <input type="number" step="0.01" name="price" value="<?= $course['price'] ?>">
                <select name="status">
                    <option value="active" <?= $course['status']=="active" ? "selected" : "" ?>>Active</option>
                    <option value="inactive" <?= $course['status']=="inactive" ? "selected" : "" ?>>Inactive</option>
                </select>
                <button type="submit" name="update_course">💾 Update Course</button>
            </form>
        </div>
    </div>
</body>
</html>
