<?php
session_start();
require_once 'config.php';

// Fetch user info
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';
$user_name = 'Guest';
if ($user_id) {
    $stmt = $pdo->prepare("SELECT firstName, lastName, email FROM users WHERE user_id = ?");
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

// Get unique categories
$categories = array_unique(array_column($courses, 'category'));
sort($categories);

// Handle inquiry submission
$inquiry_success = '';
$inquiry_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $inquiry_name = trim($_POST['inquiry_name'] ?? '');
    $inquiry_email = trim($_POST['inquiry_email'] ?? '');
    $inquiry_phone = trim($_POST['inquiry_phone'] ?? '');
    $inquiry_course = trim($_POST['inquiry_course'] ?? '');
    $inquiry_message = trim($_POST['inquiry_message'] ?? '');

    if (empty($inquiry_name)) {
        $inquiry_error = "Name is required.";
    } elseif (!filter_var($inquiry_email, FILTER_VALIDATE_EMAIL)) {
        $inquiry_error = "Invalid email address.";
    } elseif (empty($inquiry_message)) {
        $inquiry_error = "Message is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO course_inquiries (name, email, phone, course_id, message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$inquiry_name, $inquiry_email, $inquiry_phone, $inquiry_course ?: null, $inquiry_message]);
            $inquiry_success = "Thank you! Your inquiry has been submitted. We'll respond to your email shortly.";
        } catch (Exception $e) {
            $inquiry_error = "Failed to submit inquiry. Please try again.";
        }
    }
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
        .filter-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .filter-btn {
            padding: 0.6rem 1.5rem;
            border: 2px solid var(--border);
            background: white;
            color: var(--dark);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1rem;
        }
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            cursor: pointer;
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
        .course-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn-primary, .btn-secondary {
            flex: 1;
            padding: 0.8rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.4);
        }
        .btn-secondary {
            background: var(--light);
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }
        .no-courses {
            text-align: center;
            padding: 4rem 2rem;
            color: white;
            font-size: 1.4rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-body {
            padding: 2rem;
        }
        .detail-section {
            margin-bottom: 2rem;
        }
        .detail-section h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.8rem;
        }
        .modal-tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 2rem;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fecaca;
        }
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .info-card {
            background: var(--light);
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }
        .info-card strong {
            color: var(--dark);
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
            .course-actions { flex-direction: column; }
            .filter-buttons { gap: 0.5rem; }
            .filter-btn { padding: 0.5rem 1rem; font-size: 0.9rem; }
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

        <!-- Filter Section -->
        <?php if (!empty($courses)): ?>
            <div class="filter-section">
                <div class="filter-title"><i class="fas fa-filter"></i> Filter by Category</div>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterCourses('all')">
                        All Courses
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="filter-btn" onclick="filterCourses('<?php echo htmlspecialchars($cat); ?>')">
                            <?php echo htmlspecialchars($cat); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="courses-grid" id="coursesGrid">
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
                        : 'uploads/courses/default-course.jpg';
                ?>
                    <div class="course-card" data-category="<?php echo htmlspecialchars($course['category']); ?>">
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

                            <div class="course-actions">
                                <button class="btn-secondary" onclick="openCourseDetail(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                    <i class="fas fa-info-circle"></i> Learn More
                                </button>
                                <?php if ($role !== 'guest'): ?>
                                    <a href="enroll.php?course=<?php echo $course['course_id']; ?>" class="btn-primary">
                                        <i class="fas fa-rocket"></i> Enroll
                                    </a>
                                <?php else: ?>
                                    <a href="registration.html" class="btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Register
                                    </a>
                                <?php endif; ?>
                            </div>
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

    <!-- Course Detail Modal -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle"></h2>
                    <p style="margin: 0.5rem 0 0 0; opacity: 0.9;" id="modalLevel"></p>
                </div>
                <button class="modal-close" onclick="closeCourseDetail()">×</button>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="switchTab(event, 'overview')">Overview</button>
                    <button class="tab-btn" onclick="switchTab(event, 'details')">Details</button>
                    <button class="tab-btn" onclick="switchTab(event, 'inquiry')">Ask a Question</button>
                </div>

                <!-- Overview Tab -->
                <div id="overview" class="tab-content active">
                    <div class="detail-section">
                        <h3>About This Course</h3>
                        <p id="modalDescription" style="color: var(--gray); line-height: 1.8;"></p>
                    </div>

                    <div class="course-info-grid">
                        <div class="info-card">
                            <i class="fas fa-graduation-cap"></i> <strong>Level:</strong> <span id="infoLevel"></span>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-tag"></i> <strong>Category:</strong> <span id="infoCategory"></span>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-clock"></i> <strong>Duration:</strong> <span id="infoDuration"></span>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-dollar-sign"></i> <strong>Price:</strong> <span id="infoPrice"></span>
                        </div>
                    </div>
                </div>

                <!-- Details Tab -->
                <div id="details" class="tab-content">
                    <div class="detail-section">
                        <h3><i class="fas fa-calendar-check"></i> Course Schedule</h3>
                        <p><strong>Start Date:</strong> <span id="startDate"></span></p>
                        <p><strong>End Date:</strong> <span id="endDate"></span></p>
                    </div>

                    <div class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> What You'll Learn</h3>
                        <p>This course provides comprehensive training in <?php echo htmlspecialchars($course['category'] ?? 'your chosen field'); ?>, equipping you with in-demand skills for the modern workforce.</p>
                    </div>

                    <div class="detail-section">
                        <h3><i class="fas fa-check"></i> Course Includes</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li><i class="fas fa-check" style="color: var(--success); margin-right: 0.5rem;"></i> Expert instruction from industry professionals</li>
                            <li><i class="fas fa-check" style="color: var(--success); margin-right: 0.5rem;"></i> Hands-on practical projects</li>
                            <li><i class="fas fa-check" style="color: var(--success); margin-right: 0.5rem;"></i> Certificate upon completion</li>
                            <li><i class="fas fa-check" style="color: var(--success); margin-right: 0.5rem;"></i> Flexible payment plans</li>
                        </ul>
                    </div>
                </div>

                <!-- Inquiry Tab -->
                <div id="inquiry" class="tab-content">
                    <?php if ($inquiry_success): ?>
                        <div class="alert alert-success"><?php echo $inquiry_success; ?></div>
                    <?php endif; ?>
                    <?php if ($inquiry_error): ?>
                        <div class="alert alert-error"><?php echo $inquiry_error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="submit_inquiry" value="1">
                        <input type="hidden" name="inquiry_course" id="inquiryCourseId" value="">

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="inquiry_name" required placeholder="Your full name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" name="inquiry_email" required placeholder="your@email.com" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="inquiry_phone" placeholder="Your phone number">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-question-circle"></i> Your Question *</label>
                            <textarea name="inquiry_message" rows="5" required placeholder="Ask your question about the course..."></textarea>
                        </div>

                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                            <i class="fas fa-paper-plane"></i> Send Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Girls Coding Academy • Lesotho • Empowering Women in Technology</p>
        <p style="margin-top: 1rem; opacity: 0.8;">
            <i class="fas fa-phone"></i> +266 5837 5096 • 
            <i class="fas fa-envelope"></i> info@girlscodingacademy.com
        </p>
    </footer>

    <script>
        let currentCourse = null;

        function filterCourses(category) {
            const cards = document.querySelectorAll('.course-card');
            const btns = document.querySelectorAll('.filter-btn');

            btns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = '';
                    setTimeout(() => card.style.opacity = '1', 10);
                } else {
                    card.style.opacity = '0';
                    setTimeout(() => card.style.display = 'none', 300);
                }
            });
        }

        function openCourseDetail(course) {
            currentCourse = course;
            document.getElementById('modalTitle').textContent = course.courseName;
            document.getElementById('modalLevel').textContent = course.level;
            document.getElementById('modalDescription').textContent = course.description;
            document.getElementById('infoLevel').textContent = course.level;
            document.getElementById('infoCategory').textContent = course.category;
            document.getElementById('infoPrice').textContent = 'R' + parseFloat(course.price).toFixed(2);
            document.getElementById('inquiryCourseId').value = course.course_id;

            const start = new Date(course.start_date);
            const end = new Date(course.end_date);
            const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30));
            document.getElementById('infoDuration').textContent = months + ' months';
            document.getElementById('startDate').textContent = start.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('endDate').textContent = end.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

            document.getElementById('courseModal').classList.add('show');
        }

        function closeCourseDetail() {
            document.getElementById('courseModal').classList.remove('show');
        }

        function switchTab(e, tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const btns = document.querySelectorAll('.tab-btn');

            tabs.forEach(tab => tab.classList.remove('active'));
            btns.forEach(btn => btn.classList.remove('active'));

            document.getElementById(tabName).classList.add('active');
            e.target.classList.add('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('courseModal');
            if (event.target === modal) {
                closeCourseDetail();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCourseDetail();
            }
        });
    </script>

</body>
</html>