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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_meeting_link') {
    $class_id     = intval($_POST['class_id'] ?? 0);
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $meeting_title = trim($_POST['meeting_title'] ?? 'Online Class');

    if ($class_id <= 0 || empty($meeting_link) || !filter_var($meeting_link, FILTER_VALIDATE_URL)) {
        $error_message = "Invalid class or meeting link. Please provide a valid URL.";
    } else {
        // Optional: save to database for history / display
        $stmt = $conn->prepare("
            INSERT INTO class_meeting_links (class_id, teacher_id, meeting_link, meeting_title, scheduled_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $teacher_id = $_SESSION['user_id'];
        $stmt->bind_param("iiss", $class_id, $teacher_id, $meeting_link, $meeting_title);
        $stmt->execute();
        $stmt->close();

        // Get all students enrolled in this class/batch
        // Adjust this query to match your actual structure
        $students_query = "
            SELECT u.email, u.firstName 
            FROM users u 
            INNER JOIN students s ON u.user_id = s.user_id 
            INNER JOIN course_enrollments ce ON s.student_id = ce.student_id
            WHERE ce.class_id = ? AND u.status = 'active'  -- adjust table/column names
        ";
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $sent_count = 0;
        while ($student = $result->fetch_assoc()) {
            $to = $student['email'];
            $subject = "Online Class Link: $meeting_title";

            $message = "
Dear {$student['firstName']},

A new online class has been scheduled.

Topic/Link: $meeting_title
Join here: $meeting_link

Date/Time: [Add date/time if you have it in your system]
Platform: Zoom / Google Meet / Teams (use the link above)

Best regards,
{$_SESSION['firstName']} (Teacher)
Girls Coding Academy
            ";

            $headers = "From: no-reply@girlscodingacademy.org\r\n";
            $headers .= "Reply-To: {$_SESSION['email']}\r\n";

            if (mail($to, $subject, $message, $headers)) {
                $sent_count++;
            }
        }
        $stmt->close();

        $success_message = "Meeting link shared successfully with $sent_count students!";
    }
}
?>
