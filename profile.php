<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT u.*, a.address1, a.streetName, a.postalCode, a.district, a.country 
                          FROM users u 
                          LEFT JOIN addresses a ON u.address_id = a.address_id 
                          WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        die("User not found.");
    }
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Role-specific data
$extra_info = [];
$student_id = $teacher_id = $parent_id = null;

try {
    if ($role == 'student') {
        $stmt = $pdo->prepare("SELECT s.student_id, s.photo, sm.blood_type, sm.allergies, sm.emergency_contact_name, sm.emergency_contact_phone, 
                              st.transport_mode, st.route_number, st.pick_up_point, st.drop_off_point, st.guardian_contact 
                              FROM students s 
                              LEFT JOIN student_medical_info sm ON s.student_id = sm.student_id 
                              LEFT JOIN student_transport_info st ON s.student_id = st.student_id 
                              WHERE s.user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetch();
        $student_id = $extra_info['student_id'] ?? null;
    } elseif ($role == 'teacher') {
        $stmt = $pdo->prepare("SELECT t.teacher_id, t.subject_speciality, t.photo FROM teachers t WHERE t.user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetch();
        $teacher_id = $extra_info['teacher_id'] ?? null;
    } elseif ($role == 'parent') {
        $stmt = $pdo->prepare("SELECT p.parent_id, p.photo FROM parents p WHERE p.user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetch();
        $parent_id = $extra_info['parent_id'] ?? null;
    }
} catch (PDOException $e) {
    $error = "Error fetching role-specific data: " . $e->getMessage();
}

// Fetch additional role-specific data
$courses = $batches = $linked_students = [];
if ($role == 'student' && $student_id) {
    // Student courses
    $stmt = $pdo->prepare("SELECT c.courseName, b.batch_code, ig.test_1, ig.test_2, ce.status
                          FROM course_enrollments ce 
                          JOIN batches b ON ce.batch_id = b.batch_id 
                          JOIN courses c ON b.course_id = c.course_id 
                          LEFT JOIN internal_grades ig ON ce.student_id = ig.student_id AND ce.batch_id = ig.batch_id 
                          WHERE ce.student_id = ?");
    $stmt->execute([$student_id]);
    $courses = $stmt->fetchAll();
} elseif ($role == 'teacher' && $teacher_id) {
    // Teacher batches
    $stmt = $pdo->prepare("SELECT b.batch_code, c.courseName 
                          FROM course_assignments ca 
                          JOIN batches b ON ca.batch_id = b.batch_id 
                          JOIN courses c ON b.course_id = c.course_id 
                          WHERE ca.teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $batches = $stmt->fetchAll();
} elseif ($role == 'parent' && $parent_id) {
    // Parent linked students
    $stmt = $pdo->prepare("SELECT u2.firstName AS student_firstName, u2.lastName AS student_lastName, s.student_id
                          FROM parent_students ps 
                          JOIN students s ON ps.student_id = s.student_id 
                          JOIN users u2 ON s.user_id = u2.user_id 
                          WHERE ps.parent_id = ?");
    $stmt->execute([$parent_id]);
    $linked_students = $stmt->fetchAll();
}

// Handle profile update
$success_message = $_GET['success'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address1 = trim($_POST['address1'] ?? '');
        $streetName = trim($_POST['streetName'] ?? '');
        $postalCode = trim($_POST['postalCode'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $country = trim($_POST['country'] ?? '');

        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception("First name, last name, and email are required.");
        }

        // Update address if exists
        if ($user['address_id']) {
            $stmt = $pdo->prepare("UPDATE addresses SET address1 = ?, streetName = ?, postalCode = ?, district = ?, country = ?, updated_at = NOW() 
                                  WHERE address_id = ?");
            $stmt->execute([$address1, $streetName, $postalCode, $district, $country, $user['address_id']]);
        }

        // Update user
        $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ?, phone = ?, updated_at = NOW() 
                              WHERE user_id = ?");
        $stmt->execute([$firstName, $lastName, $email, $phone, $user_id]);

        // Handle file upload
        if (!empty($_FILES['photo']['name'])) {
            $upload_dir = 'imageuploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $photo_name = time() . '_' . basename($_FILES['photo']['name']);
            $photo_path = $upload_dir . $photo_name;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $table = ($role == 'student') ? 'students' : (($role == 'teacher') ? 'teachers' : 'parents');
                $stmt = $pdo->prepare("UPDATE $table SET photo = ? WHERE user_id = ?");
                $stmt->execute([$photo_path, $user_id]);
                $_SESSION['photo'] = $photo_path;
            }
        }

        header("Location: profile.php?success=Profile updated successfully");
        exit();
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .profile-container { max-width: 900px; margin: 80px auto 20px; padding: 30px; background: #FFF; border-radius: 15px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-img { width: 140px; height: 140px; object-fit: cover; border: 4px solid #4B5EAA; border-radius: 50%; }
        .section-title { color: #2B2D42; font-size: 1.6rem; font-weight: 700; margin: 25px 0 15px; }
        .form-control:focus { border-color: #9333EA; box-shadow: 0 0 0 0.25rem rgba(168, 85, 247, 0.25); }
        .btn-primary { background: linear-gradient(135deg, #A855F7 0%, #9333EA 100%); border: none; font-weight: 600; }
        .btn-primary:hover { background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); transform: translateY(-1px); }
        .info-card { background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%); border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .role-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); padding: 8px 20px; border-radius: 25px; color: white; font-weight: 600; }
    </style>
</head>
<body>
    <?php include 'top_navigation.php'; ?>

    <div class="container profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?= htmlspecialchars($extra_info['photo'] ?? $user['photo'] ?? 'https://via.placeholder.com/140x140/4B5EAA/FFFFFF?text=👤') ?>" 
                 alt="Profile Photo" class="profile-img mb-3 shadow-lg">
            <h2 class="h1 fw-bold text-dark mb-2"><?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?></h2>
            <p class="text-muted fs-5 mb-1"><?= ucfirst($role) ?></p>
            <span class="role-badge"><?= strtoupper($role) ?></span>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Update Form -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h3 class="section-title mb-4"><i class="bi bi-pencil-square text-primary me-2"></i>Update Profile</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="firstName" value="<?= htmlspecialchars($user['firstName'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="lastName" value="<?= htmlspecialchars($user['lastName'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address1" value="<?= htmlspecialchars($user['address1'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Street</label>
                            <input type="text" class="form-control" name="streetName" value="<?= htmlspecialchars($user['streetName'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Postal Code</label>
                            <input type="text" class="form-control" name="postalCode" value="<?= htmlspecialchars($user['postalCode'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">District</label>
                            <input type="text" class="form-control" name="district" value="<?= htmlspecialchars($user['district'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" class="form-control" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg mt-4 px-5">
                        <i class="bi bi-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Role-Specific Information -->
        <?php if ($role == 'student'): ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="info-card">
                        <h4 class="section-title mb-3"><i class="bi bi-heart-pulse text-danger me-2"></i>Medical Information</h4>
                        <div class="row g-3">
                            <div class="col-6"><strong>Blood Type:</strong> <?= htmlspecialchars($extra_info['blood_type'] ?? 'N/A') ?></div>
                            <div class="col-6"><strong>Allergies:</strong> <?= htmlspecialchars($extra_info['allergies'] ?? 'None') ?></div>
                            <div class="col-12"><strong>Emergency:</strong> <?= htmlspecialchars($extra_info['emergency_contact_name'] ?? 'N/A') ?> (<?= htmlspecialchars($extra_info['emergency_contact_phone'] ?? 'N/A') ?>)</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="info-card">
                        <h4 class="section-title mb-3"><i class="bi bi-bus-front text-info me-2"></i>Transport Details</h4>
                        <div class="row g-3">
                            <div class="col-6"><strong>Mode:</strong> <?= htmlspecialchars($extra_info['transport_mode'] ?? 'N/A') ?></div>
                            <div class="col-6"><strong>Route:</strong> <?= htmlspecialchars($extra_info['route_number'] ?? 'N/A') ?></div>
                            <div class="col-6"><strong>Pickup:</strong> <?= htmlspecialchars($extra_info['pick_up_point'] ?? 'N/A') ?></div>
                            <div class="col-6"><strong>Drop-off:</strong> <?= htmlspecialchars($extra_info['drop_off_point'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($courses)): ?>
            <div class="info-card mt-4">
                <h4 class="section-title mb-3"><i class="bi bi-book-half text-success me-2"></i>Course Progress (<?= count($courses) ?>)</h4>
                <div class="row g-3">
                    <?php foreach ($courses as $course): ?>
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <div class="fw-bold"><?= htmlspecialchars($course['courseName']) ?></div>
                            <small class="text-muted">Batch: <?= htmlspecialchars($course['batch_code']) ?></small>
                            <div class="mt-2">
                                <small>Test 1: <?= htmlspecialchars($course['test_1'] ?? 'N/A') ?></small> | 
                                <small>Test 2: <?= htmlspecialchars($course['test_2'] ?? 'N/A') ?></small>
                            </div>
                            <span class="badge bg-<?= strtolower($course['status'] ?? 'secondary') ?>"><?= ucfirst($course['status'] ?? 'Unknown') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($role == 'teacher'): ?>
            <div class="info-card">
                <h4 class="section-title mb-3"><i class="bi bi-easel2 text-primary me-2"></i>Teaching Information</h4>
                <p class="fs-5 fw-semibold text-dark mb-3"><?= htmlspecialchars($extra_info['subject_speciality'] ?? 'N/A') ?></p>
                <?php if (!empty($batches)): ?>
                <h5 class="mb-3">Assigned Batches (<?= count($batches) ?>)</h5>
                <ul class="list-unstyled">
                    <?php foreach ($batches as $batch): ?>
                    <li class="p-2 border-start border-primary ps-3 mb-2">
                        <strong><?= htmlspecialchars($batch['courseName']) ?></strong> (<?= htmlspecialchars($batch['batch_code']) ?>)
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted">No batches assigned yet.</p>
                <?php endif; ?>
                <a href="upload_material.php" class="btn btn-outline-primary mt-3">
                    <i class="bi bi-upload me-2"></i>Upload Material
                </a>
            </div>

        <?php elseif ($role == 'parent'): ?>
            <div class="info-card">
                <h4 class="section-title mb-3"><i class="bi bi-people text-purple me-2"></i>Linked Students</h4>
                <?php if (!empty($linked_students)): ?>
                <ul class="list-unstyled">
                    <?php foreach ($linked_students as $student): ?>
                    <li class="p-3 border rounded mb-2">
                        <div class="fw-bold"><?= htmlspecialchars($student['student_firstName'] . ' ' . $student['student_lastName']) ?></div>
                        <small class="text-muted">Student ID: <?= htmlspecialchars($student['student_id']) ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted">No students linked yet.</p>
                <?php endif; ?>
                <a href="parent_messages.php" class="btn btn-outline-primary">
                    <i class="bi bi-chat-dots me-2"></i>View Messages
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
