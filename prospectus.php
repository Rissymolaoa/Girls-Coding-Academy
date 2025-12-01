<?php
session_start();
require_once 'config.php';

// Fetch user info
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';
$user_name = 'Guest';
if ($user_id) {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $user_name = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
    }
}

// Fetch ACTIVE courses with all details
try {
    $stmt = $pdo->prepare("
        SELECT 
            course_id,
            title,
            courseName,
            description,
            category,
            level,
            start_date,
            end_date,
            price,
            status,
            image_path
        FROM courses 
        WHERE status = 'active'
        ORDER BY course_id DESC
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Unable to load courses at this time.";
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospectus 2025 | Girls Coding Academy Lesotho</title>
    <meta name="description" content="Empowering young women in Lesotho & South Africa with world-class coding, robotics, and tech skills. Enroll today in our Junior Certificate & Degree-level programs.">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --accent: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --success: #10b981;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        .header-hero {
            text-align: center;
            padding: 4rem 1rem 3rem;
            color: white;
        }
        .header-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .header-hero p {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.95;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .welcome-bar {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 2rem;
            border-radius: 16px;
            text-align: center;
            font-size: 1.2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .course-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.4s ease;
            position: relative;
        }
        .course-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .course-img {
            height: 220px;
            overflow: hidden;
        }
        .course-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .course-card:hover .course-img img {
            transform: scale(1.1);
        }
        .course-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--secondary);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 10;
        }
        .course-content {
            padding: 1.8rem;
        }
        .course-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.8rem;
        }
        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 1rem 0;
            font-size: 0.95rem;
            color: var(--gray);
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .meta-item i { color: var(--primary); }
        .course-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin: 1rem 0;
        }
        .price-note {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: -0.5rem;
        }
        .btn-enroll {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1.5rem;
        }
        .btn-enroll:hover {
            background: linear-gradient(135deg, var(--primary-dark), #4338ca);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.4);
        }
        .no-courses {
            text-align: center;
            padding: 4rem 2rem;
            color: white;
            font-size: 1.4rem;
        }
        footer {
            text-align: center;
            padding: 3rem 1rem;
            color: white;
            font-size: 1rem;
            margin-top: 4rem;
        }
        @media (max-width: 768px) {
            .header-hero h1 { font-size: 2.5rem; }
            .header-hero p { font-size: 1.1rem; }
            .courses-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'login_navigation.php'; ?>

    <div class="header-hero">
        <h1>Girls Coding Academy</h1>
        <p>Empowering the next generation of African women in technology • Lesotho & South Africa</p>
    </div>

    <div class="container">
        <div class="welcome-bar">
            Welcome<?php echo $user_name !== 'Guest' ? ", $user_name" : ''; ?>! 
            Explore our cutting-edge courses and start your tech journey today
        </div>

        <?php if (!empty($courses)): ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): 
                    $duration = '';
                    if ($course['start_date'] && $course['end_date']) {
                        $start = new DateTime($course['start_date']);
                        $end = new DateTime($course['end_date']);
                        $months = $start->diff($end)->m + ($start->diff($end)->y * 12);
                        $duration = $months . " months";
                    }
                    $image = !empty($course['image_path']) && file_exists($course['image_path']) 
                        ? $course['image_path'] 
                        : 'uploads/courses/default-course.jpg'; // fallback image
                ?>
                    <div class="course-card">
                        <div class="course-img">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($course['courseName']); ?>">
                            <div class="course-badge"><?php echo htmlspecialchars($course['level']); ?></div>
                        </div>
                        <div class="course-content">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['courseName']); ?></h3>
                            
                            <div class="course-meta">
                                <div class="meta-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['category']); ?></div>
                                <?php if ($duration): ?>
                                    <div class="meta-item"><i class="fas fa-clock"></i> <?php echo $duration; ?></div>
                                <?php endif; ?>
                                <div class="meta-item"><i class="fas fa-calendar"></i> Starts <?php echo date('M Y', strtotime($course['start_date'])); ?></div>
                            </div>

                            <p style="color: var(--gray); margin: 1rem 0; font-size: 0.95rem;">
                                <?php echo htmlspecialchars(strlen($course['description']) > 140 
                                    ? substr($course['description'], 0, 140) . '...' 
                                    : $course['description']); ?>
                            </p>

                            <div class="course-price">
                                R<?php echo number_format((float)$course['price'], 2); ?>
                                <div class="price-note">Full course fee • Payment plans available</div>
                            </div>

                            <?php if ($role !== 'guest'): ?>
                                <a href="enroll.php?course=<?php echo $course['course_id']; ?>" class="btn-enroll">
                                    <i class="fas fa-rocket"></i> Enroll Now
                                </a>
                            <?php else: ?>
                                <a href="registration.html" class="btn-enroll">
                                    <i class="fas fa-sign-in-alt"></i> Register to Enroll
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-courses">
                <i class="fas fa-sad-tear fa-4x mb-4"></i>
                <p>No courses are currently open for enrollment.<br>Check back soon — new batches start regularly!</p>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Girls Coding Academy • Lesotho • Empowering Women in Technology</p>
        <p style="margin-top: 1rem; opacity: 0.8;">
            <i class="fas fa-phone"></i> +266 5837 5096 • 
            <i class="fas fa-envelope"></i> info@girlscodingacademy.com
        </p>
    </footer>

</body>
</html>