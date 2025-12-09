<?php
session_start();

// Database connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $selectedRole = trim($_POST['role']); // role selected from login page

    // Function to redirect with error to modal
    function redirectWithError($msg) {
        $msg = urlencode($msg);
        header("Location: login.html?error=$msg");
        exit();
    }

    if (empty($selectedRole)) {
        redirectWithError('Please select a role before logging in.');
    }

    $hardcodedAdminUser  = "admin";
    $hardcodedAdminEmail = "admin@girlscodingacademy.com";
    $hardcodedAdminHash  = '$2y$10$H86xIq4M.tYYwlggdOKPdufvBbFe7VTmiYF6XLgyfZ5gEj0rYW00S'; 

    // First check: Hardcoded Admin
    if (
        ($username === $hardcodedAdminUser || $username === $hardcodedAdminEmail) 
        && password_verify($password, $hardcodedAdminHash)
    ) {
        if ($selectedRole !== 'admin') {
            redirectWithError('Invalid role selection for this account.');
        }

        $_SESSION['user_id']  = 0; // no DB ID for hardcoded
        $_SESSION['username'] = $hardcodedAdminUser;
        $_SESSION['role']     = "admin";

        header("Location: admin_dashboard.php");
        exit();
    }

    // Check DB users
    $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $db_username, $db_password, $role, $status);
        $stmt->fetch();

        if ($status !== 'active') {
            redirectWithError('Your account is not active. Please verify your email or wait for admin approval.');
        }

        if ($role !== $selectedRole) {
            redirectWithError('Role mismatch! Please select the correct role for your account.');
        }

        if (password_verify($password, $db_password)) {
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $db_username;
            $_SESSION['role']     = $role;

            // Redirect based on role
            switch ($role) {
                case "student":
                    header("Location: student.php");
                    break;
                 case "marketing":
                    header("Location: marketing_dashboard.php");
                    break;
                case "accounts":
                    header("Location: accounts_dashboard.php");
                    break;
                case "teacher":
                    header("Location: teacher_dashboard.php");
                    break;
                case "admin":
                    header("Location: admin_dashboard.php");
                    break;
                case "parent":
                    header("Location: parents_dashboard.php");
                    break;
                default:
                    header("Location: dashboard.php");
            }
            exit();
        } else {
            redirectWithError('Incorrect password!');
        }
    } else {
        redirectWithError('User not found!');
    }

    $stmt->close();
}

$conn->close();
?>
