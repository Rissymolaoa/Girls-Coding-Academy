<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include("db.php");
include("student_navigation.php");

$user_id = $_SESSION['user_id'];

// Fetch current approved data
$stmt = $conn->prepare("
    SELECT u.*, a.*, s.student_id, s.photo,
           m.*, t.*
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN students s ON s.user_id = u.user_id
    LEFT JOIN student_medical_info m ON m.student_id = s.student_id
    LEFT JOIN student_transport_info t ON t.student_id = s.student_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$student_id = $student['student_id'] ?? null;

// Check if there's a pending request
$pending_count = 0;
if ($student_id) {
    $pending = $conn->query("SELECT COUNT(*) as cnt FROM student_profile_updates WHERE student_id = $student_id AND status = 'pending'")->fetch_assoc();
    $pending_count = $pending['cnt'];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];

    // Compare each field
    $fields = [
        'firstName' => 'First Name',
        'lastName' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'gender' => 'Gender',
        'IDNumber' => 'ID Number',
        'address1' => 'House/Flat No.',
        'streetName' => 'Street Name',
        'postalCode' => 'Postal Code',
        'district' => 'District',
        'country' => 'Country',
        'transport_mode' => 'Transport Mode',
        'route_number' => 'Route Number',
        'pick_up_point' => 'Pick-up Point',
        'drop_off_point' => 'Drop-off Point',
        'guardian_contact' => 'Guardian Contact',
        'blood_type' => 'Blood Type',
        'allergies' => 'Allergies',
        'chronic_conditions' => 'Chronic Conditions',
        'medications' => 'Medications',
        'emergency_contact_name' => 'Emergency Contact Name',
        'emergency_contact_phone' => 'Emergency Contact Phone'
    ];

    foreach ($fields as $field => $label) {
        $new_val = trim($_POST[$field] ?? '');
        $old_val = $student[$field] ?? '';

        if ($new_val !== $old_val && $new_val !== '') {
            $updates[] = [
                'field' => $field,
                'label' => $label,
                'old' => $old_val,
                'new' => $new_val
            ];
        }
    }

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && $student_id) {
        $upload_dir = 'uploads/students/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = $student_id . '_photo_' . time() . '.' . $ext;
        $path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $path)) {
            $updates[] = [
                'field' => 'photo',
                'label' => 'Profile Photo',
                'old' => $student['photo'] ?? 'None',
                'new' => $path
            ];
        }
    }

    if (!empty($updates)) {
        $stmt = $conn->prepare("INSERT INTO student_profile_updates (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
        foreach ($updates as $u) {
            $stmt->bind_param("isss", $student_id, $u['field'], $u['old'], $u['new']);
            $stmt->execute();
        }
        $message = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-6 py-4 rounded-xl mb-6">
                        <strong>Request Sent!</strong> Your profile update has been sent for admin approval. You will be notified when approved.
                    </div>';
    } else {
        $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-6 py-4 rounded-xl mb-6">
                        No changes detected.
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile • GCA Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #7b2cbf; --secondary: #5a189a; --accent: #c084fc; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f2ff 0%, #e0e7ff 100%); min-height: 100vh; }
        .card { border: none; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(123, 44, 191, 0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 2rem; text-align: center; }
        .card-header h2 { margin: 0; font-size: 2rem; font-weight: 700; }
        .profile-img { width: 140px; height: 140px; object-fit: cover; border: 6px solid white; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transition: all 0.3s; }
        .profile-img:hover { transform: scale(1.05); }
        .form-label { font-weight: 600; color: #4c1d95; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 16px; border: 2px solid #e0d4ff; transition: all 0.3s; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(123, 44, 191, 0.15); }
        .btn-update { background: linear-gradient(135deg, #27ae60, #1e8449); border: none; padding: 14px 40px; border-radius: 50px; font-weight: 600; font-size: 1.1rem; }
        .btn-update:hover { background: linear-gradient(135deg, #1e8449, #1a6e3d); transform: translateY(-3px); box-shadow: 0 10px 25px rgba(39, 174, 96, 0.3); }
        .pending-badge { background: #fef3c7; color: #92400e; padding: 12px 24px; border-radius: 50px; font-weight: 600; }
        .section-title { color: var(--primary); font-weight: 700; font-size: 1.4rem; margin: 2rem 0 1.5rem; position: relative; padding-bottom: 10px; }
        .section-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 60px; height: 4px; background: var(--accent); border-radius: 2px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <img src="<?= htmlspecialchars($student['photo'] ?? 'admin.png') ?>" alt="Profile" class="rounded-circle profile-img mb-3">
                    <h2>My Profile</h2>
                    <p class="mb-0 opacity-90">Update your information • Changes require admin approval</p>
                </div>
                <div class="card-body p-5">
                    <?php if ($pending_count > 0): ?>
                        <div class="text-center mb-5">
                            <div class="pending-badge d-inline-block">
                                <i class="bi bi-clock-history fs-4"></i> You have <?= $pending_count ?> update<?= $pending_count > 1 ? 's' : '' ?> pending admin approval
                            </div>
                        </div>
                    <?php endif; ?>

                    <?= $message ?>

                    <form method="POST" enctype="multipart/form-data">
                        <!-- Photo -->
                        <div class="text-center mb-5">
                            <img src="<?= htmlspecialchars($student['photo'] ?? 'admin.png') ?>" alt="Profile" class="rounded-circle profile-img" id="previewImg">
                            <div class="mt-4">
                                <label class="btn btn-outline-primary px-5 py-3 rounded-pill">
                                    <i class="bi bi-camera"></i> Change Photo
                                    <input type="file" name="photo" class="d-none" accept="image/*" onchange="previewImage(this)">
                                </label>
                            </div>
                        </div>

                        <!-- Personal Info -->
                        <h3 class="section-title"><i class="bi bi-person-circle"></i> Personal Information</h3>
                        <div class="row g-4">
                            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="firstName" class="form-control" value="<?= htmlspecialchars($student['firstName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="lastName" class="form-control" value="<?= htmlspecialchars($student['lastName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Gender</label><input type="text" name="gender" class="form-control" value="<?= htmlspecialchars($student['gender'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">ID Number</label><input type="text" name="IDNumber" class="form-control" value="<?= htmlspecialchars($student['IDNumber'] ?? '') ?>"></div>
                        </div>

                        <!-- Address -->
                        <h3 class="section-title mt-5"><i class="bi bi-house-door"></i> Address Information</h3>
                        <div class="row g-4">
                            <div class="col-md-6"><label class="form-label">House/Flat No.</label><input type="text" name="address1" class="form-control" value="<?= htmlspecialchars($student['address1'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Street Name</label><input type="text" name="streetName" class="form-control" value="<?= htmlspecialchars($student['streetName'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Postal Code</label><input type="text" name="postalCode" class="form-control" value="<?= htmlspecialchars($student['postalCode'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">District</label><input type="text" name="district" class="form-control" value="<?= htmlspecialchars($student['district'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="<?= htmlspecialchars($student['country'] ?? '') ?>"></div>
                        </div>

                        <!-- Transport -->
                        <h3 class="section-title mt-5"><i class="bi bi-bus-front"></i> Transport Information</h3>
                        <div class="row g-4">
                            <div class="col-md-6"><label class="form-label">Transport Mode</label><input type="text" name="transport_mode" class="form-control" value="<?= htmlspecialchars($student['transport_mode'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Route Number</label><input type="text" name="route_number" class="form-control" value="<?= htmlspecialchars($student['route_number'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Pick-up Point</label><input type="text" name="pick_up_point" class="form-control" value="<?= htmlspecialchars($student['pick_up_point'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Drop-off Point</label><input type="text" name="drop_off_point" class="form-control" value="<?= htmlspecialchars($student['drop_off_point'] ?? '') ?>"></div>
                            <div class="col-md-12"><label class="form-label">Guardian Contact</label><input type="text" name="guardian_contact" class="form-control" value="<?= htmlspecialchars($student['guardian_contact'] ?? '') ?>"></div>
                        </div>

                        <!-- Medical -->
                        <h3 class="section-title mt-5"><i class="bi bi-heart-pulse"></i> Medical Information</h3>
                        <div class="row g-4">
                            <div class="col-md-4"><label class="form-label">Blood Type</label><input type="text" name="blood_type" class="form-control" value="<?= htmlspecialchars($student['blood_type'] ?? '') ?>"></div>
                            <div class="col-md-8"><label class="form-label">Allergies</label><input type="text" name="allergies" class="form-control" value="<?= htmlspecialchars($student['allergies'] ?? '') ?>"></div>
                            <div class="col-md-12"><label class="form-label">Chronic Conditions</label><input type="text" name="chronic_conditions" class="form-control" value="<?= htmlspecialchars($student['chronic_conditions'] ?? '') ?>"></div>
                            <div class="col-md-12"><label class="form-label">Medications</label><input type="text" name="medications" class="form-control" value="<?= htmlspecialchars($student['medications'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($student['emergency_contact_name'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($student['emergency_contact_phone'] ?? '') ?>"></div>
                        </div>

                        <div class="text-center mt-5">
                            <button type="submit" class="btn btn-update text-white">
                                <i class="bi bi-paper-plane"></i> Request Update (Admin Approval Required)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview uploaded photo
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewImg').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>