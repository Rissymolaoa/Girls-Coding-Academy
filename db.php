<?php
// Database connection settings
$host = "localhost";
$user = "root";   // default XAMPP user
$pass = "";       // default XAMPP password (leave empty unless you set one)
$db   = "girlscodingacademydb";  // your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
