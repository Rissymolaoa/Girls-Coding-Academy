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

function post($key){ return isset($_POST[$key]) ? trim($_POST[$key]) : null; }

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $res = $conn->prepare("SELECT address_id FROM users WHERE user_id=?");
    $res->bind_param("i",$del_id); $res->execute();
    $r=$res->get_result()->fetch_assoc(); $address_id=$r['address_id']??null; $res->close();

    $stmt=$conn->prepare("DELETE FROM parents WHERE user_id=?");
    $stmt->bind_param("i",$del_id); $stmt->execute(); $stmt->close();

    $stmt=$conn->prepare("DELETE FROM users WHERE user_id=? AND role='parent'");
    $stmt->bind_param("i",$del_id); $stmt->execute(); $stmt->close();

    if($address_id){
        $stmt=$conn->prepare("DELETE FROM addresses WHERE address_id=?");
        $stmt->bind_param("i",$address_id); $stmt->execute(); $stmt->close();
    }

    header("Location: manage_parents.php"); exit();
}

// Handle add/edit
if($_SERVER['REQUEST_METHOD']==='POST'){
    $user_id    = post('user_id')?intval(post('user_id')):null;
    $username   = post('username'); $firstName = post('firstName'); $lastName = post('lastName');
    $gender     = post('gender'); $IDNumber = post('IDNumber'); $phone = post('phone');
    $email      = post('email'); $password = post('password'); $status = post('status')?:'active';
    $address1   = post('address1'); $streetName = post('streetName'); $postalCode = post('postalCode');
    $district   = post('district'); $country = post('country');
    $relationship = post('relationship'); // NEW FIELD

    // handle file upload
    $documentPath = null;
    if(isset($_FILES['document']) && is_uploaded_file($_FILES['document']['tmp_name']) && $_FILES['document']['error']===UPLOAD_ERR_OK){
        $ext=pathinfo($_FILES['document']['name'],PATHINFO_EXTENSION);
        if(in_array(strtolower($ext),['pdf'])){
            $newName=uniqid('doc_').'.'.$ext;
            if(move_uploaded_file($_FILES['document']['tmp_name'],$uploadDir.$newName)){
                $documentPath = $uploadWebPath.$newName;
            }
        }
    }

    if($user_id){
        // edit
        $stmt=$conn->prepare("SELECT address_id FROM users WHERE user_id=?");
        $stmt->bind_param("i",$user_id); $stmt->execute();
        $res=$stmt->get_result(); $row=$res->fetch_assoc(); $address_id=$row['address_id']??null; $stmt->close();

        if($address_id){
            $stmt=$conn->prepare("UPDATE addresses SET address1=?,streetName=?,postalCode=?,district=?,country=? WHERE address_id=?");
            $stmt->bind_param("sssssi",$address1,$streetName,$postalCode,$district,$country,$address_id);
            $stmt->execute(); $stmt->close();
        } else {
            $stmt=$conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country,created_at) VALUES (?,?,?,?,?,NOW())");
            $stmt->bind_param("sssss",$address1,$streetName,$postalCode,$district,$country);
            $stmt->execute(); $address_id=$conn->insert_id; $stmt->close();
            $stmt=$conn->prepare("UPDATE users SET address_id=? WHERE user_id=?");
            $stmt->bind_param("ii",$address_id,$user_id); $stmt->execute(); $stmt->close();
        }

        // update user
        if($documentPath && !empty($password)){
            $hash=password_hash($password,PASSWORD_BCRYPT);
            $stmt=$conn->prepare("UPDATE users SET username=?,firstName=?,lastName=?,gender=?,IDNumber=?,phone=?,email=?,password=?,status=?,document=? WHERE user_id=? AND role='parent'");
            $stmt->bind_param("ssssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$hash,$status,$documentPath,$user_id); $stmt->execute(); $stmt->close();
        } elseif($documentPath){
            $stmt=$conn->prepare("UPDATE users SET username=?,firstName=?,lastName=?,gender=?,IDNumber=?,phone=?,email=?,status=?,document=? WHERE user_id=? AND role='parent'");
            $stmt->bind_param("sssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$status,$documentPath,$user_id); $stmt->execute(); $stmt->close();
        } elseif(!empty($password)){
            $hash=password_hash($password,PASSWORD_BCRYPT);
            $stmt=$conn->prepare("UPDATE users SET username=?,firstName=?,lastName=?,gender=?,IDNumber=?,phone=?,email=?,password=?,status=? WHERE user_id=? AND role='parent'");
            $stmt->bind_param("sssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$hash,$status,$user_id); $stmt->execute(); $stmt->close();
        } else {
            $stmt=$conn->prepare("UPDATE users SET username=?,firstName=?,lastName=?,gender=?,IDNumber=?,phone=?,email=?,status=? WHERE user_id=? AND role='parent'");
            $stmt->bind_param("ssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$status,$user_id); $stmt->execute(); $stmt->close();
        }

        // Update relationship in parents table
        $stmt=$conn->prepare("UPDATE parents SET relationship=? WHERE user_id=?");
        $stmt->bind_param("si",$relationship,$user_id); $stmt->execute(); $stmt->close();

    } else {
        // add
        $stmt=$conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country,created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->bind_param("sssss",$address1,$streetName,$postalCode,$district,$country); $stmt->execute();
        $address_id=$conn->insert_id; $stmt->close();

        $hash=password_hash($password?:uniqid(),PASSWORD_BCRYPT);
        $role='parent';
        $stmt=$conn->prepare("INSERT INTO users (username,firstName,lastName,gender,IDNumber,phone,email,password,status,role,document,address_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->bind_param("sssssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$hash,$status,$role,$documentPath,$address_id); $stmt->execute();
        $new_user_id=$conn->insert_id; $stmt->close();

        $stmt=$conn->prepare("INSERT INTO parents (user_id,relationship) VALUES (?,?)");
        $stmt->bind_param("is",$new_user_id,$relationship); $stmt->execute(); $stmt->close();
    }

    header("Location: manage_parents.php"); exit();
}

$limit=5; $page=isset($_GET['page'])?max(1,intval($_GET['page'])):1;
$offset=($page-1)*$limit; $search=isset($_GET['search'])?trim($_GET['search']):'';

if($search){
    $like="%$search%";
    $stmt=$conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='parent' AND (username LIKE ? OR firstName LIKE ? OR lastName LIKE ?)");
    $stmt->bind_param("sss",$like,$like,$like); $stmt->execute();
    $total_row=$stmt->get_result()->fetch_assoc(); $stmt->close();
} else {
    $total_row=$conn->query("SELECT COUNT(*) AS total FROM users WHERE role='parent'")->fetch_assoc();
}
$total_pages=ceil($total_row['total']/$limit);

if($search){
    $like="%$search%";
    $stmt=$conn->prepare("
        SELECT u.*,a.address1,a.streetName,a.postalCode,a.district,a.country,p.relationship
        FROM users u
        JOIN parents p ON p.user_id=u.user_id
        LEFT JOIN addresses a ON u.address_id=a.address_id
        WHERE u.role='parent' AND (u.username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?)
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sssii",$like,$like,$like,$limit,$offset); $stmt->execute();
    $parents=$stmt->get_result(); $stmt->close();
} else {
    $parents_q="SELECT u.*,a.address1,a.streetName,a.postalCode,a.district,a.country,p.relationship 
                FROM users u 
                JOIN parents p ON p.user_id=u.user_id 
                LEFT JOIN addresses a ON u.address_id=a.address_id 
                WHERE u.role='parent' ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
    $parents=$conn->query($parents_q);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Parents - Administration</title>
<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

:root{--primary:#7b2cbf;--accent:#5a189a;--muted:#f4f4f8;--card:#fff;--text:#222;}
*{box-sizing:border-box} body{font-family:'Inter',Arial,sans-serif;margin:0;background:var(--muted);color:var(--text);}

    header {
        background: #2c3e50;
        color: white;
        padding: 2px 2px;
        text-align: center;
    }

header h1{margin:0;font-size:20px;font-weight:600;}

.layout{display:flex;min-height:calc(100vh - 72px);}

.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff;}

.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px;}

.sidebar h3{font-size:13px;margin:0 0 12px;}

.nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left;}

.nav a.active,.nav a:hover{background:#1abc9c;color:#062018;}

.main{flex:1;padding:26px;}

.top-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;}

.btn{background:var(--primary);color:#fff;padding:10px 14px;border-radius:8px;border:none;cursor:pointer;}

.table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06);}

table{width:100%;border-collapse:collapse;font-size:14px;}

th,td{padding:10px;border-bottom:1px solid #732d91;text-align:left;}

th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;}

td.actions{width:140px;text-align:right;}

td a,td button{margin-left:6px;}

.small-link{color:var(--primary);text-decoration:none;}

.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);justify-content:center;align-items:flex-start;padding:40px;z-index:9999;overflow:auto;}

.modal.open{display:flex;}

.modal-card{background:#fff;border-radius:10px;padding:18px;max-width:900px;width:100%;box-shadow:0 8px 30px rgba(0,0,0,0.2);}

.modal-card h2{margin:0 0 10px;font-size:18px;color:var(--accent);text-align:center;}

.modal-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

.field{display:flex;flex-direction:column;}

.field label{font-size:13px;margin-bottom:6px;font-weight:600;color:#333;}

.field input[type="text"],.field input[type="email"],.field input[type="password"],.field select,.field input[type="file"]{padding:9px;border:1px solid #ddd;border-radius:6px;font-size:14px;}

.full{grid-column:1/-1;}

.modal-actions{display:flex;justify-content:space-between;gap:10px;margin-top:12px;}

.btn-secondary{background:#eee;color:#333;border-radius:8px;padding:10px 12px;border:none;cursor:pointer;}

.btn-secondary:hover{background:#8e44ad;color:#062018;}
@media(max-width:900px){.sidebar{display:none}.modal-form{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><h1>Girls Coding Academy - Admin Dashboard</h1></header>
<div class="layout">
<aside class="sidebar">

<img src="admin.png" alt="Admin">
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
<div class="top-row"><h2 style="margin:0;color:#333">Manage Parents</h2><div><button class="btn" onclick="openModal();">+ Add Parent</button></div></div>
<form method="get" style="margin-bottom:12px;">
<input type="text" name="search" placeholder="Search parents..." value="<?= htmlspecialchars($search) ?>" style="padding:8px;width:300px;">
<button type="submit" class="btn">Search</button>
</form>
<div class="table-card">
<table>
<thead>
<tr><th>Username</th><th>First name</th><th>Last name</th><th>Relationship</th><th>Gender</th><th>ID No</th><th>Phone</th><th>Email</th><th>Address</th><th>Document</th><th>Status</th><th class="actions">Actions</th></tr>
</thead>
<tbody>
<?php while($p=$parents->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($p['username']) ?></td>
<td><?= htmlspecialchars($p['firstName']) ?></td>
<td><?= htmlspecialchars($p['lastName']) ?></td>
<td><?= htmlspecialchars($p['relationship']) ?></td>
<td><?= htmlspecialchars($p['gender']) ?></td>
<td><?= htmlspecialchars($p['IDNumber']) ?></td>
<td><?= htmlspecialchars($p['phone']) ?></td>
<td><?= htmlspecialchars($p['email']) ?></td>
<td><?= htmlspecialchars(trim(($p['address1']??'').' '.($p['streetName']??'').' '.($p['district']??'').' '.($p['postalCode']??'').' '.($p['country']??''))) ?></td>
<td><?php if(!empty($p['document'])):?><a class="small-link" href="<?= htmlspecialchars($p['document']) ?>" target="_blank">View</a><?php else:?>—<?php endif;?></td>
<td><?= htmlspecialchars($p['status']) ?></td>
<td class="actions"><button class="btn-secondary" onclick='editParent(<?= json_encode($p,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
<a class="small-link" href="?delete=<?= intval($p['user_id']) ?>" onclick="return confirm('Delete this parent?')">🗑️</a></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</main>
</div>

<!-- Modal -->
<div id="parentModal" class="modal">
<div class="modal-card">
<h2 id="modalTitle">Add Parent</h2>
<form method="post" enctype="multipart/form-data" class="modal-form">
<input type="hidden" id="user_id" name="user_id">
<div class="field"><label>Username</label><input type="text" id="username" name="username" required></div>
<div class="field"><label>First Name</label><input type="text" id="firstName" name="firstName" required></div>
<div class="field"><label>Last Name</label><input type="text" id="lastName" name="lastName" required></div>
<div class="field"><label>Relationship</label>
<select name="relationship" id="relationship" required>
<option value="">Select</option>
<option value="Mother">Mother</option>
<option value="Father">Father</option>
<option value="Guardian">Guardian</option>
</select>
</div>
<div class="field"><label>Gender</label><select id="gender" name="gender" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
<div class="field"><label>ID Number</label><input type="text" id="IDNumber" name="IDNumber"></div>
<div class="field"><label>Phone</label><input type="text" id="phone" name="phone"></div>
<div class="field"><label>Email</label><input type="email" id="email" name="email" required></div>
<div class="field"><label>Password</label><input type="password" id="password" name="password"></div>
<div class="field"><label>Status</label><select id="status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></div>
<div class="field"><label>Address 1</label><input type="text" id="address1" name="address1"></div>
<div class="field"><label>Street Name</label><input type="text" id="streetName" name="streetName"></div>
<div class="field"><label>Postal Code</label><input type="text" id="postalCode" name="postalCode"></div>
<div class="field"><label>District</label><input type="text" id="district" name="district"></div>
<div class="field"><label>Country</label><input type="text" id="country" name="country"></div>
<div class="field full"><label>Document (PDF)</label><input type="file" name="document" accept="application/pdf"></div>
<div class="modal-actions full"><button type="submit" class="btn">Save</button><button type="button" class="btn-secondary" onclick="closeModal();">Cancel</button></div>
</form>
</div>
</div>

<script>
function openModal(){
  document.getElementById('modalTitle').innerText="Add Parent";
  ['user_id','username','firstName','lastName','gender','IDNumber','phone','email','password','status','address1','streetName','postalCode','district','country','relationship'].forEach(f=>{
    let el=document.getElementById(f); if(el) el.value='';
  });
  document.getElementById('status').value='active';
  document.getElementById('parentModal').classList.add('open');
}

function editParent(data){
  document.getElementById('modalTitle').innerText="Edit Parent";
  ['user_id','username','firstName','lastName','gender','IDNumber','phone','email','status','address1','streetName','postalCode','district','country','relationship'].forEach(f=>{
    let el=document.getElementById(f); if(el) el.value=data[f]??'';
  });
  document.getElementById('password').value='';
  document.getElementById('parentModal').classList.add('open');
}

function closeModal(){document.getElementById('parentModal').classList.remove('open');}
document.getElementById('parentModal').addEventListener('click',function(e){if(e.target===this) closeModal();});
</script>
</body>
</html>
