<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') exit('Unauthorized');

include("db.php");

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "role='student'";
if($search!==''){
    $where .= " AND (firstName LIKE '%$search%' OR lastName LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%')";
}

$limit = 5;
$page  = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset= ($page-1)*$limit;

$total_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE $where");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

$students = $conn->query("SELECT u.*, a.address1, a.streetName, a.postalCode, a.district, a.country 
                          FROM users u 
                          LEFT JOIN addresses a ON u.address_id=a.address_id 
                          WHERE $where 
                          ORDER BY u.firstName ASC 
                          LIMIT $limit OFFSET $offset");

$qs = '';
if(!empty($_GET)){
    $params = $_GET;
    unset($params['page']);
    $qs = !empty($params) ? '&'.http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Students</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;background:#f5f6fa;color:#2c3e50;}
header{background:linear-gradient(90deg,#7b2cbf,#5a189a);color:white;padding:20px;text-align:center;}
.container{display:flex;min-height:calc(100vh - 70px);}
.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;}
.sidebar img{width:100px;height:100px;border-radius:50%;margin-bottom:15px;border:3px solid #7b2cbf;object-fit:cover;}
.sidebar h3{font-size:13px;color:white;margin-bottom:12px;}
.sidebar a{width:100%;display:block;color:white;text-decoration:none;padding:10px 15px;margin:5px 0;border-radius:5px;font-size:14px;}
.sidebar a:hover,.sidebar a.active{background:#1abc9c;}
.content{flex:1;padding:30px;display:flex;flex-direction:column;gap:20px;}
.table-wrapper{overflow-x:auto;}
table{width:100%;border-collapse:collapse;background:white;border-radius:8px;overflow:hidden;}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;font-size:14px;}
th{background: linear-gradient(90deg,#7b2cbf,#5a189a);color:white;}
.pagination{margin-top:15px;text-align:center;}
.pagination a, .pagination span{display:inline-block;margin:0 5px;padding:6px 10px;border-radius:5px;text-decoration:none;color:#7b2cbf;border:1px solid #7b2cbf;}
.pagination span.disabled{color:#999;border-color:#999;}
button{padding:6px 10px;border:none;border-radius:5px;cursor:pointer;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;overflow-y:auto;padding:20px;}
.modal-content{background:#fff;padding:20px;border-radius:8px;width:90%;max-width:900px;position:relative;display:flex;gap:20px;flex-wrap:wrap;}
.close{position:absolute;top:10px;right:15px;font-size:18px;cursor:pointer;color:red;}
input, select{width:100%;padding:8px;margin-bottom:10px;border:1px solid #ccc;border-radius:5px;}
.col{flex:1;min-width:300px;}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<header><h1>Girls Coding Academy - Admin Dashboard</h1></header>
<div class="container">
<div class="sidebar">
<img src="admin.jpg">
<h3>GIRLS CODING ACADEMY</h3>
<a href="admin_dashboard.php">🏠 Dashboard</a>
<a href="approve_users.php">📝 Approve Users</a>
<a href="manage_courses.php">📚 Manage Courses</a>
<a href="manage_students.php" class="active">👩‍🎓 Manage Students</a>
<a href="logout.php">🚪 Logout</a>
</div>

<div class="content">
<h2>Students</h2>
<form method="GET" style="margin-bottom:10px;">
<input type="text" name="search" placeholder="Search students..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
<button type="submit">Search</button>
</form>

<div class="table-wrapper">
<table>
<thead>
<tr>
<th>Username</th>
<th>Full Name</th>
<th>Email</th>
<th>Phone</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($students->num_rows>0): ?>
<?php while($s=$students->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($s['username']) ?></td>
<td><?= htmlspecialchars($s['firstName'].' '.$s['lastName']) ?></td>
<td><?= htmlspecialchars($s['email']) ?></td>
<td><?= htmlspecialchars($s['phone']) ?></td>
<td><?= htmlspecialchars($s['status']) ?></td>
<td>
<button onclick="openStudentModal(<?= $s['user_id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
<button onclick="openMedicalModal(<?= $s['user_id'] ?>)"><i class="fas fa-notes-medical"></i> Medical</button>
<button onclick="openTransportModal(<?= $s['user_id'] ?>)"><i class="fas fa-bus"></i> Transport</button>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" style="text-align:center;">No students found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="pagination">
<?php if($page>1): ?><a href="?page=<?= ($page-1).$qs ?>">&laquo; Prev</a><?php else:?><span class="disabled">&laquo; Prev</span><?php endif;?>
<span>Page <?= $page ?> of <?= $total_pages ?></span>
<?php if($page<$total_pages): ?><a href="?page=<?= ($page+1).$qs ?>">Next &raquo;</a><?php else:?><span class="disabled">Next &raquo;</span><?php endif;?>
</div>
</div>

<!-- Modals -->
<?php include('student_modals.php'); ?>

</div></div>

<script src="student_scripts.js"></script>
</body>
</html>
