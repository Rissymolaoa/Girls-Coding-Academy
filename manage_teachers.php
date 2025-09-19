<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

include("db.php"); // your DB connection

$uploadDir = __DIR__ . '/uploads/docs/';
$uploadWebPath = 'uploads/docs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

function post($key){
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Delete teacher
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM teachers WHERE user_id=?");
    $stmt->bind_param("i",$del_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='teacher'");
    $stmt->bind_param("i",$del_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_teachers.php");
    exit();
}

// Add/Edit teacher
if($_SERVER['REQUEST_METHOD']==='POST'){
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
    $speciality = post('subject_speciality');

    $documentPath = null;
    if(isset($_FILES['document']) && is_uploaded_file($_FILES['document']['tmp_name']) && $_FILES['document']['error']===UPLOAD_ERR_OK){
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        if(strtolower($ext)==='pdf'){
            $newName = uniqid('doc_').'.'.$ext;
            $absPath = $uploadDir.$newName;
            if(move_uploaded_file($_FILES['document']['tmp_name'],$absPath)){
                $documentPath = $uploadWebPath.$newName;
            }
        }
    }

    if($user_id){
        // EDIT
        $fields=['username','firstName','lastName','gender','IDNumber','phone','email','status'];
        $params=[$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$status];
        $types='ssssssss';

        if(!empty($password)){
            $fields[]='password';
            $params[]=password_hash($password,PASSWORD_BCRYPT);
            $types.='s';
        }
        if($documentPath){
            $fields[]='document';
            $params[]=$documentPath;
            $types.='s';
        }

        $setStr = implode('=?,',$fields).'=?';
        $params[]=$user_id;
        $types.='i';

        $stmt = $conn->prepare("UPDATE users SET $setStr WHERE user_id=? AND role='teacher'");
        $stmt->bind_param($types,...$params);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE teachers SET subject_speciality=? WHERE user_id=?");
        $stmt->bind_param("si",$speciality,$user_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // ADD
        $hash = password_hash($password ?: uniqid(),PASSWORD_BCRYPT);
        $role='teacher';
        $stmt=$conn->prepare("INSERT INTO users (username,firstName,lastName,gender,IDNumber,phone,email,password,status,role,document,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->bind_param("sssssssssss",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$hash,$status,$role,$documentPath);
        $stmt->execute();
        $new_user_id=$conn->insert_id;
        $stmt->close();

        $stmt=$conn->prepare("INSERT INTO teachers (user_id,subject_speciality) VALUES (?,?)");
        $stmt->bind_param("is",$new_user_id,$speciality);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: manage_teachers.php");
    exit();
}

// Server-side search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "u.role='teacher'";
if($search !== '') {
    $where .= " AND (u.username LIKE '%$search%' OR u.firstName LIKE '%$search%' OR u.lastName LIKE '%$search%' OR t.subject_speciality LIKE '%$search%')";
}

// Pagination
$limit=5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page<1) $page=1;
$offset=($page-1)*$limit;

// Total count
$total_result = $conn->query("SELECT COUNT(*) as total FROM users u JOIN teachers t ON t.user_id=u.user_id WHERE $where");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

// Fetch teachers
$teachers_q = "SELECT u.*, t.subject_speciality 
               FROM users u 
               JOIN teachers t ON t.user_id=u.user_id 
               WHERE $where 
               ORDER BY u.created_at DESC 
               LIMIT $limit OFFSET $offset";
$teachers = $conn->query($teachers_q);

// Preserve other GET params in pagination
$qs = '';
if (!empty($_GET)) {
    $params = $_GET;
    unset($params['page']);
    $qs = !empty($params) ? '&' . http_build_query($params) : '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Teachers - Admin</title>
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
      header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }
  header h1{margin:0;
    font-size:20px;
    font-weight:600}
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
  /* Modal */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.modal.open {
  display: flex;
}

.modal-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  width: 600px;
  max-width: 95%;
  box-shadow: 0 8px 20px rgba(0,0,0,0.25);
  animation: fadeIn 0.3s ease-in-out;
}

.modal-card h2 {
  margin-top: 0;
  text-align: center;
  font-size: 20px;
  color: var(--accent);
}

.modal-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.modal-form label {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  font-weight: 600;
  color: #333;
}

.modal-form input,
.modal-form select {
  margin-top: 5px;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.modal-form button {
  grid-column: 1 / -1;
  margin-top: 10px;
  padding: 10px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.modal-form button[type="submit"] {
  background: var(--primary);
  color: #fff;
}

.modal-form button[type="button"] {
  background: #ccc;
  color: #222;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>  
<header><h1>Girls Coding Academy - Admin Dashboard</h1></header>
<div class="layout">
<aside class="sidebar">
<img src="admin.png" alt="Admin">
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
<h2>Manage Teachers</h2>
<button class="btn" onclick="openModal();">+ Add Teacher</button>
</div>

<div class="search">
<form method="GET" action="">
  <input type="text" name="search" id="searchInput" placeholder="Search teachers..." 
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="padding:8px;width:250px;">
  <button type="submit" class="btn">Search</button>
</form>
</div>

<div class="table-card">
<table id="teachersTable">
<thead>
<tr>
<th>Username</th><th>First</th><th>Last</th><th>Gender</th><th>ID No</th>
<th>Phone</th><th>Email</th><th>Speciality</th><th>Status</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php while($s=$teachers->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($s['username']) ?></td>
<td><?= htmlspecialchars($s['firstName']) ?></td>
<td><?= htmlspecialchars($s['lastName']) ?></td>
<td><?= htmlspecialchars($s['gender']) ?></td>
<td><?= htmlspecialchars($s['IDNumber']) ?></td>
<td><?= htmlspecialchars($s['phone']) ?></td>
<td><?= htmlspecialchars($s['email']) ?></td>
<td><?= htmlspecialchars($s['subject_speciality']) ?></td>
<td><?= htmlspecialchars($s['status']) ?></td>
<td>
<button onclick='editTeacher(<?= json_encode($s,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️Edit</button>
<a href="?delete=<?= intval($s['user_id']) ?>" onclick="return confirm('Delete teacher?')"> 🗑️Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<div class="pagination" style="margin-top:12px;text-align:center;">
<?php if($page>1): ?>
<a href="?page=<?= ($page-1) . $qs ?>">&laquo; Prev</a>
<?php else: ?>
<span class="disabled">&laquo; Prev</span>
<?php endif; ?>

<span> Page <?= $page ?> of <?= $total_pages ?> </span>

<?php if($page<$total_pages): ?>
<a href="?page=<?= ($page+1) . $qs ?>">Next &raquo;</a>
<?php else: ?>
<span class="disabled">Next &raquo;</span>
<?php endif; ?>
</div>
</main>
</div>

<!-- Modal -->
<div id="teacherModal" class="modal">
<div class="modal-card">
<h2 id="modalTitle">Add Teacher</h2>
<form id="teacherForm" method="POST" enctype="multipart/form-data" class="modal-form">
<input type="hidden" name="user_id" id="user_id">
<label>Username<input type="text" name="username" id="username"></label>
<label>First Name<input type="text" name="firstName" id="firstName"></label>
<label>Last Name<input type="text" name="lastName" id="lastName"></label>
<label>Gender<select name="gender" id="gender">
  <option>Male</option>
  <option>Female</option>
  <option>Others</option>
</select></label>
<label>ID Number<input type="text" name="IDNumber" id="IDNumber"></label>
<label>Phone<input type="text" name="phone" id="phone"></label>
<label>Email<input type="email" name="email" id="email"></label>
<label>Password<input type="password" name="password" id="password"></label>
<label>Status<select name="status" id="status">
  <option value="active">Active</option>
  <option value="inactive">Inactive</option>
  <option value="pending">Pending</option>
</select></label>
<label>Subject Speciality<input type="text" name="subject_speciality" id="subject_speciality"></label>
<label>Document<input type="file" name="document" accept="application/pdf"></label>
<button type="submit">Save</button>
<button type="button" onclick="closeModal()">Close</button>
</form>
</div>
</div>

<script>
function openModal(){
  document.getElementById('teacherForm').reset();
  document.getElementById('teacherModal').classList.add('open');
  document.getElementById('modalTitle').innerText='Add Teacher';
}
function editTeacher(data){
  document.getElementById('teacherForm').reset();
  document.getElementById('modalTitle').innerText='Edit Teacher';
  document.getElementById('user_id').value = data.user_id;
  document.getElementById('username').value = data.username;
  document.getElementById('firstName').value = data.firstName;
  document.getElementById('lastName').value = data.lastName;
  document.getElementById('gender').value = data.gender;
  document.getElementById('IDNumber').value = data.IDNumber;
  document.getElementById('phone').value = data.phone;
  document.getElementById('email').value = data.email;
  document.getElementById('status').value = data.status;
  document.getElementById('subject_speciality').value = data.subject_speciality;
  document.getElementById('teacherModal').classList.add('open');
}
function closeModal(){
  document.getElementById('teacherModal').classList.remove('open');
}
</script>
</body>
</html>
