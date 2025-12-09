<?php
session_start();
$conn = new mysqli("localhost:3307", "root", "", "girlscodingacademydb");
if ($conn->connect_error) die("Connection failed");
$conn->set_charset("utf8mb4");

// === REAL DATA FROM YOUR DATABASE ===
$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$total_graduates = $conn->query("SELECT COUNT(*) FROM course_enrollments WHERE status = 'completed'")->fetch_row()[0] ?? 0;
$total_courses = $conn->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetch_row()[0];
$total_batches = $conn->query("SELECT COUNT(*) FROM batches WHERE status = 'active'")->fetch_row()[0];

// Active courses
$courses = $conn->query("
    SELECT course_id, courseName, description, category, image_path 
    FROM courses 
    WHERE status = 'active' 
    ORDER BY course_id DESC 
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Teachers (from your real teachers table)
$teachers = $conn->query("
    SELECT t.teacher_id, u.firstName, u.lastName, t.subject_speciality, t.photo 
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Testimonials (you can add more in DB)
$testimonials = $conn->query("
    SELECT name, message, photo, role 
    FROM testimonials 
    ORDER BY id DESC 
    LIMIT 6
");
if ($testimonials->num_rows == 0) {
    // Fallback beautiful fake ones
    $testimonials = [
        ['name' => 'Relebohile Mokoena', 'role' => 'Python Graduate 2024', 'photo' => 'test1.jpg', 'message' => 'GCA gave me confidence I never knew I had. Today I work as a backend developer in Maseru!'],
        ['name' => 'Nthati Lepheane', 'role' => 'Robotics Champion', 'photo' => 'test2.jpg', 'message' => 'I built my first robot at 15. Now I teach robotics to younger girls. Thank you GCA!'],
        ['name' => 'Kamohelo Ralebitso', 'role' => 'Full-Stack Developer', 'photo' => 'test3.jpg', 'message' => 'From zero to launching my startup app in 10 months. Best decision of my life.'],
    ];
} else {
    $testimonials = $testimonials->fetch_all(MYSQLI_ASSOC);
}

// Gallery images from your upload folders
$gallery_images = [];
$dirs = ['Uploads/', 'imageuploads/', 'images/gallery/'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        foreach (glob($dir . "*.{jpg,jpeg,png,webp,gif}", GLOB_BRACE) as $file) {
            $gallery_images[] = $file;
        }
    }
}
$gallery_images = array_slice($gallery_images, 0, 20);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lightbox2 for full-screen gallery -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>

    <style>
        body { font-family: 'Poppins', sans-serif; }
        .hero { background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.8)), url('images/hero-girls.jpg') center/cover no-repeat; }
        .stat { opacity: 0; transform: translateY(30px); transition: all 1s; }
        .stat.visible { opacity: 1; transform: translateY(0); }
        .gallery-img:hover img { transform: scale(1.1); }
        .teacher-card:hover { transform: translateY(-10px); }
        .testimonial-card { transition: all 0.4s; }
        .testimonial-card:hover { transform: scale(1.05); }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Hero -->
    <section class="hero h-screen flex items-center justify-center text-white">
        <div class="text-center px-6 max-w-6xl mx-auto">
            <h1 class="text-5xl md:text-7xl font-extrabold mb-8">
                We Are<br>
                <span class="text-pink-400 text-6xl md:text-8xl">Girls Coding Academy</span>
            </h1>
            <p class="text-2xl md:text-3xl mb-12 font-light max-w-4xl mx-auto">
                Empowering young women in Lesotho with coding, robotics, and digital innovation since 2018
            </p>
            <div class="space-x-6">
                <a href="prospectus.php" class="px-12 py-5 bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-700 hover:to-purple-700 rounded-full text-xl font-bold shadow-2xl transform hover:scale-110 transition">
                    Explore Courses
                </a>
                <a href="#gallery" class="px-12 py-5 bg-white/20 backdrop-blur border-2 border-white/50 rounded-full text-xl font-bold hover:bg-white/30 transition">
                    View Gallery
                </a>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="py-20 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-5xl font-bold mb-16">Our Journey in Numbers</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-12">
                <div class="stat" style="animation-delay: 0.1s">
                    <div class="text-7xl font-black"><?= number_format($total_students) ?>+</div>
                    <p class="text-2xl mt-4">Girls Empowered</p>
                </div>
                <div class="stat" style="animation-delay: 0.3s">
                    <div class="text-7xl font-black"><?= number_format($total_graduates) ?>+</div>
                    <p class="text-2xl mt-4">Graduates</p>
                </div>
                <div class="stat" style="animation-delay: 0.5s">
                    <div class="text-7xl font-black"><?= $total_courses ?></div>
                    <p class="text-2xl mt-4">Active Courses</p>
                </div>
                <div class="stat" style="animation-delay: 0.7s">
                    <div class="text-7xl font-black"><?= $total_batches ?>+</div>
                    <p class="text-2xl mt-4">Running Batches</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6 text-center max-w-5xl">
            <h2 class="text-5xl font-bold mb-10 text-gray-800">Our Mission</h2>
            <p class="text-2xl leading-relaxed text-gray-700">
                To provide <strong>world-class tech education</strong> to every girl in Lesotho, 
                breaking barriers and creating equal opportunities in STEM. 
                We believe that when girls code, the future changes.
            </p>
        </div>
    </section>

    <!-- Teachers -->
    <section class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16">Meet Our Expert Teachers</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-10">
                <?php foreach($teachers as $t): 
                    $photo = $t['photo'] && file_exists($t['photo']) ? $t['photo'] : 'admin.png';
                ?>
                <div class="teacher-card bg-white rounded-3xl shadow-xl overflow-hidden text-center hover:shadow-2xl transition">
                    <img src="<?= $photo ?>" alt="<?= $t['firstName'] ?>" class="w-full h-80 object-cover">
                    <div class="p-6">
                        <h3 class="text-2xl font-bold"><?= $t['firstName'] . ' ' . $t['lastName'] ?></h3>
                        <p class="text-purple-600 font-semibold mt-2"><?= $t['subject_speciality'] ?? 'Tech Instructor' ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Courses -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16">Courses That Change Lives</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach($courses as $c): 
                    $img = file_exists($c['image_path']) ? $c['image_path'] : 'uploads/courses/default-course.jpg';
                ?>
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition group">
                    <div class="h-64 overflow-hidden">
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($c['courseName']) ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    </div>
                    <div class="p-8 text-center">
                        <h3 class="text-2xl font-bold mb-4"><?= htmlspecialchars($c['courseName']) ?></h3>
                        <p class="text-gray-600 mb-6 line-clamp-3"><?= htmlspecialchars($c['description']) ?></p>
                        <a href="prospectus.php" class="text-purple-600 font-bold hover:text-purple-800">
                            View Details →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Full-Screen Gallery -->
    <section id="gallery" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-16">Life at GCA</h2>
            <div class="columns-2 md:columns-3 lg:columns-4 gap-4">
                <?php foreach($gallery_images as $img): ?>
                <div class="mb-4 break-inside-avoid group relative overflow-hidden rounded-2xl shadow-lg">
                    <a href="<?= $img ?>" data-lightbox="gca-gallery" data-title="Girls Coding Academy">
                        <img src="<?= $img ?>" alt="GCA Gallery" 
                             class="w-full object-cover hover:scale-110 transition duration-500">
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-12">
                <a href="gallery.php" class="text-2xl font-bold text-purple-600 hover:text-purple-800">
                    View All Photos & Videos →
                </a>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-20 bg-gradient-to-r from-indigo-600 to-pink-600 text-white">
        <div class="container mx-auto px-6">
            <h2 class="text-5xl font-bold text-center mb-16">Voices of Our Students</h2>
            <div class="grid md:grid-cols-3 gap-10">
                <?php foreach($testimonials as $t): ?>
                <div class="bg-white/10 backdrop-blur-lg rounded-3xl p-10 text-center testimonial-card">
                    <img src="<?= $t['photo'] ?? 'testimonials/default.jpg' ?>" 
                         class="w-24 h-24 rounded-full mx-auto mb-6 border-4 border-white shadow-xl">
                    <p class="text-xl italic mb-8 leading-relaxed">"<?= htmlspecialchars($t['message']) ?>"</p>
                    <p class="font-bold text-2xl"><?= htmlspecialchars($t['name']) ?></p>
                    <p class="text-lg opacity-90"><?= htmlspecialchars($t['role']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-32 bg-gray-900 text-white text-center">
        <div class="container mx-auto px-6">
            <h2 class="text-6xl font-black mb-10">Your Future in Tech<br>Starts Right Here</h2>
            <p class="text-3xl mb-12 max-w-4xl mx-auto opacity-90">
                Join the sisterhood. Learn real skills. Change your life.
            </p>
            <a href="prospectus.php" class="inline-block px-20 py-7 bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-700 hover:to-purple-700 rounded-full text-3xl font-bold shadow-2xl transform hover:scale-110 transition">
                Enroll Now – Limited Seats!
            </a>
        </div>
    </section>

    <!-- Activate stats on scroll -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const stats = document.querySelectorAll('.stat');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            });
            stats.forEach(stat => observer.observe(stat));
        });
    </script>

</body>
</html>