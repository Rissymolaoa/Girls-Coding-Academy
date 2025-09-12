<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login'])) {
    $temp_id = trim($_POST['temporary_id']);

    // FIXED: Check temporary_id in temporary_ids table
    $stmt = $conn->prepare("SELECT u.user_id, u.firstName, u.lastName, u.status 
                            FROM temporary_ids t
                            JOIN users u ON u.user_id = t.user_id
                            WHERE t.temporary_code = ? LIMIT 1");
    $stmt->bind_param("s", $temp_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $firstName, $lastName, $status);
    $stmt->fetch();

    if ($stmt->num_rows > 0) {
        $_SESSION['user_id']   = $user_id;
        $_SESSION['firstName'] = $firstName;
        $_SESSION['lastName']  = $lastName;
        $_SESSION['temporary_id'] = $temp_id;
        $_SESSION['status']    = $status;

        if ($status === "pending") {
            echo "<script>alert('Please verify your email first.'); window.location='temp_login.php';</script>";
        } elseif ($status === "pending") {
            header("Location: track_application.php"); 
            exit();
        } elseif ($status === "active") {
            header("Location: student_dashboard.php"); 
            exit();
        } else {
            echo "<script>alert('Unknown status. Contact admin.'); window.location='temp_login.php';</script>";
        }

    } else {
        echo "<script>alert('Temporary ID not found.'); window.location='temp_login.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login with Temporary ID</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            font-family: Arial, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            text-align: center;
            color: #fff;
            width: 350px;
        }
        .login-container h2 {
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: bold;
            color: #fff;
        }
        .login-container label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .login-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
        }
        .login-container button {
            width: 100%;
            padding: 12px;
            background: #6a11cb;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .login-container button:hover {
            background: #2575fc;
        }
        footer {
            position: absolute;
            bottom: 10px;
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #eee;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>User Registration Tracking</h2> <!-- ✅ Added heading text here -->
        <form method="POST">
            <label>Temporary ID:</label>
            <input type="text" name="temporary_id" placeholder="Enter your Temporary ID" required>
            <button type="submit" name="login">Login</button>
            <a href="login.html">🚪 Back to Login</a>

        </form>
    </div>
    <footer>
        &copy; <?php echo date("Y"); ?> Girls Coding Academy. All Rights Reserved.
    </footer>
</body>
</html>
