<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
try {
    require_once 'db.php'; // Change to 'db.php' if you keep that name
} catch (Exception $e) {
    die("Failed to load database configuration: " . $e->getMessage());
}

// Ensure $pdo is defined
if (!isset($pdo)) {
    die("Database connection not established. Please check config.php.");
}

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
try {
    if ($role == 'student') {
        $stmt = $pdo->prepare("SELECT s.photo, sm.blood_type, sm.allergies, sm.emergency_contact_name, sm.emergency_contact_phone, 
                               st.transport_mode, st.route_number, st.pick_up_point, st.drop_off_point, st.guardian_contact 
                               FROM students s 
                               LEFT JOIN student_medical_info sm ON s.student_id = sm.student_id 
                               LEFT JOIN student_transport_info st ON s.student_id = st.student_id 
                               WHERE s.user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetch();
    } elseif ($role == 'teacher') {
        $stmt = $pdo->prepare("SELECT subject_speciality, photo FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetch();
    } elseif ($role == 'parent') {
        $stmt = $pdo->prepare("SELECT p.photo, ps.student_id, u2.firstName AS student_firstName, u2.lastName AS student_lastName 
                               FROM parents p 
                               LEFT JOIN parent_students ps ON p.parent_id = ps.parent_id 
                               LEFT JOIN students s ON ps.student_id = s.student_id 
                               LEFT JOIN users u2 ON s.user_id = u2.user_id 
                               WHERE p.user_id = ?");
        $stmt->execute([$user_id]);
        $extra_info = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Error fetching role-specific data: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $firstName = $_POST['firstName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address1 = $_POST['address1'] ?? '';
        $streetName = $_POST['streetName'] ?? '';
        $postalCode = $_POST['postalCode'] ?? '';
        $district = $_POST['district'] ?? '';
        $country = $_POST['country'] ?? '';

        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception("First name, last name, and email are required.");
        }

        // Update address
        $stmt = $pdo->prepare("UPDATE addresses SET address1 = ?, streetName = ?, postalCode = ?, district = ?, country = ?, updated_at = NOW() 
                               WHERE address_id = ?");
        $stmt->execute([$address1, $streetName, $postalCode, $district, $country, $user['address_id']]);

        // Update user
        $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ?, phone = ?, updated_at = NOW() 
                               WHERE user_id = ?");
        $stmt->execute([$firstName, $lastName, $email, $phone, $user_id]);

        // Handle file uploads (e.g., profile photo)
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
            } else {
                throw new Exception("Failed to upload profile photo.");
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
        body {
            background-color: #F3F4F6;
            font-family: Arial, sans-serif;
        }
        .profile-container {
            max-width: 800px;
            margin: 80px auto 20px;
            padding: 20px;
            background: #FFF;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #4B5EAA;
        }
        .section-title {
            color: #2B2D42;
            font-size: 1.5rem;
            margin: 20px 0 10px;
        }
        .form-control:focus {
            border-color: #9333EA;
            box-shadow: 0 0 0 0.2rem rgba(168, 85, 247, 0.25);
        }
        .btn-primary {
            background-color: #A855F7;
            border: none;
        }
        .btn-primary:hover {
            background-color: #9333EA;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'top_navigation.php'; ?>

    <div class="container profile-container">
        <div class="profile-header">
            <img src="<?php echo htmlspecialchars($extra_info['photo'] ?? ($user['photo'] ?? 'default_user.png')); ?>" alt="Profile" class="rounded-circle profile-img">
            <h2><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h2>
            <p class="text-muted"><?php echo ucfirst($role); ?></p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Profile Update Form -->
        <div class="card p-4 mb-4">
            <h3 class="section-title">Update Profile</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="address1" class="form-label">Address</label>
                        <input type="text" class="form-control" id="address1" name="address1" value="<?php echo htmlspecialchars($user['address1'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="streetName" class="form-label">Street Name</label>
                        <input type="text" class="form-control" id="streetName" name="streetName" value="<?php echo htmlspecialchars($user['streetName'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="postalCode" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="postalCode" name="postalCode" value="<?php echo htmlspecialchars($user['postalCode'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="district" class="form-label">District</label>
                        <input type="text" class="form-control" id="district" name="district" value="<?php echo htmlspecialchars($user['district'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="photo" class="form-label">Profile Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Update Profile</button>
            </form>
        </div>

        <!-- Role-Specific Information -->
        <?php if ($role == 'student'): ?>
            <div class="card p-4 mb-4">
                <h3 class="section-title">Student Information</h3>
                <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($extra_info['blood_type'] ?? 'N/A'); ?></p>
                <p><strong>Allergies:</strong> <?php echo htmlspecialchars($extra_info['allergies'] ?? 'N/A'); ?></p>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($extra_info['emergency_contact_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($extra_info['emergency_contact_phone'] ?? 'N/A'); ?>)</p>
                <p><strong>Transport Mode:</strong> <?php echo htmlspecialchars($extra_info['transport_mode'] ?? 'N/A'); ?></p>
                <p><strong>Route Number:</strong> <?php echo htmlspecialchars($extra_info['route_number'] ?? 'N/A'); ?></p>
                <p><strong>Pick-up/Drop-off Point:</strong> <?php echo htmlspecialchars($extra_info['pick_up_point'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($extra_info['drop_off_point'] ?? 'N/A'); ?></p>
                <h4>Course Progress</h4>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT c.courseName, b.batch_code, ig.test_1, ig.test_2 
                                           FROM course_enrollments ce 
                                           JOIN batches b ON ce.batch_id = b.batch_id 
                                           JOIN courses c ON b.course_id = c.course_id 
                                           LEFT JOIN internal_grades ig ON ce.student_id = ig.student_id AND ce.batch_id = ig.batch_id 
                                           WHERE ce.student_id = (SELECT student_id FROM students WHERE user_id = ?)");
                    $stmt->execute([$user_id]);
                    $courses = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Error fetching course progress: " . htmlspecialchars($e->getMessage()) . "</div>";
                    $courses = [];
                }
                ?>
                <ul>
                    <?php foreach ($courses as $course): ?>
                        <li><?php echo htmlspecialchars($course['courseName'] . ' (' . $course['batch_code'] . ')'); ?>
                            - Test 1: <?php echo htmlspecialchars($course['test_1'] ?? 'N/A'); ?>, Test 2: <?php echo htmlspecialchars($course['test_2'] ?? 'N/A'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($role == 'teacher'): ?>
            <div class="card p-4 mb-4">
                <h3 class="section-title">Teacher Information</h3>
                <p><strong>Subject Speciality:</strong> <?php echo htmlspecialchars($extra_info['subject_speciality'] ?? 'N/A'); ?></p>
                <h4>Assigned Batches</h4>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT b.batch_code, c.courseName 
                                           FROM course_assignments ca 
                                           JOIN batches b ON ca.batch_id = b.batch_id 
                                           JOIN courses c ON b.course_id = c.course_id 
                                           WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)");
                    $stmt->execute([$user_id]);
                    $batches = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Error fetching assigned batches: " . htmlspecialchars($e->getMessage()) . "</div>";
                    $batches = [];
                }
                ?>
                <ul>
                    <?php foreach ($batches as $batch): ?>
                        <li><?php echo htmlspecialchars($batch['courseName'] . ' (' . $batch['batch_code'] . ')'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="upload_material.php" class="btn btn-primary">Upload Construction Material</a>
            </div>
        <?php elseif ($role == 'parent'): ?>
            <div class="card p-4 mb-4">
                <h3 class="section-title">Parent Information</h3>
                <h4>Linked Students</h4>
                <ul>
                    <?php foreach ($extra_info as $student): ?>
                        <li><?php echo htmlspecialchars($student['student_firstName'] . ' ' . $student['student_lastName'] ?? 'N/A'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="parent_messages.php" class="btn btn-primary">View Messages</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>