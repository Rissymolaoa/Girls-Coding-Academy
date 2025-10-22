<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
            margin: 15px auto;
            display: block;
            border: 2px solid #6c757d;
        }
        .sidebar h3 {
            text-align: center;
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
        .card {
            border: 2px solid #dee2e6; /* added border */
            border-radius: 12px;
        }
        .card h5 {
            font-weight: bold;
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
    <img src="admin.png" alt="Parent Picture">
    <h3 class="text-center">Parent Panel</h3>
    <a href="parent_dashboard.php"  class="active"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php"><i class="bi bi-people"></i> My Children</a>
    <a href="parent_view_attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="parent_view_performance.php"><i class="bi bi-graph-up"></i> Performance</a>
    <a href="parent_view_materials.php"><i class="bi bi-folder"></i> Materials</a>
    <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
    <a href="parents_chatting.php"><i class="bi bi-chat"></i> Group Chat</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header mb-4">
            <h1>Welcome, <?= htmlspecialchars($parent['firstName']) ?></h1>
        </div>

        <!-- Home Section -->
        <div id="home" class="section active">
            <div class="row">
                <!-- My Children -->
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-people"></i> My Children</h5>
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

                <!-- Attendance -->
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-card-checklist"></i> Attendance</h5>
                            <p class="card-text fs-3">View Records</p>
                            <a href="parent_view_attendance.php" class="btn btn-light btn-sm">View</a>
                        </div>
                    </div>
                </div>

                <!-- Performance -->
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-graph-up"></i> Performance</h5>
                            <p class="card-text fs-3">Check Progress</p>
                            <a href="parent_view_performance.php" class="btn btn-light btn-sm">View</a>
                        </div>
                    </div>
                </div>

                <!-- Marks -->
                <div class="col-md-4">
                    <div class="card text-white bg-dark mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-bar-chart-line-fill"></i> Marks</h5>
                            <p class="card-text fs-3">Latest Results</p>
                            <a href="parent_view_marks.php" class="btn btn-light btn-sm">View</a>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="col-md-4">
                    <div class="card text-white bg-warning mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-calendar-event"></i> Schedule</h5>
                            <p class="card-text fs-3">View Calendar</p>
                            <a href="parent_view_schedule.php" class="btn btn-light btn-sm">View</a>
                        </div>
                    </div>
                </div>

                <!-- Materials -->
                <div class="col-md-4">
                    <div class="card text-white bg-secondary mb-3 shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="bi bi-folder"></i> Materials</h5>
                            <p class="card-text fs-3">Files & Notes</p>
                            <a href="parent_view_materials.php" class="btn btn-light btn-sm">View</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="section">
            <div class="card shadow p-4">
                <h2><i class="bi bi-person-circle"></i> My Profile</h2>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($parent['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($parent['phone']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($parent['username']) ?></p>
            </div>
        </div>

        <!-- Children Section -->
        <div id="children" class="section">
            <div class="container">
                <h2 class="mb-4"><i class="bi bi-people"></i> My Children</h2>
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
                                <a href="student_parent_profile.php?student_id=<?= $child['user_id'] ?>" class="btn btn-primary btn-sm">View Profile</a>
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
                <h2><i class="bi bi-envelope"></i> Messages</h2>
                <p>Messages functionality will be implemented here.</p>
            </div>
        </div>

        <!-- Notifications Section -->
        <div id="notifications" class="section">
            <div class="card shadow p-4">
                <h2><i class="bi bi-bell"></i> Notifications</h2>
                <p>Notifications functionality will be implemented here.</p>
            </div>
        </div>

        <!-- Settings Section -->
        <div id="settings" class="section">
            <div class="card shadow p-4">
                <h2><i class="bi bi-gear"></i> Settings</h2>
                <p>Settings functionality will be implemented here.</p>
            </div>
        </div>
    </div>
</body>
</html>
