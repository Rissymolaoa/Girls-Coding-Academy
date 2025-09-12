<?php
session_start();
include 'db.php'; // DB connection

// Ensure parent is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

// Handle form submission
if(isset($_POST['update'])) {
    $firstName = $_POST['firstName'];
    $lastName  = $_POST['lastName'];
    $email     = $_POST['email'];
    $phone     = $_POST['phone'];
    $username  = $_POST['username'];

    $update_sql = "UPDATE users SET firstName=?, lastName=?, email=?, phone=?, username=? WHERE user_id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $username, $parent_id);

    if($stmt->execute()){
        $success = "Profile updated successfully!";
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}

// Fetch parent details
$parent_sql = "SELECT * FROM users WHERE user_id=?";
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
    <title>Parent Profile</title>
    <link rel="stylesheet" href="parent_dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="parent.jpg" alt="Profile Picture">
        <h3><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h3>
        <nav class="nav">
            <a href="parents_dashboard.php">Home</a>
            <a href="parents_profile.php" class="active">Profile</a>
            <a href="children.php">Children</a>
            <a href="messages.php">Messages</a>
            <a href="notifications.php">Notifications</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="header">
            <h1>My Profile</h1>
        </div>

        <!-- Feedback messages -->
        <?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

        <!-- Profile Card -->
        <div class="card profile-card">
            <div class="profile-header">
                <img src="parent.jpg" alt="Profile Picture" class="profile-pic">
                <h2><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h2>
            </div>

            <form action="" method="POST" class="profile-form">
                <!-- Editable fields -->
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="firstName" value="<?= htmlspecialchars($parent['firstName']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="lastName" value="<?= htmlspecialchars($parent['lastName']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($parent['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($parent['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($parent['username']) ?>" required>
                </div>

                <!-- Non-editable fields -->
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" value="********" disabled>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" value="<?= htmlspecialchars($parent['role']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <input type="text" value="<?= htmlspecialchars($parent['gender']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" value="<?= htmlspecialchars($parent['IDNumber']) ?>" disabled>
                </div>

                <div class="form-group">
                    <button type="submit" name="update" class="btn">Update Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; <?= date('Y') ?> School Management System</p>
    </div>
</body>
</html>
