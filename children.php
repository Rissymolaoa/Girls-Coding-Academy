<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_id = $_SESSION['user_id']; // parent logged in, stored as user_id

// Fetch children linked to this parent
$query = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN parents p ON ps.parent_id = p.parent_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE p.user_id = ?
");
$query->bind_param("i", $parent_id);
$query->execute();
$result = $query->get_result();
$children = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Children Profiles - Parent Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
        }
        .sidebar {
            width: 250px;
            background: #343a40;
            color: white;
            flex-shrink: 0;
        }
        .sidebar h4 {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #495057;
        }
        .sidebar img {
            width: 80px;
            border-radius: 50%;
            margin: 10px auto;
            display: block;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .student-card {
            transition: transform 0.2s;
        }
        .student-card:hover {
            transform: scale(1.05);
        }
        .student-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="admin.png" alt="Parent Image">
        <h4>Parent Dashboard</h4>
        <a href="parents_dashboard.php">Dashboard</a>
        <a href="children.php">Children Profiles</a>
        <a href="parent_messages.php">Messages</a>
        <a href="parent_settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <h2>Your Children</h2>
        <div class="row">
            <?php if (count($children) > 0): ?>
                <?php foreach ($children as $child): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card student-card shadow-sm">
                            <img src="<?php echo $child['photo'] ?: 'default_student.png'; ?>" class="student-img" alt="Student Image">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($child['firstName'] . " " . $child['lastName']); ?></h5>
                                <p class="card-text">
                                    Gender: <?php echo htmlspecialchars($child['gender']); ?>
                                </p>
                                <a href="student_parent_profile.php?id=<?php echo $child['student_id']; ?>" class="btn btn-primary">View Student</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No children assigned to your account.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
