<?php
// prospectus.php
session_start();
require_once 'config.php'; // Database connection file

// Fetch user info for personalization
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';
$user_name = 'Guest';
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $user_name = $user['firstName'] . ' ' . $user['lastName'];
        }
    } catch (PDOException $e) {
        $error = "Error fetching user data: " . $e->getMessage();
    }
}

// Fetch available courses from the database
try {
    $stmt = $pdo->prepare("SELECT courseName, description FROM courses");
    $stmt->execute();
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching courses: " . $e->getMessage();
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospectus - Girls Coding Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #3b82f6;
            --accent-blue: #1d4ed8;
            --accent-hover: #1e40af;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 20px rgba(0,0,0,0.1);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
            --error-red: #e74c3c;
            --text-dark: #1e293b;
            --text-light: #64748b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1e40af 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            transition: box-shadow 0.3s ease;
        }

        .container:hover {
            box-shadow: var(--shadow-hover);
        }

        h2 {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 500;
            margin: 1.5rem 0 1rem;
        }

        .course-list {
            list-style: none;
            padding: 0;
        }

        .course-item {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .course-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-light);
        }

        .course-item h4 {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .course-item p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        footer {
            background: rgba(30, 58, 138, 0.95);
            backdrop-filter: blur(10px);
            color: var(--white);
            text-align: center;
            padding: 1rem;
            margin-top: auto;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                margin: 1rem auto;
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }

            .course-item h4 {
                font-size: 1rem;
            }

            .course-item p {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'login_navigation.php'; ?>

    <div class="container">
        <h2>Prospectus</h2>
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?>. Explore our construction-focused coding courses below.</p>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="section-title">Available Courses</div>
        <ul class="course-list">
            <?php if (empty($courses)): ?>
                <li class="course-item">
                    <p>No courses available at this time. Please check back later.</p>
                </li>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <li class="course-item">
                        <h4><?php echo htmlspecialchars($course['courseName']); ?></h4>
                        <p><?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?></p>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <?php if ($role !== 'guest'): ?>
            <div class="section-title">Interested in Enrolling?</div>
            <p>Contact our <a href="help_desk.php">Help Desk</a> for enrollment details or to discuss how our courses can enhance your construction project skills.</p>
        <?php else: ?>
            <div class="section-title">Get Started</div>
            <p><a href="login.php">Log in</a> or <a href="registration.html">register</a> to enroll in our construction-focused coding courses.</p>
        <?php endif; ?>
    </div>

    <footer>
        &copy; 2025 Girls Coding Academy. All rights reserved.
    </footer>
</body>
</html>