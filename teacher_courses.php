<?php
session_start();

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// For demo, get teacher info (adjust according to your login/session logic)
$teacher_id = $_SESSION['user_id'] ?? 1; 
$teacher = $conn->query("SELECT firstName, lastName FROM teachers WHERE user_id = $teacher_id LIMIT 1");
$teacherData = $teacher && $teacher->num_rows > 0 ? $teacher->fetch_assoc() : [
    "firstName" => "Demo",
    "lastName" => "Teacher",
];

// Fetch all courses
$courses = [];
$sql = "SELECT * FROM courses";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher - My Courses</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background: #2c3e50;
            height: 100vh;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 22px;
        }
        .profile-pic {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-pic img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            padding: 12px 20px;
            cursor: pointer;
            position: relative;
        }
        .sidebar ul li:hover {
            background: #34495e;
        }
        .sidebar ul li ul {
            display: none;
            list-style: none;
            padding-left: 20px;
            background: #3e4d5c;
        }
        .sidebar ul li:hover ul {
            display: block;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: block;
        }
        .admin-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid #1abc9c;
            object-fit: cover;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            flex: 1;
            background: #ecf0f1;
            min-height: 100vh;
        }
        h1 { color: #2c3e50; }

        .courses {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .course-card img {
            max-width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }
        .course-card h3 {
            margin: 10px 0 5px;
            font-size: 18px;
            color: #2c3e50;
        }
        .course-card p {
            font-size: 14px;
            color: #555;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Teacher Panel</h2>
    <div class="profile-pic">
        <img src="admin.jpg" alt="Teacher Picture" class="admin-pic">
        <p><?php echo htmlspecialchars($teacherData['firstName'] . " " . $teacherData['lastName']); ?></p>
    </div>
    <ul>
        <li>Academic Notifications
            <ul>
                <li><a href="#">Make Announcements</a></li>
            </ul>
        </li>
        <li>Virtual Classes
            <ul>
                <li><a href="#">Start Virtual Class</a></li>
                <li><a href="#">Join Virtual Meeting</a></li>
            </ul>
        </li>
        <li>Schedules
            <ul>
                <li><a href="#">View Schedules</a></li>
                <li><a href="#">Schedule a Lesson</a></li>
            </ul>
        </li>
        <li>Profiles
            <ul>
                <li><a href="#">My Profile</a></li>
                <li><a href="#">My Student's Profiles</a></li>
            </ul>
        </li>
        <li>Send Emails
            <ul>
                <li><a href="#">System Help Desk</a></li>
                <li><a href="#">Send to Administrator</a></li>
                <li><a href="#">Send to Teachers</a></li>
                <li><a href="#">Send to Students</a></li>
            </ul>
        </li>
        <li><a href="teacher_courses.php">My Courses</a></li>
        <li><a href="logout.php">Logout</a></li>

    </ul>
</div>

<!-- Main Content -->
<div class="content">
    <h1>My Courses</h1>
    <div class="courses">
        <?php if (!empty($courses)) : ?>
            <?php foreach ($courses as $course) : ?>
                <div class="course-card">
                    <img src="<?php echo $course['image_path'] ? $course['image_path'] : 'https://via.placeholder.com/220x120'; ?>" alt="Course Image">
                    <h3><?php echo htmlspecialchars($course['courseName']); ?></h3>
                    <p><?php echo htmlspecialchars($course['description']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p>No courses available.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
