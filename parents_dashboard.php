<?php
session_start();
include 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

// Fetch parent details
$parent_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #343a40;
            color: white;
            padding: 20px;
        }
        .sidebar img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        .sidebar h3 {
            margin-bottom: 20px;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 10px;
            margin: 5px 0;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #495057;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }
        .section {
            display: none;
        }
        .section.active {
            display: block;
        }
    </style>
    <script>
        function showSection(id) {
            const sections = document.querySelectorAll('.section');
            sections.forEach(sec => sec.classList.remove('active'));
            document.getElementById(id).classList.add('active');

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            document.getElementById(id + "-link").classList.add('active');
        }
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Admin image placeholder -->
        <img src="admin.png" alt="Admin Logo">
        <h3><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h3>
        <nav>
            <a href="#" id="home-link" class="nav-link active" onclick="showSection('home')">🏠 Home</a>
            <a href="#" id="profile-link" class="nav-link" onclick="showSection('profile')">👤 My Profile</a>
            <a href="children.php" id="children-link" class="nav-link" onclick="showSection('children')">👨‍👩‍👧 My Children</a>
            <a href="parent_messages.php" id="messages-link" class="nav-link" onclick="showSection('messages')">✉️ Messages</a>
            <a href="#" id="notifications-link" class="nav-link" onclick="showSection('notifications')">🔔 Notifications</a>
            <a href="#" id="settings-link" class="nav-link" onclick="showSection('settings')">⚙️ Settings</a>
            <a href="logout.php" class="nav-link">🚪 Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header mb-4">
            <h1>Welcome, <?= htmlspecialchars($parent['firstName']) ?></h1>
        </div>

        <!-- Home Section -->
        <div id="home" class="section active">
            <div class="row">
                <!-- Number of Children -->
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3 shadow">
                        <div class="card-body">
                            <h5 class="card-title">My Children</h5>
                            <?php
                            $count_sql = "SELECT COUNT(*) as total FROM parent_students WHERE parent_id = ?";
                            $stmtCount = $conn->prepare($count_sql);
                            $stmtCount->bind_param("i", $parent_id);
                            $stmtCount->execute();
                            $count_result = $stmtCount->get_result()->fetch_assoc();
                            ?>
                            <p class="card-text fs-3"><?= $count_result['total'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Homework -->
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Homeworks</h5>
                            <p class="card-text fs-3">3 Pending</p>
                        </div>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="col-md-4">
                    <div class="card text-white bg-warning mb-3 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Announcements</h5>
                            <p class="card-text fs-3">5 New</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="section">
            <div class="card shadow p-4">
                <h2>My Profile</h2>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($parent['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($parent['phone']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($parent['username']) ?></p>
            </div>
        </div>

        <!-- Children Section -->
        <div id="children" class="section">
            <div class="container">
                <h2 class="mb-4">My Children</h2>
                <div class="row">
                <?php
                $children_sql = "SELECT u.*, ps.relationship 
                                 FROM parent_students ps 
                                 JOIN users u ON ps.student_id = u.user_id 
                                 WHERE ps.parent_id = ?";
                $stmt2 = $conn->prepare($children_sql);
                $stmt2->bind_param("i", $parent_id);
                $stmt2->execute();
                $children_result = $stmt2->get_result();

                if($children_result->num_rows > 0):
                    while($child = $children_result->fetch_assoc()):
                ?>
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <img src="<?= $child['profile_pic'] ?? 'student.jpg' ?>" class="card-img-top" alt="Student Photo">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?= htmlspecialchars($child['firstName'].' '.$child['lastName']) ?></h5>
                                <p class="card-text">Relationship: <?= htmlspecialchars($child['relationship']) ?></p>
                                <a href="student_profile.php?student_id=<?= $child['user_id'] ?>" class="btn btn-primary btn-sm">View Profile</a>
                                <a href="student_attendance.php?student_id=<?= $child['user_id'] ?>" class="btn btn-info btn-sm">View Attendance</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p>No children found.</p>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages Section -->
        <div id="messages" class="section">
            <div class="card shadow p-4">
                <h2>Messages</h2>
                <p>Messages functionality will be implemented here.</p>
            </div>
        </div>

        <!-- Notifications Section -->
        <div id="notifications" class="section">
            <div class="card shadow p-4">
                <h2>Notifications</h2>
                <p>Notifications functionality will be implemented here.</p>
            </div>
        </div>

        <!-- Settings Section -->
        <div id="settings" class="section">
            <div class="card shadow p-4">
                <h2>Settings</h2>
                <p>Settings functionality will be implemented here.</p>
            </div>
        </div>
    </div>
</body>
</html>
