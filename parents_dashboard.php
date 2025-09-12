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
    <link rel="stylesheet" href="parent_dashboard.css">
    <script>
        function showSection(id) {
            const sections = document.querySelectorAll('.section');
            sections.forEach(sec => sec.style.display = 'none');
            document.getElementById(id).style.display = 'block';
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        function toggleChildDetails(id) {
            const row = document.getElementById(id);
            if(row.style.display === 'table-row') {
                row.style.display = 'none';
            } else {
                row.style.display = 'table-row';
            }
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
        <img src="<?= isset($parent['profile_pic']) ? $parent['profile_pic'] : 'parent.jpg' ?>" alt="Profile Picture">
        <h3><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h3>
        <nav class="nav">
            <a href="#" onclick="showSection('home')" class="active">Home</a>
            <a href="parents_profiles.php">My Profile</a>
            <a href="children.php">My Children</a>
            <a href="#" onclick="showSection('messages')">Messages</a>
            <a href="#" onclick="showSection('notifications')">Notifications</a>
            <a href="#" onclick="showSection('settings')">Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome, <?= htmlspecialchars($parent['firstName']) ?></h1>
        </div>

        <div id="home" class="section">
            <div class="card">
                <h2>Dashboard</h2>
                <p>Use the navigation menu to view your profile, children, messages, and settings.</p>
            </div>
        </div>

        <div id="profile" class="section" style="display:none;">
            <div class="card">
                <h2>My Profile</h2>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($parent['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($parent['phone']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($parent['username']) ?></p>
            </div>
        </div>

        <div id="children" class="section" style="display:none;">
            <div class="card">
                <h2>My Children</h2>
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
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $i = 0;
                        while($child = $children_result->fetch_assoc()): 
                            $i++;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></td>
                                <td>
                                    <button onclick="toggleChildDetails('child<?= $i ?>')">View Details</button>
                                </td>
                            </tr>
                            <tr id="child<?= $i ?>" style="display:none;background:#f9f9f9;">
                                <td colspan="2">
                                    <p><strong>Email:</strong> <?= htmlspecialchars($child['email']) ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($child['phone']) ?></p>
                                    <p><strong>Relationship:</strong> <?= htmlspecialchars($child['relationship']) ?></p>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No children found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="messages" class="section" style="display:none;">
            <div class="card">
                <h2>Messages</h2>
                <p>Messages functionality will be implemented here.</p>
            </div>
        </div>

        <div id="notifications" class="section" style="display:none;">
            <div class="card">
                <h2>Notifications</h2>
                <p>Notifications functionality will be implemented here.</p>
            </div>
        </div>

        <div id="settings" class="section" style="display:none;">
            <div class="card">
                <h2>Settings</h2>
                <p>Settings functionality will be implemented here.</p>
            </div>
        </div>
    </div>
</body>
</html>
