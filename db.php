<?php
// Database connection settings
$host = "localhost:3307";
$user = "root";     // default XAMPP user
$pass = "";         // default XAMPP password
$db   = "girlscodingacademydb";  // your database name

// MySQLi Connection (for existing files)
$conn = new mysqli($host, $user, $pass, $db);

// Check MySQLi connection
if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}

// PDO Connection (for profile.php and new files)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}
?>
