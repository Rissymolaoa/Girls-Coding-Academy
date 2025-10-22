<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); exit();
}

include("db.php");

$uploadDir = __DIR__.'/uploads/docs/';
$uploadWebPath = 'uploads/docs/';
if(!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

$imageDir = __DIR__.'/imageuploads/';
$imageWebPath = 'imageuploads/';
if(!is_dir($imageDir)) mkdir($imageDir,0777,true);

function post($key){ return isset($_POST[$key])?trim($_POST[$key]):null; }

// Delete parent
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    $res=$conn->prepare("SELECT address_id FROM users WHERE user_id=?");
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

// Add/Edit parent
if($_SERVER['REQUEST_METHOD']==='POST'){
    $user_id=post('user_id')?intval(post('user_id')):null;
    $username=post('username'); $firstName=post('firstName'); $lastName=post('lastName');
    $dob=post('dob'); // DOB field
    $gender=post('gender'); $IDNumber=post('IDNumber'); $phone=post('phone');
    $email=post('email'); $password=post('password'); $status=post('status')?:'active';
    $address1=post('address1'); $streetName=post('streetName'); $postalCode=post('postalCode');
    $district=post('district'); $country=post('country'); $relationship=post('relationship');

    $photoPath = null;
    if(isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name']) && $_FILES['photo']['error']===UPLOAD_ERR_OK){
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','gif','webp'])){
            $newName=time().'_'.uniqid('img_').'.'.$ext;
            if(move_uploaded_file($_FILES['photo']['tmp_name'],$imageDir.$newName)){
                $photoPath = $imageWebPath.$newName;
            }
        }
    }

    $documentPath=null;
    if(isset($_FILES['document']) && is_uploaded_file($_FILES['document']['tmp_name']) && $_FILES['document']['error']===UPLOAD_ERR_OK){
        $ext=strtolower(pathinfo($_FILES['document']['name'],PATHINFO_EXTENSION));
        if($ext==='pdf'){
            $newName=uniqid('doc_').'.'.$ext;
            if(move_uploaded_file($_FILES['document']['tmp_name'],$uploadDir.$newName)){
                $documentPath=$uploadWebPath.$newName;
            }
        }
    }

    if($user_id){
        // edit
        $stmt=$conn->prepare("SELECT address_id FROM users WHERE user_id=?"); $stmt->bind_param("i",$user_id); $stmt->execute();
        $res=$stmt->get_result(); $row=$res->fetch_assoc(); $address_id=$row['address_id']??null; $stmt->close();

        if($address_id){
            $stmt=$conn->prepare("UPDATE addresses SET address1=?,streetName=?,postalCode=?,district=?,country=?,updated_at=NOW() WHERE address_id=?");
            $stmt->bind_param("sssssi",$address1,$streetName,$postalCode,$district,$country,$address_id); $stmt->execute(); $stmt->close();
        } else {
            $stmt=$conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
            $stmt->bind_param("sssss",$address1,$streetName,$postalCode,$district,$country); $stmt->execute(); $address_id=$conn->insert_id; $stmt->close();
            $stmt=$conn->prepare("UPDATE users SET address_id=? WHERE user_id=?"); $stmt->bind_param("ii",$address_id,$user_id); $stmt->execute(); $stmt->close();
        }

        // Update users table (without photo)
        $fields=[];$types=''; $params=[];
        $query="UPDATE users SET username=?,firstName=?,lastName=?,dob=?,gender=?,IDNumber=?,phone=?,email=?,status=?";
        $types="sssssssss";
        $params=[$username,$firstName,$lastName,$dob,$gender,$IDNumber,$phone,$email,$status];

        if(!empty($password)){
            $query.=",password=?"; $types.="s"; $params[]=password_hash($password,PASSWORD_BCRYPT);
        }
        if($documentPath){
            $query.=",document=?"; $types.="s"; $params[]=$documentPath;
        }

        $query.=" WHERE user_id=? AND role='parent'"; $types.="i"; $params[]=$user_id;
        $stmt=$conn->prepare($query);
        $stmt->bind_param($types,...$params); $stmt->execute(); $stmt->close();

        // Update parents table
        if($photoPath){
            $stmt=$conn->prepare("UPDATE parents SET relationship=?, photo=? WHERE user_id=?"); 
            $stmt->bind_param("ssi",$relationship,$photoPath,$user_id); 
            $stmt->execute(); 
            $stmt->close();
        } else {
            $stmt=$conn->prepare("UPDATE parents SET relationship=? WHERE user_id=?"); 
            $stmt->bind_param("si",$relationship,$user_id); 
            $stmt->execute(); 
            $stmt->close();
        }

    } else {
        // add
// add
$stmt=$conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
$stmt->bind_param("sssss",$address1,$streetName,$postalCode,$district,$country); 
$stmt->execute(); 
$address_id=$conn->insert_id; 
$stmt->close();

$hash=password_hash($password?:uniqid(),PASSWORD_BCRYPT);
$role='parent';

$stmt = $conn->prepare("INSERT INTO users (username,firstName,lastName,dob,gender,IDNumber,phone,email,password,status,role,document,address_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
$stmt->bind_param("ssssssssssssi", $username, $firstName, $lastName, $dob, $gender, $IDNumber, $phone, $email, $hash, $status, $role, $documentPath, $address_id);
$stmt->execute();

$new_user_id=$conn->insert_id; 
$stmt->close();

$stmt=$conn->prepare("INSERT INTO parents (user_id,relationship,photo) VALUES (?,?,?)"); 
$stmt->bind_param("iss",$new_user_id,$relationship,$photoPath); 
$stmt->execute(); 
$stmt->close();

    }

    header("Location: manage_parents.php"); exit();
}

// Pagination & fetch
$limit=10; $page=isset($_GET['page'])?max(1,intval($_GET['page'])):1; $offset=($page-1)*$limit; $search=isset($_GET['search'])?trim($_GET['search']):'';

if($search){
    $like="%$search%";
    $stmt=$conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='parent' AND (username LIKE ? OR firstName LIKE ? OR lastName LIKE ?)");
    $stmt->bind_param("sss",$like,$like,$like); $stmt->execute(); $total_row=$stmt->get_result()->fetch_assoc(); $stmt->close();
}else{
    $total_row=$conn->query("SELECT COUNT(*) AS total FROM users WHERE role='parent'")->fetch_assoc();
}
$total_pages=ceil($total_row['total']/$limit);

if($search){
    $like="%$search%";
    $stmt=$conn->prepare("
        SELECT u.*,p.relationship,p.photo,a.address1,a.streetName,a.postalCode,a.district,a.country
        FROM users u
        JOIN parents p ON p.user_id=u.user_id
        LEFT JOIN addresses a ON u.address_id=a.address_id
        WHERE u.role='parent' AND (u.username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?)
        ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii",$like,$like,$like,$limit,$offset); $stmt->execute(); $parents=$stmt->get_result(); $stmt->close();
}else{
    $parents=$conn->query("SELECT u.*,p.relationship,p.photo,a.address1,a.streetName,a.postalCode,a.district,a.country FROM users u JOIN parents p ON p.user_id=u.user_id LEFT JOIN addresses a ON u.address_id=a.address_id WHERE u.role='parent' ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset");
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Parents</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
  }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding-top: 56px;
  }

  .content {
    min-height: calc(100vh - 56px);
    transition: all 0.3s ease;
  }

  .main {
    padding: 2rem 2rem 2rem 1rem;
  }

  .section-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .section-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .search-form {
    display: flex;
    gap: 1rem;
    max-width: 400px;
  }

  .search-form .form-control {
    flex: 1;
  }

  .table {
    margin-bottom: 0;
  }

  .table th {
    background: var(--primary-gradient);
    color: white;
    border: none;
    font-weight: 600;
    padding: 1rem;
  }

  .table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: rgba(0,0,0,0.05);
  }

  .table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
  }

  .placeholder-photo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    border: 2px dashed rgba(0,0,0,0.1);
    font-size: 0.875rem;
  }

  .action-icons a {
    margin: 0 0.5rem;
    font-size: 1.25rem;
    color: #6b7280;
    transition: all 0.3s ease;
    border-radius: 50%;
    padding: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
  }

  .action-icons a:hover {
    color: #1f2937;
    background: rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
  }

  .pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
  }

  .pagination .btn {
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
  }

  .modal-img-preview {
    width: 140px;
    height: 140px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid rgba(0,0,0,0.1);
    display: block;
    margin: 1rem auto;
  }

  .modal-form .form-label {
    font-weight: 600;
    color: #1f2937;
  }

  .modal-form .row {
    gap: 1.5rem;
  }

  footer {
    background: rgba(31, 41, 55, 0.8);
    color: #fff;
    text-align: center;
    padding: 1.5rem;
    margin-top: 2rem;
    border-radius: 16px 16px 0 0;
  }

  /* Enhanced Sidebar Styles - Adjusted for Dashboard */
  .sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    position: fixed;
    top: 56px;
    height: calc(100vh - 56px);
    left: 0;
    overflow-y: auto;
    transition: all 0.3s ease;
    box-shadow: 4px 0 15px rgba(0,0,0,0.2);
    z-index: 1030;
  }

  @media (min-width: 992px) {
    .main {
      padding-left: 1rem;
      padding-right: 2rem;
    }
    .content {
      margin-left: 280px;
    }
  }

  @media (max-width: 991px) {
    .sidebar {
      top: 0;
      height: 100vh;
      left: -280px;
    }
    .sidebar.show {
      left: 0;
    }
    .main {
      padding: 1rem;
    }
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .top-row {
      flex-direction: column;
      align-items: stretch;
    }
    .search-form {
      max-width: none;
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="top-row">
      <h2>Manage Parents</h2>
      <button class="btn" style="background: var(--primary-gradient); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 500;" onclick="openModal();">
        <i class="bi bi-plus-lg me-2"></i>Add Parent
      </button>
    </div>

    <form method="get" class="search-form mb-4">
      <input type="text" name="search" placeholder="Search parents..." value="<?= htmlspecialchars($search) ?>" class="form-control" />
      <button type="submit" class="btn btn-outline-secondary">Search</button>
    </form>

    <div class="section-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Photo</th><th>Username</th><th>First Name</th><th>Last Name</th><th>DOB</th><th>Relationship</th><th>Gender</th><th>ID No</th><th>Phone</th><th>Email</th><th>Address</th><th>Document</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
<?php while($p=$parents->fetch_assoc()): ?>
            <tr>
              <td style="text-align:center; width: 100px;"><?php if(!empty($p['photo']) && file_exists($p['photo'])): ?><img src="<?= htmlspecialchars($p['photo']) ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;"><?php else: ?><div class="placeholder-photo">No Photo</div><?php endif;?></td>
              <td><?= htmlspecialchars($p['username']) ?></td>
              <td><?= htmlspecialchars($p['firstName']) ?></td>
              <td><?= htmlspecialchars($p['lastName']) ?></td>
              <td><?= htmlspecialchars($p['dob']) ?></td>
              <td><?= htmlspecialchars($p['relationship']) ?></td>
              <td><?= htmlspecialchars($p['gender']) ?></td>
              <td><?= htmlspecialchars($p['IDNumber']) ?></td>
              <td><?= htmlspecialchars($p['phone']) ?></td>
              <td><?= htmlspecialchars($p['email']) ?></td>
              <td><?= htmlspecialchars(trim(($p['address1']??'').' '.($p['streetName']??'').' '.($p['district']??'').' '.($p['postalCode']??'').' '.($p['country']??''))) ?></td>
              <td><?php if(!empty($p['document'])):?><a href="<?= $p['document'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a><?php else:?>—<?php endif;?></td>
              <td><span class="badge <?= $p['status']=='active'?'bg-success':'bg-secondary' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td class="text-center">
                <button class="action-icons btn btn-sm" onclick='editParent(<?= json_encode($p,JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_APOS) ?>)' title="Edit"><i class="bi bi-pencil-square"></i></button>
                <a href="?delete=<?= $p['user_id'] ?>" class="action-icons btn btn-sm btn-outline-danger" onclick="return confirm('Delete this parent?')" title="Delete"><i class="bi bi-trash"></i></a>
              </td>
            </tr>
<?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="pagination">
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

<!-- Modal -->
<div id="parentModal" class="modal fade" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content p-4">
<form method="post" enctype="multipart/form-data" id="parentForm" class="modal-form">
<input type="hidden" id="user_id" name="user_id">
<div class="text-center">
<img id="photoPreview" src="default.png" class="modal-img-preview">
</div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Username</label><input type="text" id="username" name="username" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input type="password" id="password" name="password" class="form-control"></div>
<div class="col-md-6"><label class="form-label">First Name</label><input type="text" id="firstName" name="firstName" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Last Name</label><input type="text" id="lastName" name="lastName" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" id="dob" name="dob" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Relationship</label>
<select id="relationship" name="relationship" class="form-select" required>
<option value="">Select</option><option value="Mother">Mother</option><option value="Father">Father</option><option value="Guardian">Guardian</option>
</select></div>
<div class="col-md-6"><label class="form-label">Gender</label>
<select id="gender" name="gender" class="form-select"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
<div class="col-md-6"><label class="form-label">ID Number</label><input type="text" id="IDNumber" name="IDNumber" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Phone</label><input type="text" id="phone" name="phone" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" id="email" name="email" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Status</label>
<select id="status" name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></div>
<div class="col-md-6"><label class="form-label">Address 1</label><input type="text" id="address1" name="address1" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Street Name</label><input type="text" id="streetName" name="streetName" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Postal Code</label><input type="text" id="postalCode" name="postalCode" class="form-control"></div>
<div class="col-md-4"><label class="form-label">District</label><input type="text" id="district" name="district" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Country</label><input type="text" id="country" name="country" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Document (PDF)</label><input type="file" id="document" name="document" accept="application/pdf" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Photo</label><input type="file" id="photo" name="photo" accept="image/*" class="form-control"></div>
<div class="col-12 text-end mt-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</div>
</form>
</div>
</div>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const parentModal = new bootstrap.Modal(document.getElementById('parentModal'));
document.getElementById('photo').addEventListener('change',function(){
  const file=this.files[0]; if(!file) return;
  const reader=new FileReader();
  reader.onload=function(e){ document.getElementById('photoPreview').src=e.target.result; }
  reader.readAsDataURL(file);
});
function openModal(){
  ['user_id','username','firstName','lastName','dob','gender','IDNumber','phone','email','password','status','address1','streetName','postalCode','district','country','relationship'].forEach(f=>{let el=document.getElementById(f); if(el) el.value='';});
  document.getElementById('status').value='active'; document.getElementById('photoPreview').src='default.png';
  parentModal.show();
}
function editParent(data){
  ['user_id','username','firstName','lastName','dob','gender','IDNumber','phone','email','status','address1','streetName','postalCode','district','country','relationship'].forEach(f=>{let el=document.getElementById(f); if(el) el.value=data[f]??'';});
  document.getElementById('password').value=''; document.getElementById('photoPreview').src=data.photo??'default.png';
  parentModal.show();
}
</script>
</body>
</html>