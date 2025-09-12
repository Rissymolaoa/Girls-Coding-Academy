<?php
session_start();

// Redirect helper with error
function redirectWithError($msg) {
    $msg = urlencode($msg);
    header("Location: temp_login.php?error=$msg");
    exit();
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    redirectWithError("You must log in first to track your application.");
}

// Only allow pending users to track application
if ($_SESSION['status'] !== "pending") {
    redirectWithError("Only applicants with pending status can track their application.");
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT t.temp_id, u.firstName, u.lastName, u.status, u.created_at 
    FROM temporary_ids t 
    INNER JOIN users u ON t.user_id = u.user_id 
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($temp_id, $firstName, $lastName, $status, $created_at);
$stmt->fetch();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Track Your Application - Girls Coding Academy</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #7b2cbf, #9d4edd, #c77dff);
            color: #fff;
        }
        .track-container {
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 40px;
            width: 450px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.3);
        }
        h1 { font-size: 28px; margin-bottom: 10px; color: #f3f0ff; }
        h2 { font-size: 20px; margin-bottom: 20px; color: #e0d7ff; }
        p { font-size: 16px; margin: 8px 0; }
        .status { font-weight: bold; color: #ffdd59; }
        button {
            margin-top: 25px; padding: 12px 25px;
            font-size: 16px; border: none; border-radius: 10px;
            background: #5a189a; color: #fff; cursor: pointer;
            transition: 0.3s;
        }
        button:hover { background: #7b2cbf; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="track-container">
        <h1>Girls Coding Academy</h1>
        <h2>Application Status for <?php echo htmlspecialchars($firstName . " " . $lastName); ?></h2>
        <p><strong>Temporary ID:</strong> <?php echo htmlspecialchars($temp_id); ?></p>
        <p><strong>Status:</strong> <span class="status"><?php echo htmlspecialchars($status); ?></span></p>
        <p><strong>Registered On:</strong> <?php echo htmlspecialchars($created_at); ?></p>
        <p>Please wait for the admin to approve your application.</p>
        <form method="POST" action="logout.php">
            <button type="submit">Logout</button>
        </form>
    </div>
</body>
</html>
