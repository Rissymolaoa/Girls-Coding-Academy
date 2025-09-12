
<?php
session_start();
include 'db.php'; // DB connection

// Ensure parent is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

echo 'Logged-in parent ID: ' . $_SESSION['user_id'];


$parent_id = $_SESSION['user_id'];

// Fetch children of this parent
$children_sql = "
    SELECT u.*, ps.relationship
    FROM parent_students ps
    INNER JOIN users u ON ps.student_id = u.user_id
    WHERE ps.parent_id = ?
";
$stmt = $conn->prepare($children_sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_result = $stmt->get_result();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Children</title>
    <link rel="stylesheet" href="parent_dashboard.css">
    <style>
        .child-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 12px;
        }
        .child-card img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .child-info p {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Parent Dashboard</h2>
        <ul class="nav">
            <li><a href="parents_dashboard.php">Home</a></li>
            <li><a href="parents_profile.php">Profile</a></li>
            <li><a href="children.php" class="active">Children</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="header">
            <h1>My Children</h1>
        </div>

        <div class="card">
            <?php if($children_result->num_rows > 0): ?>
                <?php while($child = $children_result->fetch_assoc()): ?>
                    <div class="child-card">
                        <!-- Hard-coded image -->
                        <img src="images/default_student.png" alt="Child Picture">
                        <div class="child-info">
                            <p><strong>Full Name:</strong> <?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($child['email']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($child['phone']) ?></p>
                            <p><strong>Username:</strong> <?= htmlspecialchars($child['username']) ?></p>
                            <p><strong>Gender:</strong> <?= htmlspecialchars($child['gender']) ?></p>
                            <p><strong>ID Number:</strong> <?= htmlspecialchars($child['IDNumber']) ?></p>
                            <p><strong>Relationship:</strong> <?= htmlspecialchars($child['relationship']) ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No children found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?= date('Y') ?> School Management System</p>
    </div>
</body>
</html>
