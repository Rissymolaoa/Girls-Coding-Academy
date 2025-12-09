<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id === 0) {
    header("Location: manage_users.php");
    exit();
}

/* MAIN USER + PROFILE PHOTO */
$stmt = $conn->prepare("
    SELECT 
        u.*,
        a.address1, a.streetName, a.district, a.country, a.postalCode,
        COALESCE(s.photo, t.photo, p.photo) AS profile_photo,
        t.subject_speciality,
        s.student_id, t.teacher_id, p.parent_id
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    LEFT JOIN parents p ON u.user_id = p.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Safe defaults
$firstName = trim($user['firstName'] ?? '') ?: 'Unknown';
$lastName  = trim($user['lastName'] ?? '') ?: 'User';
$username  = trim($user['username'] ?? '') ?: 'unknown';
$role      = ucfirst($user['role'] ?? 'unknown');
$status    = ucfirst($user['status'] ?? 'unknown');

// Initials
$firstInitial = $firstName !== 'Unknown' ? mb_strtoupper(mb_substr($firstName, 0, 1)) : '?';
$lastInitial  = $lastName !== 'User' ? mb_strtoupper(mb_substr($lastName, 0, 1)) : '?';
$initials = $firstInitial . $lastInitial;

// Photo path check
$photoPath = $user['profile_photo'] ?? null;
if ($photoPath && in_array(strtolower($photoPath), ['null', '']) || !file_exists($photoPath)) {
    $photoPath = null;
}
$photoExists = $photoPath !== null;

// Role-specific data fetches
$student_medical = $student_transport = $parent_link = null;
if ($user['student_id']) {
    $student_medical = $conn->query("SELECT * FROM student_medical_info WHERE student_id = " . (int)$user['student_id'])->fetch_assoc();
    $student_transport = $conn->query("SELECT * FROM student_transport_info WHERE student_id = " . (int)$user['student_id'])->fetch_assoc();
    $parent_link = $conn->query("
        SELECT p.*, u.firstName AS p_first, u.lastName AS p_last 
        FROM parent_students ps 
        JOIN parents p ON ps.parent_id = p.parent_id 
        JOIN users u ON p.user_id = u.user_id 
        WHERE ps.student_id = " . (int)$user['student_id']
    )->fetch_assoc();
}

// Enrolled courses for student
$courses = [];
if ($user['student_id']) {
    $courses_res = $conn->query("
        SELECT ce.enrollment_id, b.batch_code, c.courseName, b.start_date, ce.status 
        FROM course_enrollments ce
        JOIN batches b ON ce.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE ce.student_id = " . (int)$user['student_id'] . "
        ORDER BY ce.enrolled_at DESC
    ");
    if ($courses_res) {
        $courses = $courses_res->fetch_all(MYSQLI_ASSOC);
    }
}

// Attendance
$attendance_summary = [];
if ($user['student_id']) {
    $att_res = $conn->query(
        "SELECT status, COUNT(*) as count FROM attendance WHERE student_id = " . (int)$user['student_id'] . " GROUP BY status"
    );
    if ($att_res) {
        while ($row = $att_res->fetch_assoc()) {
            $attendance_summary[$row['status']] = $row['count'];
        }
    }
    $total_sessions = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE student_id = " . (int)$user['student_id'])->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars("$firstName $lastName") ?> - Full Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .main-content { margin-left: 220px; padding-top: 80px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'top_navigation.php'; ?>
    <?php include 'admin_navigation.php'; ?>

    <div class="main-content p-6 min-h-screen max-w-7xl mx-auto">

        <!-- Back Button -->
        <a href="manage_users.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-bold mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to Users
        </a>

        <!-- Header Card -->
        <div class="section flex flex-col md:flex-row items-center gap-6">
            <?php if ($photoExists): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Profile Photo" class="w-40 h-40 rounded-full border-4 border-white shadow-2xl object-cover" />
            <?php else: ?>
                <div class="w-40 h-40 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-6xl font-bold shadow-2xl">
                    <?= $initials ?>
                </div>
            <?php endif; ?>

            <div class="text-center md:text-left flex-grow">
                <h1 class="text-4xl font-bold text-gray-800"><?= htmlspecialchars("$firstName $lastName") ?></h1>
                <p class="text-2xl text-gray-600 mt-1">@<?= htmlspecialchars($username) ?></p>
                <div class="flex flex-wrap gap-3 mt-4 justify-center md:justify-start">
                    <span class="px-5 py-2 rounded-full text-white font-bold text-sm <?= $user['role']=='admin'?'bg-red-600':($user['role']=='teacher'?'bg-blue-600':($user['role']=='parent'?'bg-purple-600':'bg-green-600')) ?>">
                        <?= $role ?>
                    </span>
                    <span class="px-5 py-2 rounded-full text-white font-bold text-sm <?= $status=='Active'?'bg-green-600':($status=='Pending'?'bg-yellow-600':($status=='Waitlist'?'bg-orange-600':'bg-red-600')) ?>">
                        <?= $status ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Personal Info -->
        <div class="section">
            <h2 class="text-2xl font-bold mb-5 text-blue-700"><i class="fas fa-user mr-2"></i> Personal Details</h2>
            <div class="space-y-3 text-gray-700">
                <div><strong>ID Number:</strong> <?= htmlspecialchars($user['IDNumber'] ?? 'Not set') ?></div>
                <div><strong>Gender:</strong> <?= ucfirst($user['gender'] ?? 'Not set') ?></div>
                <div><strong>Date of Birth:</strong> <?= !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : 'Not set' ?></div>
                <div><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Not provided') ?></div>
                <div><strong>Created:</strong> <?= !empty($user['created_at']) ? date('d M Y, h:i A', strtotime($user['created_at'])) : 'Not set' ?></div>
            </div>
        </div>

        <!-- Address -->
        <div class="section">
            <h2 class="text-2xl font-bold mb-5 text-blue-700"><i class="fas fa-home mr-2"></i> Address</h2>
            <div class="text-gray-700 space-y-2">
                <div><?= htmlspecialchars($user['address1'] ?? '—') ?></div>
                <div><?= htmlspecialchars($user['streetName'] ?? '') ?></div>
                <div><?= htmlspecialchars($user['district'] ?? '') ?>, <?= htmlspecialchars($user['country'] ?? '') ?></div>
                <div>Postal Code: <?= htmlspecialchars($user['postalCode'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Student-Specific Info -->
        <?php if ($user['role'] === 'student' && $user['student_id']): ?>
            <?php if ($student_medical): ?>
                <div class="section">
                    <h2 class="text-2xl font-bold mb-5 text-red-700"><i class="fas fa-heartbeat mr-2"></i> Medical Info</h2>
                    <div class="space-y-2 text-gray-700">
                        <div><strong>Blood Type:</strong> <?= htmlspecialchars($student_medical['blood_type'] ?? '—') ?></div>
                        <div><strong>Allergies:</strong> <?= htmlspecialchars($student_medical['allergies'] ?? 'None') ?></div>
                        <div><strong>Conditions:</strong> <?= htmlspecialchars($student_medical['chronic_conditions'] ?? 'None') ?></div>
                        <div><strong>Medications:</strong> <?= htmlspecialchars($student_medical['medications'] ?? 'None') ?></div>
                        <div><strong>Emergency Contact:</strong> <?= htmlspecialchars($student_medical['emergency_contact_name'] ?? '—') ?> (<?= htmlspecialchars($student_medical['emergency_contact_phone'] ?? '') ?>)</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($student_transport): ?>
                <div class="section">
                    <h2 class="text-2xl font-bold mb-5 text-indigo-700"><i class="fas fa-bus mr-2"></i> Transport</h2>
                    <div class="space-y-2 text-gray-700">
                        <div><strong>Mode:</strong> <?= htmlspecialchars($student_transport['transport_mode'] ?? '—') ?></div>
                        <div><strong>Route/Plate:</strong> <?= htmlspecialchars($student_transport['route_number'] ?? '—') ?></div>
                        <div><strong>Pickup:</strong> <?= htmlspecialchars($student_transport['pick_up_point'] ?? '—') ?></div>
                        <div><strong>Drop-off:</strong> <?= htmlspecialchars($student_transport['drop_off_point'] ?? '—') ?></div>
                        <?php if (!empty($student_transport['transport_image']) && file_exists($student_transport['transport_image'])): ?>
                            <img src="<?= htmlspecialchars($student_transport['transport_image']) ?>" class="mt-3 rounded-lg shadow-md max-w-full h-48 object-cover" alt="Transport Image" />
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($parent_link): ?>
                <div class="section">
                    <h2 class="text-2xl font-bold mb-5 text-purple-700"><i class="fas fa-users mr-2"></i> Linked Parent</h2>
                    <div class="text-gray-700">
                        <div><strong>Name:</strong> <?= htmlspecialchars($parent_link['p_first'] . ' ' . $parent_link['p_last']) ?></div>
                        <div><strong>Phone:</strong> <?= htmlspecialchars($parent_link['phone'] ?? '—') ?></div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($parent_link['email'] ?? '—') ?></div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Teacher Speciality -->
        <?php if ($user['role'] === 'teacher' && !empty($user['subject_speciality'])): ?>
            <div class="section">
                <h2 class="text-2xl font-bold mb-5 text-blue-700"><i class="fas fa-chalkboard-teacher mr-2"></i> Teaching</h2>
                <div class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($user['subject_speciality']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Enrolled Courses -->
        <?php if (!empty($courses)): ?>
            <div class="section xl:col-span-3">
                <h2 class="text-2xl font-bold mb-5 text-green-700"><i class="fas fa-graduation-cap mr-2"></i> Enrolled Courses (<?= count($courses) ?>)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($courses as $c): ?>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="font-bold text-lg"><?= htmlspecialchars($c['courseName']) ?></div>
                            <div class="text-sm text-gray-600">Batch: <?= htmlspecialchars($c['batch_code']) ?></div>
                            <div class="text-sm">Started: <?= date('d M Y', strtotime($c['start_date'])) ?></div>
                            <div class="mt-2">
                                <span class="px-3 py-1 rounded-full text-xs font-bold <?= strtolower($c['status']) == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= ucfirst(htmlspecialchars($c['status'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance Summary -->
        <?php if ($user['role'] === 'student'): ?>
            <div class="section">
                <h2 class="text-2xl font-bold mb-5 text-orange-700"><i class="fas fa-calendar-check mr-2"></i> Attendance Summary</h2>
                <div class="space-y-3">
                    <?php if (!empty($attendance_summary)): ?>
                        <?php foreach ($attendance_summary as $status => $count): ?>
                            <div class="flex justify-between text-gray-700">
                                <span><?= htmlspecialchars($status ?: 'Not Marked') ?></span>
                                <span class="font-bold"><?= (int)$count ?> times</span>
                            </div>
                        <?php endforeach; ?>
                        <div class="border-t pt-3 font-bold text-lg">
                            Total Sessions: <?= (int)$total_sessions ?>
                        </div>
                    <?php else: ?>
                        <div class="text-gray-500">No attendance records found.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-10 text-center">
            <a href="manage_users.php" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-4 rounded-xl text-lg font-bold inline-flex items-center shadow-lg">
                <i class="fas fa-arrow-left mr-3"></i> Back to All Users
            </a>
        </div>
    </div>
</body>
</html>
