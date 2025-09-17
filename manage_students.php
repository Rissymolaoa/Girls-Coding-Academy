<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

include("db.php"); // assume this sets $conn = new mysqli(...)

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads/docs/';
$uploadWebPath = 'uploads/docs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Helper: safely get POST value
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    // get address_id
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

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id    = post('user_id') ? intval(post('user_id')) : null;
    $username   = post('username');
    $firstName  = post('firstName');
    $lastName   = post('lastName');
    $gender     = post('gender');
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

    // Handle file upload
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

    $photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "imageuploads/"; 
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES['photo']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
        $photoPath = $targetFile; // save path in DB
    }
}

    if ($user_id) {
        // ---- EDIT FLOW ----
        $stmt = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $address_id = $row['address_id'] ?? null;
        $stmt->close();

        if ($photoPath) {
    $stmt = $conn->prepare("UPDATE students SET photo=? WHERE user_id=?");
    $stmt->bind_param("si", $photoPath, $user_id);
    $stmt->execute();
    $stmt->close();
      }

        if ($address_id) {
            $stmt = $conn->prepare("UPDATE addresses SET address1 = ?, streetName = ?, postalCode = ?, district = ?, country = ? WHERE address_id = ?");
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
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, IDNumber=?, phone=?, email=?, password=?, status=?, document=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("ssssssssssi", $username, $firstName, $lastName, $gender, $IDNumber, $phone, $email, $hash, $status, $documentPath, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($documentPath) {
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, IDNumber=?, phone=?, email=?, status=?, document=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $IDNumber, $phone, $email, $status, $documentPath, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, IDNumber=?, phone=?, email=?, password=?, status=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $IDNumber, $phone, $email, $hash, $status, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, IDNumber=?, phone=?, email=?, status=? WHERE user_id=? AND role='student'");
            $stmt->bind_param("ssssssssi", $username, $firstName, $lastName, $gender, $IDNumber, $phone, $email, $status, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // ---- ADD FLOW ----
        $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
        $stmt->execute();
        $address_id = $conn->insert_id;
        $stmt->close();

        $hash = password_hash($password ?: uniqid(), PASSWORD_BCRYPT);
        $role = 'student';
        $stmt = $conn->prepare("INSERT INTO users (username, firstName, lastName, gender, IDNumber, phone, email, password, status, role, document, address_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssssssi", $username, $firstName, $lastName, $gender, $IDNumber, $phone, $email, $hash, $status, $role, $documentPath, $address_id);
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

// ---------------- SEARCH & PAGINATION ----------------
$limit = 5;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page-1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Count total
if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='student' AND (username LIKE ? OR firstName LIKE ? OR lastName LIKE ?)");
    $stmt->bind_param("sss",$like,$like,$like);
    $stmt->execute();
    $total_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $total_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'");
    $total_row = $total_result->fetch_assoc();
}
$total_pages = ceil($total_row['total'] / $limit);

// Fetch students with limit & offset
if ($search) {
    $like = "%$search%";
// For search
$stmt = $conn->prepare("
    SELECT u.*, s.photo, a.address1, a.streetName, a.postalCode, a.district, a.country
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
} else {
// Without search
$students_q = "
    SELECT u.*, s.photo, a.address1, a.streetName, a.postalCode, a.district, a.country
    FROM users u
    JOIN students s ON s.user_id = u.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";

    $students = $conn->query($students_q);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Students - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

  :root{
    --primary:#7b2cbf;
    --accent:#5a189a;
    --muted:#f4f4f8;
    --card:#ffffff;
    --text:#222;
  }
  *{box-sizing:border-box}
  body{font-family:Inter,Arial,Helvetica,sans-serif;margin:0;background:var(--muted);color:var(--text)}
  header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px 24px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
  header h1{margin:0;font-size:20px;font-weight:600}
  .layout{display:flex;min-height:calc(100vh - 72px)}
  .sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff}
  .sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px}
  .sidebar h3{font-size:13px;margin:0 0 12px}
  .nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left}
  .nav a.active, .nav a:hover{background:#1abc9c;color:#062018}
  .main{flex:1;padding:26px}
  .top-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}
  .btn{background:var(--primary);color:#fff;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
  .table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06)}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{padding:10px;border-bottom:1px solid #732d91;text-align:left}
  th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff}
  td.actions{width:140px;text-align:right}
  td a, td button{margin-left:6px}
  .small-link{color:var(--primary);text-decoration:none}
  .search{
    margin-top:12px
    margin-bottom:12px
  }

  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);justify-content:center;align-items:flex-start;padding:40px;z-index:9999;overflow:auto}
  .modal.open{display:flex}
  .modal-card{background:#fff;border-radius:10px;padding:18px;max-width:900px;width:100%;box-shadow:0 8px 30px rgba(0,0,0,0.2)}
  .modal-card h2{margin:0 0 10px;font-size:18px;color:var(--accent);text-align:center}
  .modal-form{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .field{display:flex;flex-direction:column}
  .field label{font-size:13px;margin-bottom:6px;font-weight:600;color:#333}
  .field input[type="text"], .field input[type="email"], .field input[type="password"], .field input[type="date"], .field select, .field input[type="file"]{
    padding:9px;border:1px solid #ddd;border-radius:6px;font-size:14px
  }
  .full { grid-column:1 / -1 }
  .modal-actions{display:flex;justify-content:space-between;gap:10px;margin-top:12px}
  .btn-secondary{background:#eee;color:#333;border-radius:8px;padding:10px 12px;border:none;cursor:pointer}
  .btn-secondary:hover{background:#8e44ad;color:#062018}
 
  @media(max-width:900px){
    .sidebar{display:none}
    .modal-form{grid-template-columns:1fr}
  }
  /* Dropdown menu for actions */
.dropdown {
  position: relative;
  display: inline-block;
}

.dropdown-btn {
  background: #eee;
  border: none;
  cursor: pointer;
  padding: 6px 10px;
  border-radius: 6px;
}

.dropdown-content {
  display: none;
  position: absolute;
  right: 0;
  background-color: #fff;
  min-width: 160px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  border-radius: 6px;
  z-index: 1000;
  overflow: hidden;
}

.dropdown-content a {
  color: #222;
  padding: 10px 12px;
  text-decoration: none;
  display: block;
  font-size: 14px;
}

.dropdown-content a:hover {
  background-color: #f1f1f1;
}

.dropdown:hover .dropdown-content {
  display: block;
}
table td img {
  display: block;
  margin: auto;
}

</style>
</head>
<body>
<header>
  <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>
<div class="layout">
  <aside class="sidebar">
    <img src="admin.jpg" alt="Admin">
    <h3>GIRLS CODING ACADEMY</h3>
    <nav class="nav">
    <h4 class="text-center mb-4">Administration</h4>
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
        <button class="btn" onclick="openModal();">+ Add Student</button>
      </div>
    </div>

    <form method="get" style="margin-bottom:12px;">
        <input type="text" name="search" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>" style="padding:8px;width:300px;">
        <button type="submit" class="btn">Search</button>
    </form>

    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>Username</th><th>First name</th><th>Last name</th><th>Gender</th><th>ID No</th>
            <th>Phone</th><th>Email</th><th>Address</th><th>Document</th><th>Status</th><th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($s = $students->fetch_assoc()): ?>
            <tr>
<td>
    <?php if (!empty($s['photo']) && file_exists($s['photo'])): ?>
      <img src="<?= htmlspecialchars($s['photo']) ?>" 
           alt="Student Photo" 
           style="width:60px;height:60px;object-fit:cover;border-radius:50%;border:2px solid #7b2cbf;">
    <?php else: ?>
      <span>No Photo</span>
    <?php endif; ?>
</td>

              <td><?= htmlspecialchars($s['username']) ?></td>
              <td><?= htmlspecialchars($s['firstName']) ?></td>
              <td><?= htmlspecialchars($s['lastName']) ?></td>
              <td><?= htmlspecialchars($s['gender']) ?></td>
              <td><?= htmlspecialchars($s['IDNumber']) ?></td>
              <td><?= htmlspecialchars($s['phone']) ?></td>
              <td><?= htmlspecialchars($s['email']) ?></td>
              <td><?= htmlspecialchars(trim(($s['address1'] ?? '') . ' ' . ($s['streetName'] ?? '') . ' ' . ($s['district'] ?? '') . ' ' . ($s['postalCode'] ?? '') . ' ' . ($s['country'] ?? ''))) ?></td>
              <td><?php if (!empty($s['document'])): ?><a class="small-link" href="<?= htmlspecialchars($s['document']) ?>" target="_blank">View</a><?php else: ?>—<?php endif; ?></td>
              <td><?= htmlspecialchars($s['status']) ?></td>
              <td class="actions">
                <button class="fas fa-edit" onclick='editStudent(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>edit</button>
                <a class="small-link" href="?delete=<?= intval($s['user_id']) ?>" onclick="return confirm('Delete this student?')">🗑️</a>
                <div class="dropdown">
              <button class="btn-secondary dropdown-btn">⋮</button>
               <div class="dropdown-content">
              <a href="#" onclick='openMedicalModal(<?= $s["student_id"] ?>)'>🩺 Edit Medical Info</a>
              <a href="#" onclick='openTransportModal(<?= $s["student_id"] ?>)'>🚍 Edit Transport Info</a>
         </div>
        </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <div class="pagination" style="margin-top:12px;text-align:center;">
        <?php 
          $qs = $search ? "&search=".urlencode($search) : "";
          if($page>1): 
        ?>
         <a href="?page=<?= ($page-1) . $qs ?>">&laquo; Prev</a>
        <?php else: ?>
          <span class="disabled">&laquo; Prev</span>
        <?php endif; ?>

        <?php if($page<$total_pages): ?>
         <a href="?page=<?= ($page+1) . $qs ?>">Next &raquo;</a>
        <?php else: ?>
          <span class="disabled">Next &raquo;</span>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- Student Modal -->
<div id="studentModal" class="modal">
  <div class="modal-card">
    <h2 id="modalTitle">Add Student</h2>
    <form method="post" enctype="multipart/form-data" class="modal-form">
      <input type="hidden" id="user_id" name="user_id">
      
      <div class="field">
        <label>Username</label>
        <input type="text" id="username" name="username" required>
      </div>
      <div class="field">
        <label>First Name</label>
        <input type="text" id="firstName" name="firstName" required>
      </div>
      <div class="field">
        <label>Last Name</label>
        <input type="text" id="lastName" name="lastName" required>
      </div>
      <div class="field">
        <label>Gender</label>
        <select id="gender" name="gender" required>
          <option value="">Select</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
  <option>Others</option>

        </select>
      </div>
      <div class="field">
        <label>ID Number</label>
        <input type="text" id="IDNumber" name="IDNumber">
      </div>
      <div class="field">
        <label>Phone</label>
        <input type="text" id="phone" name="phone">
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" id="email" name="email" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" id="password" name="password">
      </div>
      <div class="field">
        <label>Status</label>
        <select id="status" name="status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="pending">Pending</option>
        </select>
      </div>
      <div class="field">
        <label>Address 1</label>
        <input type="text" id="address1" name="address1">
      </div>
      <div class="field">
        <label>Street Name</label>
        <input type="text" id="streetName" name="streetName">
      </div>
      <div class="field">
        <label>Postal Code</label>
        <input type="text" id="postalCode" name="postalCode">
      </div>
      <div class="field">
        <label>District</label>
        <input type="text" id="district" name="district">
      </div>
      <div class="field">
        <label>Country</label>
        <input type="text" id="country" name="country">
      </div>
      <div class="field full">
        <label>Document (PDF)</label>
        <input type="file" name="document" accept="application/pdf">
      </div>
       <div>   
    <label for="photo">Upload Student Photo:</label>
    <input type="file" name="photo" accept="image/*">
    </div>
      <div class="modal-actions full">
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn-secondary" onclick="closeModal();">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Medical Info Modal -->
<div id="medicalModal" class="modal">
  <div class="modal-card">
    <h2>Edit Medical Info</h2>
    <form id="medicalForm" method="post">
      <input type="hidden" id="medical_student_id" name="student_id">
      <div class="field">
        <label>Blood Type</label>
        <input type="text" name="blood_type">
      </div>
      <div class="field">
        <label>Allergies</label>
        <input type="text" name="allergies">
      </div>
      <div class="field">
        <label>Chronic Conditions</label>
        <input type="text" name="chronic_conditions">
      </div>
      <div class="field">
        <label>Medications</label>
        <input type="text" name="medications">
      </div>
      <div class="field">
        <label>Emergency Contact Name</label>
        <input type="text" name="emergency_contact_name">
      </div>
      <div class="field">
        <label>Emergency Contact Phone</label>
        <input type="text" name="emergency_contact_phone">
      </div>
      <div class="modal-actions full">
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn-secondary" onclick="closeModalById('medicalModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Transport Info Modal -->
<div id="transportModal" class="modal">
  <div class="modal-card">
    <h2>Edit Transport Info</h2>
    <form id="transportForm" method="post">
      <input type="hidden" id="transport_student_id" name="student_id">
      <div class="field">
        <label>Transport Mode</label>
        <input type="text" name="transport_mode">
      </div>
      <div class="field">
        <label>Route Number</label>
        <input type="text" name="route_number">
      </div>
      <div class="field">
        <label>Pick Up Point</label>
        <input type="text" name="pick_up_point">
      </div>
      <div class="field">
        <label>Drop Off Point</label>
        <input type="text" name="drop_off_point">
      </div>
      <div class="field">
        <label>Guardian Contact</label>
        <input type="text" name="guardian_contact">
      </div>
      <div class="modal-actions full">
        <button type="submit" class="btn">Save</button>
        <button type="button" class="btn-secondary" onclick="closeModalById('transportModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeModalById(id){
  document.getElementById(id).classList.remove('open');
}

function openMedicalModal(student_id){
  document.getElementById('medical_student_id').value = student_id;
  document.getElementById('medicalModal').classList.add('open');
}

function openTransportModal(student_id){
  document.getElementById('transport_student_id').value = student_id;
  document.getElementById('transportModal').classList.add('open');
}
</script>

<script>
function openModal(){
  document.getElementById('modalTitle').innerText = "Add Student";
  const fields = ['user_id','username','firstName','lastName','gender','IDNumber','phone','email','password','status','address1','streetName','postalCode','district','country'];
  fields.forEach(f => {
    const el = document.getElementById(f);
    if (el) el.value = '';
  });
  document.getElementById('status').value = 'active';
  document.getElementById('studentModal').classList.add('open');
}

function editStudent(data){
  document.getElementById('modalTitle').innerText = "Edit Student";
  // data is object from JSON
  const mapping = {
    'user_id':'user_id',
    'username':'username',
    'firstName':'firstName',
    'lastName':'lastName',
    'gender':'gender',
    'IDNumber':'IDNumber',
    'phone':'phone',
    'email':'email',
    'status':'status',
    'address1':'address1',
    'streetName':'streetName',
    'postalCode':'postalCode',
    'district':'district',
    'country':'country'
  };
  for (const k in mapping){
    const el = document.getElementById(mapping[k]);
    if (!el) continue;
    el.value = data[k] !== undefined && data[k] !== null ? data[k] : '';
  }
  // password intentionally left blank
  document.getElementById('password').value = '';
  document.getElementById('studentModal').classList.add('open');
}

function closeModal(){
  document.getElementById('studentModal').classList.remove('open');
}

// click outside to close
document.getElementById('studentModal').addEventListener('click', function(e){
  if (e.target === this) closeModal();
});
</script>
</body>
</html>