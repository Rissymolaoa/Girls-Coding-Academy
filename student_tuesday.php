<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

include("db.php");

// Upload directories initialization
$uploadDir = __DIR__ . '/uploads/docs/';
$uploadWebPath = 'uploads/docs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$imageDir = __DIR__ . '/imageuploads/';
$imageWebPath = 'imageuploads/';
if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}


// Handle delete request
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    $res = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
    $res->bind_param("i", $del_id);
    $res->execute();
    $r = $res->get_result()->fetch_assoc();
    $address_id = $r['address_id'] ?? null;
    $res->close();

    $stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM student_medical_info WHERE student_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM student_transport_info WHERE student_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();

    if ($address_id) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt->bind_param("i", $address_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: manage_students.php");
    exit();
}


// Handle POST requests for add/edit and modal forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['form_type']) && $_POST['form_type'] === 'medical') {
        // Medical info add/edit
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
        $res = $stmt->get_result();
        $exists = $res->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE student_medical_info SET blood_type=?, allergies=?, chronic_conditions=?, medications=?, emergency_contact_name=?, emergency_contact_phone=?, updated_at=NOW() WHERE student_id=?");
            $stmt->bind_param("ssssssi", $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone, $student_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO student_medical_info (student_id, blood_type, allergies, chronic_conditions, medications, emergency_contact_name, emergency_contact_phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issssss", $student_id, $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: manage_students.php");
        exit();

    } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'transport') {
        // Transport info add/edit
        $student_id = intval(post('student_id'));
        $transport_mode = post('transport_mode');
        $route_number = post('route_number');
        $pick_up_point = post('pick_up_point');
        $drop_off_point = post('drop_off_point');
        $guardian_contact = post('guardian_contact');
        $transportImagePath = null;
if (isset($_FILES['transport_image']) && is_uploaded_file($_FILES['transport_image']['tmp_name']) && $_FILES['transport_image']['error'] === UPLOAD_ERR_OK) {
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
        $res = $stmt->get_result();
        $exists = $res->num_rows > 0;
        $stmt->close();

if ($exists) {
    if ($transportImagePath !== null) {
        $stmt = $conn->prepare("UPDATE student_transport_info SET transport_mode=?, route_number=?, pick_up_point=?, drop_off_point=?, guardian_contact=?, transport_image=?, updated_at=NOW() WHERE student_id=?");
        $stmt->bind_param("ssssssi", $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $transportImagePath, $student_id);
    } else {
        $stmt = $conn->prepare("UPDATE student_transport_info SET transport_mode=?, route_number=?, pick_up_point=?, drop_off_point=?, guardian_contact=?, updated_at=NOW() WHERE student_id=?");
        $stmt->bind_param("sssssi", $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $student_id);
    }
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO student_transport_info (student_id, transport_mode, route_number, pick_up_point, drop_off_point, guardian_contact, transport_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("issssss", $student_id, $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $transportImagePath);
    $stmt->execute();
    $stmt->close();
}


        header("Location: manage_students.php");
        exit();

    } else {
        // Student add/edit logic here

        $user_id    = post('user_id') ? intval(post('user_id')) : null;
        $username   = post('username');
        $firstName  = post('firstName');
        $lastName   = post('lastName');
        $gender     = post('gender');
        $dob        = post('dob');
        $IDNumber   = post('IDNumber');
        $phone      = post('phone');
        $email      = post('email');
        $password   = post('password');
        $status     = post('status') ?: 'active';
        $address1   = post('address1');
        $streetName = post('streetName');
        $postalCode = post('postalCode');
        $district   = post('district');
        $country    = post('country');

        // Handle document upload
        $documentPath = null;
        if (isset($_FILES['document']) && is_uploaded_file($_FILES['document']['tmp_name']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
            $allowed = ['pdf'];
            if (in_array(strtolower($ext), $allowed)) {
                $newName = uniqid('doc_') . '.' . $ext;
                $absPath = $uploadDir . $newName;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $absPath)) {
                    $documentPath = $uploadWebPath . $newName;
                }
            }
        }

        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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
            // EDIT flow

            $stmt = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $address_id = $row['address_id'] ?? null;
            $stmt->close();

            if ($photoPath) {
                $stmt = $conn->prepare("UPDATE students SET photo = ? WHERE user_id = ?");
                $stmt->bind_param("si", $photoPath, $user_id);
                $stmt->execute();
                $stmt->close();
            }

            if ($address_id) {
                $stmt = $conn->prepare("UPDATE addresses SET address1 = ?, streetName = ?, postalCode = ?, district = ?, country = ?, updated_at = NOW() WHERE address_id = ?");
                $stmt->bind_param("sssssi", $address1, $streetName, $postalCode, $district, $country, $address_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
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
                $stmt->execute();
                $stmt->close();
            } elseif ($documentPath) {
                $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, status=?, document=? WHERE user_id=? AND role='student'");
                $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $status, $documentPath, $user_id);
                $stmt->execute();
                $stmt->close();
            } elseif (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, password=?, status=? WHERE user_id=? AND role='student'");
                $stmt->bind_param("ssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $user_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, status=? WHERE user_id=? AND role='student'");
                $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $status, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // ADD flow

            $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
            $stmt->execute();
            $address_id = $conn->insert_id;
            $stmt->close();

            $hash = password_hash($password ?: uniqid(), PASSWORD_BCRYPT);
            $role = 'student';
            $stmt = $conn->prepare("INSERT INTO users (username, firstName, lastName, gender, dob, IDNumber, phone, email, password, status, role, document, address_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $role, $documentPath, $address_id);
            $stmt->execute();
            $new_user_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO students (user_id, photo) VALUES (?, ?)");
            $stmt->bind_param("is", $new_user_id, $photoPath);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: manage_students.php");
        exit();
    }
}

// Pagination and fetching students
$limit = 10;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page-1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("
        SELECT u.*, s.student_id, s.photo, a.address1, a.streetName, a.postalCode, a.district, a.country
        FROM users u
        JOIN students s ON s.user_id = u.user_id
        LEFT JOIN addresses a ON u.address_id = a.address_id
        WHERE u.role='student' AND (u.username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?)
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sssii",$like,$like,$like,$limit,$offset);
    $stmt->execute();
    $students = $stmt->get_result();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='student' AND (username LIKE ? OR firstName LIKE ? OR lastName LIKE ?)");
    $stmt->bind_param("sss",$like,$like,$like);
    $stmt->execute();
    $total_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $students_q = "
        SELECT u.*, s.student_id, s.photo, a.address1, a.streetName, a.postalCode, a.district, a.country
        FROM users u
        JOIN students s ON s.user_id = u.user_id
        LEFT JOIN addresses a ON u.address_id = a.address_id
        WHERE u.role = 'student'
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $students = $conn->query($students_q);

    $total_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'");
    $total_row = $total_result->fetch_assoc();
}
$total_pages = ceil($total_row['total'] / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Students - Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

<style>
:root {
    --primary:#7b2cbf; --accent:#5a189a; --muted:#f4f4f8; --card:#ffffff; --text:#222;
}
*{box-sizing:border-box}
body{font-family:Inter,Arial,Helvetica,sans-serif;margin:0;background:var(--muted);color:var(--text)}
header { background: #2c3e50; color: white; padding: 12px; text-align: center; }
.layout{display:flex;min-height:calc(100vh - 56px)}
.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff}
.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px}
.sidebar h3{font-size:13px;margin:0 0 12px}
.nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left}
.nav a.active, .nav a:hover{background:#1abc9c;color:#062018}
.main{flex:1;padding:26px}
.top-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}
.btn-primary{background:var(--primary);border:0}
.table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06)}
.table thead th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff}
.table td, .table th{vertical-align:middle}
.action-icons a{margin:0 6px;font-size:1.2rem;color:#333}
.action-icons a:hover{color:var(--accent)}
.placeholder-photo{width:80px;height:80px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;border:2px dashed #ddd}
.modal-img-preview{width:140px;height:140px;border-radius:8px;object-fit:cover;border:2px solid #ddd;display:block;margin:12px auto}
.modal-form .field label{font-weight:600}
@media(max-width:900px){.sidebar{display:none}}
</style>
</head>
<body>
<header>
  <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>

<div class="layout">
  <aside class="sidebar">
    <img src="admin.png" alt="Admin" />
    <h3>GIRLS CODING ACADEMY</h3>
    <nav class="nav">
      <a href="admin_dashboard.php" class="active"><i class="bi bi-house-door-fill"></i> Dashboard</a>
      <a href="approve_users.php"><i class="bi bi-person-check-fill"></i> Approve Users</a>
      <a href="manage_courses.php"><i class="bi bi-journal-bookmark-fill"></i> Manage Courses</a>
      <a href="manage_students.php"><i class="bi bi-people-fill"></i> Manage Students</a>
      <a href="manage_teachers.php"><i class="bi bi-person-badge-fill"></i> Manage Teachers</a>
      <a href="parents_summary.php"><i class="bi bi-people"></i> Parent Summary</a>
      <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Manage Parents</a>
      <a href="assign_parent_student.php"><i class="bi bi-person-plus-fill"></i> Assign Students</a>
      <a href="course_assignment.php"><i class="bi bi-book-half"></i> Assign Courses</a>
      <a href="add_batch.php"><i class="bi bi-plus-circle-fill"></i> Add Batch</a>
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>

  <main class="main">
    <div class="top-row">
      <h2 style="margin:0;color:#333">Manage Students</h2>
      <div>
        <button class="btn btn-primary" onclick="openModal();"><i class="bi bi-plus-lg"></i> Add Student</button>
      </div>
    </div>

    <form method="get" class="mb-3 d-flex gap-2">
      <input type="text" name="search" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>" class="form-control w-50" />
      <button type="submit" class="btn btn-outline-secondary">Search</button>
    </form>

    <div class="table-card">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Photo</th><th>Username</th><th>First Name</th><th>Last Name</th><th>Gender</th><th>DOB</th><th>Email</th><th>Phone</th><th>ID No</th><th>Address</th><th>Status</th><th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
<?php while ($s = $students->fetch_assoc()):
    $json = json_encode($s, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
          <tr>
            <td style="text-align:center; width: 100px;">
              <?php if (!empty($s['photo']) && file_exists($s['photo'])): ?>
                <img src="<?= htmlspecialchars($s['photo']) ?>" alt="Photo" style="width:60px;height:60px;object-fit:cover;border-radius:50%;" />
              <?php else: ?>
                <div class="placeholder-photo">No Photo</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($s['username']) ?></td>
            <td><?= htmlspecialchars($s['firstName']) ?></td>
            <td><?= htmlspecialchars($s['lastName']) ?></td>
            <td><?= htmlspecialchars($s['gender']) ?></td>
            <td><?= htmlspecialchars($s['dob']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['phone']) ?></td>
            <td><?= htmlspecialchars($s['IDNumber']) ?></td>
            <td><?= htmlspecialchars(trim(($s['address1']??'').' '.($s['streetName']??'').' '.($s['district']??'').' '.($s['postalCode']??'').' '.($s['country']??''))) ?></td>
            <td><span class="badge <?= $s['status']=='active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
            <td class="text-center">
              <a href="#" class="action-icons" title="Edit" onclick='editStudent(<?= $json ?>); return false;'>
                <i class="bi bi-pencil-square"></i>
              </a>
              <a href="?delete=<?= intval($s['user_id']) ?>" class="action-icons" title="Delete" onclick="return confirm('Delete this student?')">
                <i class="bi bi-trash"></i>
              </a>
              <a href="#" class="action-icons" title="Transport" onclick="openTransportModal(<?= intval($s['student_id']) ?>); return false;">
                <i class="bi bi-bus-front-fill"></i>
              </a>
              <a href="#" class="action-icons" title="Medical" onclick="openMedicalModal(<?= intval($s['student_id']) ?>); return false;">
                <i class="bi bi-heart-pulse-fill"></i>
              </a>
            </td>
          </tr>
<?php endwhile; ?>
        </tbody>
      </table>

      <div class="pagination d-flex justify-content-center gap-3 mt-3">
<?php
$qs = $search ? "&search=".urlencode($search) : "";
if ($page > 1):
?>
        <a class="btn btn-outline-secondary" href="?page=<?= $page - 1 . $qs ?>">&laquo; Prev</a>
<?php else: ?>
        <span class="btn btn-outline-secondary disabled">&laquo; Prev</span>
<?php endif; ?>

        <span class="align-self-center">Page <?= $page ?> of <?= $total_pages ?></span>

<?php if ($page < $total_pages): ?>
        <a class="btn btn-outline-secondary" href="?page=<?= $page + 1 . $qs ?>">Next &raquo;</a>
<?php else: ?>
        <span class="btn btn-outline-secondary disabled">Next &raquo;</span>
<?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Student Modal (Add/Edit) -->
<div id="studentModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="studentForm" class="modal-form p-4">
        <input type="hidden" id="user_id" name="user_id" />

        <div class="text-center">
          <img id="photoPreview" src="default.png" alt="Photo preview" class="modal-img-preview" />
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-control" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Password (leave blank to keep)</label>
            <input type="password" id="password" name="password" class="form-control" />
          </div>

          <div class="col-md-6">
            <label class="form-label">First Name</label>
            <input type="text" id="firstName" name="firstName" class="form-control" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Last Name</label>
            <input type="text" id="lastName" name="lastName" class="form-control" required />
          </div>

          <div class="col-md-6">
            <label class="form-label">Gender</label>
            <select id="gender" name="gender" class="form-select">
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Others">Others</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Date of Birth</label>
            <input type="date" id="dob" name="dob" class="form-control" />
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" id="phone" name="phone" class="form-control" />
          </div>

          <div class="col-md-6">
            <label class="form-label">ID Number</label>
            <input type="text" id="IDNumber" name="IDNumber" class="form-control" />
          </div>

          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Address 1</label>
            <input type="text" id="address1" name="address1" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Street Name</label>
            <input type="text" id="streetName" name="streetName" class="form-control" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Postal Code</label>
            <input type="text" id="postalCode" name="postalCode" class="form-control" />
          </div>
          <div class="col-md-4">
            <label class="form-label">District</label>
            <input type="text" id="district" name="district" class="form-control" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Country</label>
            <input type="text" id="country" name="country" class="form-control" />
          </div>

          <div class="col-md-6">
            <label class="form-label">Document (PDF)</label>
            <input type="file" id="document" name="document" accept="application/pdf" class="form-control" />
            <div id="documentLink" class="small mt-1"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Upload Photo</label>
            <input type="file" id="photo" name="photo" accept="image/*" class="form-control" />
          </div>

          <div class="col-12 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Medical Info Modal -->
<div id="medicalModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-3">
      <form id="medicalForm" method="post" class="p-4">
        <input type="hidden" id="medical_student_id" name="student_id" />
        <input type="hidden" name="form_type" value="medical" />

        <div class="modal-body">
          <h5 class="mb-3">Edit Medical Info</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label>Blood Type</label>
              <input type="text" name="blood_type" id="blood_type" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Allergies</label>
              <input type="text" name="allergies" id="allergies" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Chronic Conditions</label>
              <input type="text" name="chronic_conditions" id="chronic_conditions" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Medications</label>
              <input type="text" name="medications" id="medications" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Emergency Contact Name</label>
              <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Emergency Contact Phone</label>
              <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Transport Info Modal -->
<div id="transportModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-3">

      <!-- Centered image container on top -->
      <div class="text-center my-3">
        <img id="transportImagePreview" src="default-transport.png" alt="Transport Image Preview" style="width:140px; height:auto; border-radius:8px;" />
      </div>

      <!-- Form for transport -->
      <form id="transportForm" method="post" enctype="multipart/form-data" class="p-4">
        <input type="hidden" id="transport_student_id" name="student_id" />
        <input type="hidden" name="form_type" value="transport" />

        <div class="modal-body">
          <h5 class="mb-3">Edit Transport Info</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label>Transport Mode</label>
              <input type="text" id="transport_mode" name="transport_mode" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Plate Number</label>
              <input type="text" id="route_number" name="route_number" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Pick Up Point</label>
              <input type="text" id="pick_up_point" name="pick_up_point" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Drop Off Point</label>
              <input type="text" id="drop_off_point" name="drop_off_point" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Driver Contact</label>
              <input type="text" id="guardian_contact" name="guardian_contact" class="form-control" />
            </div>
            <div class="col-md-6">
              <label>Transport Mode Image</label>
              <input type="file" id="transport_image" name="transport_image" accept="image/*" class="form-control" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>

    </div>
  </div>
</div>


          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
const medicalModal = new bootstrap.Modal(document.getElementById('medicalModal'));
const transportModal = new bootstrap.Modal(document.getElementById('transportModal'));

document.getElementById('transport_image').addEventListener('change', function(e) {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(evt) {
    document.getElementById('transportImagePreview').src = evt.target.result;
  };
  reader.readAsDataURL(file);
});

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
  document.getElementById('password').value = ''; // clear password input
  document.getElementById('status').value = data.status || 'active';

  document.getElementById('address1').value = data.address1 || '';
  document.getElementById('streetName').value = data.streetName || '';
  document.getElementById('postalCode').value = data.postalCode || '';
  document.getElementById('district').value = data.district || '';
  document.getElementById('country').value = data.country || '';

  // Reset file inputs
  document.getElementById('document').value = '';
  document.getElementById('photo').value = '';

  // Show document link if present
  if (data.document) {
    document.getElementById('documentLink').innerHTML = `<a href="${data.document}" target="_blank">Current doc</a>`;
  } else {
    document.getElementById('documentLink').innerHTML = '';
  }

  // Show photo or default
  if (data.photo && data.photo !== '') {
    document.getElementById('photoPreview').src = data.photo;
  } else {
    document.getElementById('photoPreview').src = 'default.png';
  }

  studentModal.show();
}

// Add your openMedicalModal, openTransportModal functions here if needed...

function openMedicalModal(studentId) {
  document.getElementById('medical_student_id').value = studentId;
  const fields = ['blood_type','allergies','chronic_conditions','medications','emergency_contact_name','emergency_contact_phone'];
  fields.forEach(id => document.getElementById(id).value = '');

  fetch(`get_medical_info.php?student_id=${studentId}`)
    .then(res => res.json())
    .then(data => {
      if (data) {
        fields.forEach(id => { document.getElementById(id).value = data[id] || ''; });
      }
    })
    .catch(console.error);

  medicalModal.show();
}

function openModal() {
  // Clear all student form inputs
  const fieldsToClear = ['user_id', 'username', 'firstName', 'lastName', 'gender', 'dob', 'IDNumber', 'phone', 'email', 'password', 
                         'status', 'address1', 'streetName', 'postalCode', 'district', 'country', 'document', 'photo'];

  fieldsToClear.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      if (element.tagName === "SELECT") {
        element.selectedIndex = 0;
      } else {
        element.value = '';
      }
    }
  });

  // Clear documentLink and set photoPreview to default
  document.getElementById('documentLink').innerHTML = '';
  document.getElementById('photoPreview').src = 'default.png';

  // Show the modal
  studentModal.show();
}


function openTransportModal(studentId) {
  document.getElementById('transport_student_id').value = studentId;
  const fields = ['transport_mode','route_number','pick_up_point','drop_off_point','guardian_contact'];
  fields.forEach(id => document.getElementById(id).value = '');

  fetch(`get_transport_info.php?student_id=${studentId}`)
    .then(res => res.json())
    .then(data => {
      if (data) {
        fields.forEach(id => { document.getElementById(id).value = data[id] || ''; });

        if (data.transport_image && data.transport_image !== '') {
          document.getElementById('transportImagePreview').src = data.transport_image;
        } else {
          document.getElementById('transportImagePreview').src = 'default-transport.png';
        }
      }
    })
    .catch(console.error);

  transportModal.show();
}

document.getElementById('transport_image').addEventListener('change', function(e) {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(evt) {
    document.getElementById('transportImagePreview').src = evt.target.result;
  };
  reader.readAsDataURL(file);
});

</script>
</body>
</html>
