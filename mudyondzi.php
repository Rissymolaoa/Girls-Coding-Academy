<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); exit();
}
include("db.php");

// Upload directories
$uploadDir = __DIR__ . '/uploads/docs/';
$uploadWebPath = 'uploads/docs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$imageDir = __DIR__ . '/imageuploads/';
$imageWebPath = 'imageuploads/';
if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $res = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
    $res->bind_param("i", $del_id);
    $res->execute();
    $r = $res->get_result()->fetch_assoc();
    $address_id = $r['address_id'] ?? null;
    $res->close();

    $tables = ['student_medical_info', 'student_transport_info', 'students', 'users'];
    $id_fields = ['student_id', 'student_id', 'user_id', 'user_id'];
    foreach ($tables as $i => $table) {
        if ($i < 2) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE {$id_fields[$i]} = (SELECT student_id FROM students WHERE user_id = ? LIMIT 1)");
        } else {
            $stmt = $conn->prepare("DELETE FROM $table WHERE {$id_fields[$i]} = ?");
        }
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
    }
    if ($address_id) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt->bind_param("i", $address_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_students.php"); exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'medical') {
        $student_id = intval(post('student_id'));
        $blood_type = post('blood_type');
        $allergies = post('allergies');
        $chronic_conditions = post('chronic_conditions');
        $medications = post('medications');
        $emergency_contact_name = post('emergency_contact_name');
        $emergency_contact_phone = post('emergency_contact_phone');

        $stmt = $conn->prepare("SELECT medical_id FROM student_medical_info WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE student_medical_info SET blood_type=?, allergies=?, chronic_conditions=?, medications=?, emergency_contact_name=?, emergency_contact_phone=?, updated_at=NOW() WHERE student_id=?");
            $stmt->bind_param("ssssssi", $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone, $student_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO student_medical_info (student_id, blood_type, allergies, chronic_conditions, medications, emergency_contact_name, emergency_contact_phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issssss", $student_id, $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: mudyondzi.php"); exit();
    }

    if (isset($_POST['form_type']) && $_POST['form_type'] === 'transport') {
        $student_id = intval(post('student_id'));
        $transport_mode = post('transport_mode');
        $route_number = post('route_number');
        $pick_up_point = post('pick_up_point');
        $drop_off_point = post('drop_off_point');
        $guardian_contact = post('guardian_contact');
        $transportImagePath = null;

        if (isset($_FILES['transport_image']) && $_FILES['transport_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['transport_image']['name'], PATHINFO_EXTENSION));
            $allowedImg = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowedImg)) {
                $newName = time() . '_' . uniqid('trans_img_') . '.' . $ext;
                $absImg = $imageDir . $newName;
                if (move_uploaded_file($_FILES['transport_image']['tmp_name'], $absImg)) {
                    $transportImagePath = $imageWebPath . $newName;
                }
            }
        }

        $stmt = $conn->prepare("SELECT transport_id FROM student_transport_info WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            if ($transportImagePath !== null) {
                $stmt = $conn->prepare("UPDATE student_transport_info SET transport_mode=?, route_number=?, pick_up_point=?, drop_off_point=?, guardian_contact=?, transport_image=?, updated_at=NOW() WHERE student_id=?");
                $stmt->bind_param("ssssssi", $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $transportImagePath, $student_id);
            } else {
                $stmt = $conn->prepare("UPDATE student_transport_info SET transport_mode=?, route_number=?, pick_up_point=?, drop_off_point=?, guardian_contact=?, updated_at=NOW() WHERE student_id=?");
                $stmt->bind_param("sssssi", $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $student_id);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO student_transport_info (student_id, transport_mode, route_number, pick_up_point, drop_off_point, guardian_contact, transport_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issssss", $student_id, $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $transportImagePath ?? '');
        }
        $stmt->execute();
        $stmt->close();
        header("Location: mudyondzi.php"); exit();
    }

    // Student add/edit
    $user_id = post('user_id') ? intval(post('user_id')) : null;
    $username = post('username');
    $firstName = post('firstName');
    $lastName = post('lastName');
    $gender = post('gender');
    $dob = post('dob');
    $IDNumber = post('IDNumber');
    $phone = post('phone');
    $email = post('email');
    $password = post('password');
    $status = post('status') ?: 'active';
    $address1 = post('address1');
    $streetName = post('streetName');
    $postalCode = post('postalCode');
    $district = post('district');
    $country = post('country');

    $documentPath = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['pdf'])) {
            $newName = uniqid('doc_') . '.' . $ext;
            $absPath = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['document']['tmp_name'], $absPath)) {
                $documentPath = $uploadWebPath . $newName;
            }
        }
    }

    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $allowedImg = ['jpg','jpeg','png','gif','webp'];
        if (in_array(strtolower($ext), $allowedImg)) {
            $newName = time() . '_' . uniqid('img_') . '.' . $ext;
            $absImg = $imageDir . $newName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $absImg)) {
                $photoPath = $imageWebPath . $newName;
            }
        }
    }

    if ($user_id) {
        // EDIT
        $stmt = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $address_id = $row['address_id'] ?? null;
        $stmt->close();

        if ($photoPath) {
            $stmt = $conn->prepare("UPDATE students SET photo = ? WHERE user_id = ?");
            $stmt->bind_param("si", $photoPath, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($address_id) {
            $stmt = $conn->prepare("UPDATE addresses SET address1=?, streetName=?, postalCode=?, district=?, country=?, updated_at=NOW() WHERE address_id=?");
            $stmt->bind_param("sssssi", $address1, $streetName, $postalCode, $district, $country, $address_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
            $stmt->execute();
            $address_id = $conn->insert_id;
            $stmt->close();
            $stmt = $conn->prepare("UPDATE users SET address_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $address_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($documentPath && !empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, password=?, status=?, document=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("sssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $documentPath, $user_id);
        } elseif ($documentPath) {
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, status=?, document=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $status, $documentPath, $user_id);
        } elseif (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, password=?, status=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("ssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, status=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $status, $user_id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        // ADD
        $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
        $stmt->execute();
        $address_id = $conn->insert_id;
        $stmt->close();

        $hash = password_hash($password ?: uniqid(), PASSWORD_BCRYPT);
        $role = 'student';
        $stmt = $conn->prepare("INSERT INTO users (username, firstName, lastName, gender, dob, IDNumber, phone, email, password, status, role, document, address_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $role, $documentPath, $address_id);
        $stmt->execute();
        $new_user_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO students (user_id, photo) VALUES (?, ?)");
        $stmt->bind_param("is", $new_user_id, $photoPath);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: mudyondzi.php"); exit();
}

// Pagination and filtering
$limit = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$view = $_GET['view'] ?? 'grid';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$district = trim($_GET['district'] ?? '');
$batch = trim($_GET['batch'] ?? '');

$where = ["u.role = 'student'"];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "(u.firstName LIKE ? OR u.lastName LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    for ($i = 0; $i < 4; $i++) { $params[] = $like; $types .= "s"; }
}
if ($status !== '') { $where[] = "u.status = ?"; $params[] = $status; $types .= "s"; }
if ($district !== '') { $where[] = "a.district LIKE ?"; $params[] = "%$district%"; $types .= "s"; }
if ($batch !== '') { $where[] = "b.batch_code LIKE ?"; $params[] = "%$batch%"; $types .= "s"; }

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN addresses a ON u.address_id = a.address_id LEFT JOIN students s ON s.user_id = u.user_id LEFT JOIN course_enrollments ce ON ce.student_id = s.student_id LEFT JOIN batches b ON ce.batch_id = b.batch_id $whereClause";
$stmt = $conn->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

$sql = "SELECT u.*, s.photo, s.student_id, a.district, a.country, b.batch_code FROM users u LEFT JOIN students s ON s.user_id = u.user_id LEFT JOIN addresses a ON u.address_id = a.address_id LEFT JOIN course_enrollments ce ON ce.student_id = s.student_id AND ce.status = 'active' LEFT JOIN batches b ON ce.batch_id = b.batch_id $whereClause GROUP BY u.user_id ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types .= "ii";
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();

$districts = $conn->query("SELECT DISTINCT district FROM addresses WHERE district IS NOT NULL ORDER BY district")->fetch_all(MYSQLI_ASSOC);
$batches_list = $conn->query("SELECT DISTINCT batch_code FROM batches ORDER BY batch_code")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students • GCA Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #7b2cbf; --secondary: #5a189a; --accent: #c084fc; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f2ff 0%, #e0e7ff 100%); min-height: 100vh; }
        .gradient-header { background: linear-gradient(90deg, var(--primary), var(--secondary)); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #1e293b, #0f172a); position: fixed; height: 100vh; top: 0; left: 0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
        .card { border: none; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(123,44,191,0.1); }
        .student-card { transition: all 0.3s; }
        .student-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(123,44,191,0.15); }
        .filter-panel { background: white; border-radius: 1.5rem; padding: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn-view-toggle { background: rgba(123,44,191,0.1); color: var(--primary); border: none; }
        .btn-view-toggle.active { background: var(--primary); color: white; }
        .badge-status { padding: 8px 16px; border-radius: 50px; font-weight: 600; }
        .action-btn { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <?php include 'admin_navigation.php'; ?>
    <?php include 'top_navigation.php'; ?>


    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 fw-bold text-purple">Manage Students</h1>
                <button class="btn btn-success rounded-pill px-4 py-3 shadow" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="openAddModal()">
                    Add Student
                </button>
            </div>

            <!-- Filters -->
            <div class="filter-panel mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label Gerardo class.organic-label fw-bold">Search</label>
                        <input type="text" class="form-control form-control-lg" placeholder="Name, email..." value="<?= htmlspecialchars($search) ?>" onchange="filter('search', this.value)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select" onchange="filter('status', this.value)">
                            <option value="">All</option>
                            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">District</label>
                        <select class="form-select" onchange="filter('district', this.value)">
                            <option value="">All</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= htmlspecialchars($d['district']) ?>" <?= $district===$d['district']?'selected':'' ?>><?= htmlspecialchars($d['district']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Batch</label>
                        <select class="form-select" onchange="filter('batch', this.value)">
                            <option value="">All</option>
                            <?php foreach ($batches_list as $b): ?>
                                <option value="<?= htmlspecialchars($b['batch_code']) ?>" <?= $batch===$b['batch_code']?'selected':'' ?>><?= htmlspecialchars($b['batch_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="btn-group">
                            <button class="btn btn-view-toggle <?= $view==='grid'?'active':'' ?>" onclick="setView('grid')"><i class="bi bi-grid-3x3-gap"></i></button>
                            <button class="btn btn-view-toggle <?= $view==='list'?'active':'' ?>" onclick="setView('list')"><i class="bi bi-list-ul"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid View -->
            <?php if ($view === 'grid'): ?>
                <div class="row g-4">
                    <?php while ($s = $students->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card student-card h-100">
                                <div class="card-body text-center p-4">
                                    <img src="<?= htmlspecialchars($s['photo'] ?? 'default-student.png') ?>" class="rounded-circle mb-3" width="100" height="100" style="object-fit: cover; border: 4px solid #e0d4ff;">
                                    <h5 class="fw-bold"><?= htmlspecialchars($s['firstName'] . ' ' . $s['lastName']) ?></h5>
                                    <p class="text-muted">@<?= htmlspecialchars($s['username']) ?></p>
                                    <p><i class="bi bi-envelope"></i> <?= htmlspecialchars($s['email']) ?></p>
                                    <?php if ($s['batch_code']): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($s['batch_code']) ?></span>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <span class="badge-status <?= $s['status']==='active'?'bg-success':($s['status']==='pending'?'bg-warning':'bg-secondary') ?>">
                                            <?= ucfirst($s['status']) ?>
                                        </span>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-center gap-2">
                                        <button class="action-btn btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick='editStudent(<?= json_encode($s) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="action-btn btn btn-outline-danger" onclick="if(confirm('Delete this student?')) location.href='?delete=<?= $s['user_id'] ?>'">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button class="action-btn btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#medicalModal" onclick="openMedicalModal(<?= $s['student_id'] ?>)">
                                            <i class="bi bi-heart-pulse"></i>
                                        </button>
                                        <button class="action-btn btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#transportModal" onclick="openTransportModal(<?= $s['student_id'] ?>)">
                                            <i class="bi bi-bus-front"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- List View -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Batch</th>
                                    <th>District</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td><img src="<?= htmlspecialchars($s['photo'] ?? 'default-student.png') ?>" width="50" height="50" class="rounded-circle"></td>
                                        <td class="fw-bold"><?= htmlspecialchars($s['firstName'] . ' ' . $s['lastName']) ?></td>
                                        <td>@<?= htmlspecialchars($s['username']) ?></td>
                                        <td><?= htmlspecialchars($s['email']) ?></td>
                                        <td><?= htmlspecialchars($s['batch_code'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($s['district'] ?? '—') ?></td>
                                        <td><span class="badge-status <?= $s['status']==='active'?'bg-success':($s['status']==='pending'?'bg-warning':'bg-secondary') ?>"><?= ucfirst($s['status']) ?></span></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick='editStudent(<?= json_encode($s) ?>)'><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete?')) location.href='?delete=<?= $s['user_id'] ?>'"><i class="bi bi-trash"></i></button>
                                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#medicalModal" onclick="openMedicalModal(<?= $s['student_id'] ?>)"><i class="bi bi-heart-pulse"></i></button>
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#transportModal" onclick="openTransportModal(<?= $s['student_id'] ?>)"><i class="bi bi-bus-front"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-5 gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&view=<?= $view ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&district=<?= urlencode($district) ?>&batch=<?= urlencode($batch) ?>"
                           class="btn <?= $i==$page?'btn-primary':'btn-outline-primary' ?> rounded-pill px-4">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL YOUR ORIGINAL MODALS (100% restored) -->
    <!-- Student Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add/Edit Student</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="user_id">
                        <div class="text-center mb-4">
                            <img src="default.png" id="photoPreview" class="rounded-circle" width="120" height="120">
                            <div class="mt-3">
                                <input type="file" name="photo" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" name="username" id="username" class="form-control" placeholder="Username" required></div>
                            <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="Password (leave blank to keep)"></div>
                            <div class="col-md-6"><input type="text" name="firstName" id="firstName" class="form-control" placeholder="First Name" required></div>
                            <div class="col-md-6"><input type="text" name="lastName" id="lastName" class="form-control" placeholder="Last Name" required></div>
                            <div class="col-md-6"><input type="text" name="gender" id="gender" class="form-control" placeholder="Gender"></div>
                            <div class="col-md-6"><input type="date" name="dob" id="dob" class="form-control"></div>
                            <div class="col-md-6"><input type="email" name="email" id="email" class="form-control" placeholder="Email" required></div>
                            <div class="col-md-6"><input type="text" name="phone" id="phone" class="form-control" placeholder="Phone"></div>
                            <div class="col-md-6"><input type="text" name="IDNumber" id="IDNumber" class="form-control" placeholder="ID Number"></div>
                            <div class="col-md-6">
                                <select name="status" id="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="col-12"><hr></div>
                            <div class="col-md-6"><input type="text" name="address1" id="address1" class="form-control" placeholder="House/Flat No."></div>
                            <div class="col-md-6"><input type="text" name="streetName" id="streetName" class="form-control" placeholder="Street Name"></div>
                            <div class="col-md-4"><input type="text" name="postalCode" id="postalCode" class="form-control" placeholder="Postal Code"></div>
                            <div class="col-md-4"><input type="text" name="district" id="district" class="form-control" placeholder="District"></div>
                            <div class="col-md-4"><input type="text" name="country" id="country" class="form-control" placeholder="Country"></div>
                            <div class="col-md-6"><input type="file" name="document" accept=".pdf" class="form-control"></div>
                            <div id="documentLink" class="small mt-1"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Medical Modal -->
    <div class="modal fade" id="medicalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_type" value="medical">
                    <input type="hidden" name="student_id" id="medical_student_id">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">Medical Information</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" name="blood_type" id="blood_type" class="form-control" placeholder="Blood Type"></div>
                            <div class="col-md-6"><input type="text" name="allergies" id="allergies" class="form-control" placeholder="Allergies"></div>
                            <div class="col-12"><input type="text" name="chronic_conditions" id="chronic_conditions" class="form-control" placeholder="Chronic Conditions"></div>
                            <div class="col-12"><input type="text" name="medications" id="medications" class="form-control" placeholder="Medications"></div>
                            <div class="col-md-6"><input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" placeholder="Emergency Contact Name"></div>
                            <div class="col-md-6"><input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" placeholder="Emergency Contact Phone"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Medical Info</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transport Modal -->
    <div class="modal fade" id="transportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="form_type" value="transport">
                    <input type="hidden" name="student_id" id="transport_student_id">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title">Transport Information</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="default-transport.png" id="transportImagePreview" class="img-fluid rounded" style="max-width:200px;">
                        <div class="mt-3">
                            <input type="file" name="transport_image" class="form-control" accept="image/*">
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-6"><input type="text" name="transport_mode" id="transport_mode" class="form-control" placeholder="Transport Mode"></div>
                            <div class="col-md-6"><input type="text" name="route_number" id="route_number" class="form-control" placeholder="Route Number"></div>
                            <div class="col-md-6"><input type="text" name="pick_up_point" id="pick_up_point" class="form-control" placeholder="Pick-up Point"></div>
                            <div class="col-md-6"><input type="text" name="drop_off_point" id="drop_off_point" class="form-control" placeholder="Drop-off Point"></div>
                            <div class="col-12"><input type="text" name="guardian_contact" id="guardian_contact" class="form-control" placeholder="Guardian Contact"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Transport Info</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
        const medicalModal = new bootstrap.Modal(document.getElementById('medicalModal'));
        const transportModal = new bootstrap.Modal(document.getElementById('transportModal'));

        function filter(key, value) {
            const params = new URLSearchParams(window.location.search);
            if (value) params.set(key, value); else params.delete(key);
            window.location = '?' + params.toString() + '&view=' + (params.get('view') || 'grid');
        }
        function setView(view) {
            const params = new URLSearchParams(window.location.search);
            params.set('view', view);
            window.location = '?' + params.toString();
        }

        function editStudent(data) {
            document.getElementById('user_id').value = data.user_id || '';
            document.getElementById('username').value = data.username || '';
            document.getElementById('firstName').value = data.firstName || '';
            document.getElementById('lastName').value = data.lastName || '';
            document.getElementById('gender').value = data.gender || '';
            document.getElementById('dob').value = data.dob || '';
            document.getElementById('IDNumber').value = data.IDNumber || '';
            document.getElementById('phone').value = data.phone || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('status').value = data.status || 'active';
            document.getElementById('address1').value = data.address1 || '';
            document.getElementById('streetName').value = data.streetName || '';
            document.getElementById('postalCode').value = data.postalCode || '';
            document.getElementById('district').value = data.district || '';
            document.getElementById('country').value = data.country || '';
            document.getElementById('photoPreview').src = data.photo || 'default.png';
            document.getElementById('documentLink').innerHTML = data.document ? `<a href="${data.document}" target="_blank">Current Document</a>` : '';
            studentModal.show();
        }

        function openAddModal() {
            document.querySelectorAll('#studentModal input, #studentModal select').forEach(el => {
                if (el.type !== 'file') el.value = '';
            });
            document.getElementById('photoPreview').src = 'default.png';
            document.getElementById('documentLink').innerHTML = '';
            studentModal.show();
        }

        function openMedicalModal(id) {
            document.getElementById('medical_student_id').value = id;
            fetch(`get_medical_info.php?student_id=${id}`)
                .then(r => r.json())
                .then(d => {
                    ['blood_type','allergies','chronic_conditions','medications','emergency_contact_name','emergency_contact_phone'].forEach(f => {
                        document.getElementById(f).value = d[f] || '';
                    });
                });
            medicalModal.show();
        }

        function openTransportModal(id) {
            document.getElementById('transport_student_id').value = id;
            fetch(`get_transport_info.php?student_id=${id}`)
                .then(r => r.json())
                .then(d => {
                    ['transport_mode','route_number','pick_up_point','drop_off_point','guardian_contact'].forEach(f => {
                        document.getElementById(f).value = d[f] || '';
                    });
                    document.getElementById('transportImagePreview').src = d.transport_image || 'default-transport.png';
                });
            transportModal.show();
        }

        document.querySelectorAll('input[type=file]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        if (this.name === 'photo') document.getElementById('photoPreview').src = e.target.result;
                        if (this.name === 'transport_image') document.getElementById('transportImagePreview').src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>