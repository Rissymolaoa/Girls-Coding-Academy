<?php
session_start();
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$user_id = intval($_GET['id'] ?? 0);
if ($user_id <= 0) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("
    SELECT u.*, a.address1, a.streetName, a.postalCode, a.district, a.country
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows === 0) {
    die("User not found.");
}
$user = $user_result->fetch_assoc();
$stmt->close();

// Fetch role-specific info
$role = $user['role'] ?? 'user';
$additional_info = [];

// For students
if ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT s.*, u.firstName as parent_first, u.lastName as parent_last, p.relationship
        FROM students s
        LEFT JOIN parent_students ps ON s.student_id = ps.student_id
        LEFT JOIN parents p ON ps.parent_id = p.parent_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE s.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_data = $student_result->fetch_assoc();
    $additional_info['student'] = is_array($student_data) ? $student_data : [];
    $stmt->close();

    // Enrollments
    $stmt = $conn->prepare("
        SELECT ce.*, b.batch_code, c.courseName
        FROM course_enrollments ce
        JOIN batches b ON ce.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE ce.student_id = ?
        ORDER BY ce.enrolled_at DESC
    ");
    if (!empty($additional_info['student']['student_id'])) {
        $stmt->bind_param("i", $additional_info['student']['student_id']);
    } else {
        $zero = 0;
        $stmt->bind_param("i", $zero); // Fallback
    }
    $stmt->execute();
    $enrollments_result = $stmt->get_result();
    $additional_info['enrollments'] = [];
    while ($row = $enrollments_result->fetch_assoc()) {
        $additional_info['enrollments'][] = $row;
    }
    $stmt->close();
}

// For teachers
if ($role === 'teacher') {
    $stmt = $conn->prepare("
        SELECT t.*
        FROM teachers t
        WHERE t.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $teacher_result = $stmt->get_result();
    $teacher_data = $teacher_result->fetch_assoc();
    $additional_info['teacher'] = is_array($teacher_data) ? $teacher_data : [];
    $stmt->close();

    // Assigned batches
    $stmt = $conn->prepare("
        SELECT ca.*, b.batch_code, c.courseName
        FROM course_assignments ca
        JOIN batches b ON ca.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE ca.teacher_id = ?
        ORDER BY ca.created_at DESC
    ");
    if (!empty($additional_info['teacher']['teacher_id'])) {
        $stmt->bind_param("i", $additional_info['teacher']['teacher_id']);
    } else {
        $zero = 0;
        $stmt->bind_param("i", $zero); // Fallback
    }
    $stmt->execute();
    $assignments_result = $stmt->get_result();
    $additional_info['assignments'] = [];
    while ($row = $assignments_result->fetch_assoc()) {
        $additional_info['assignments'][] = $row;
    }
    $stmt->close();
}

// For parents
if ($role === 'parent') {
    $stmt = $conn->prepare("
        SELECT p.*
        FROM parents p
        WHERE p.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $parent_result = $stmt->get_result();
    $parent_data = $parent_result->fetch_assoc();
    $additional_info['parent'] = is_array($parent_data) ? $parent_data : [];
    $stmt->close();

    // Linked students
    $stmt = $conn->prepare("
        SELECT s.*, u.firstName, u.lastName, u.email
        FROM parent_students ps
        JOIN students s ON ps.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        WHERE ps.parent_id = ?
    ");
    if (!empty($additional_info['parent']['parent_id'])) {
        $stmt->bind_param("i", $additional_info['parent']['parent_id']);
    } else {
        $zero = 0;
        $stmt->bind_param("i", $zero); // Fallback
    }
    $stmt->execute();
    $students_result = $stmt->get_result();
    $additional_info['students'] = [];
    while ($row = $students_result->fetch_assoc()) {
        $additional_info['students'][] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?> - Profile - Girls Coding Academy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
  }
  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding-top: 76px;
  }
  .profile-header {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin: 2rem auto;
    max-width: 800px;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-align: center;
    position: relative;
  }
  .admin-actions {
    position: absolute;
    top: 1rem;
    right: 1rem;
  }
  .admin-actions .btn {
    margin-left: 0.5rem;
  }
  .profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    margin: 0 auto 1rem;
  }
  .profile-header h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }
  .role-badge {
    background: var(--primary-gradient);
    color: white;
    padding: 0.25rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
  }
  .info-section {
    max-width: 800px;
    margin: 0 auto 2rem;
    padding: 0 1rem;
  }
  .info-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-md);
    border-left: 4px solid #667eea;
  }
  .info-label {
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 0.25rem;
  }
  .info-value {
    color: #1f2937;
    font-size: 1rem;
  }
  .enrollments-table, .assignments-table, .students-table {
    width: 100%;
    border-collapse: collapse;
  }
  .enrollments-table th, .enrollments-table td,
  .assignments-table th, .assignments-table td,
  .students-table th, .students-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
  }
  .enrollments-table th, .assignments-table th, .students-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
  }
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 1rem;
  }
  .back-link:hover {
    color: #764ba2;
  }
  .no-info {
    text-align: center;
    color: #6b7280;
    font-style: italic;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>
<div class="profile-header">
  <div class="admin-actions">
    <a href="edit_user.php?id=<?= $user_id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
    <?php if ($user['status'] === 'active'): ?>
    <a href="deactivate_user.php?id=<?= $user_id ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate this user?')"><i class="bi bi-pause"></i> Deactivate</a>
    <?php else: ?>
    <a href="activate_user.php?id=<?= $user_id ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Activate this user?')"><i class="bi bi-play"></i> Activate</a>
    <?php endif; ?>
    <a href="delete_user.php?id=<?= $user_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user? This action cannot be undone.')"><i class="bi bi-trash"></i> Delete</a>
  </div>
  <a href="admin_dashboard.php" class="back-link">
    <i class="bi bi-arrow-left"></i>
    Back to Dashboard
  </a>
  <div class="profile-avatar">
    <?php 
$photo_src = '';
if (!empty($additional_info[$role]) && is_array($additional_info[$role]) && array_key_exists('photo', $additional_info[$role]) && !empty($additional_info[$role]['photo'])) {
    $photo_src = $additional_info[$role]['photo'];
} elseif (!empty($user['document'])) {
    $photo_src = $user['document'];
}

    if ($photo_src): 
    ?>
    <img src="<?= htmlspecialchars($photo_src) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
    <?php else: ?>
    <i class="bi bi-person-circle"></i>
    <?php endif; ?>
  </div>
  <h1><?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?></h1>
  <span class="role-badge"><?= ucfirst(htmlspecialchars($user['role'])) ?></span>
</div>
<div class="info-section">
  <!-- Basic Info -->
  <div class="info-card">
    <h5 class="mb-3"><i class="bi bi-info-circle"></i> Basic Information</h5>
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <div class="info-label">Username</div>
          <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Email</div>
          <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Phone</div>
          <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Date of Birth</div>
          <div class="info-value"><?= $user['dob'] ? date('M j, Y', strtotime($user['dob'])) : 'Not specified' ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Gender</div>
          <div class="info-value"><?= htmlspecialchars($user['gender'] ?? 'Not specified') ?></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <div class="info-label">ID Number</div>
          <div class="info-value"><?= htmlspecialchars($user['IDNumber']) ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Status</div>
          <div class="info-value"><span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst(htmlspecialchars($user['status'])) ?></span></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Joined</div>
          <div class="info-value"><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
        </div>
        <div class="mb-3">
          <div class="info-label">Document</div>
          <div class="info-value">
            <?php if ($user['document']): ?>
              <a href="<?= htmlspecialchars($user['document']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Document</a>
            <?php else: ?>
              Not uploaded
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Address -->
  <?php if (!empty($user['address1'])): ?>
  <div class="info-card">
    <h5 class="mb-3"><i class="bi bi-geo-alt"></i> Address</h5>
    <div class="info-value">
      <?= htmlspecialchars($user['address1']) ?><br>
      <?= htmlspecialchars($user['streetName']) ?><br>
      <?= htmlspecialchars($user['district']) ?>, <?= htmlspecialchars($user['postalCode']) ?><br>
      <?= htmlspecialchars($user['country']) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Role-specific sections -->
  <?php if ($role === 'student' && !empty($additional_info['student']))
        : ?>
  <div class="info-card">
    <h5 class="mb-3"><i class="bi bi-mortarboard"></i> Student Details</h5>
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <div class="info-label">Photo</div>
          <div class="info-value">
            <?php if (!empty($additional_info['student']['photo'])): ?>
              <img src="<?= htmlspecialchars($additional_info['student']['photo']) ?>" alt="Student Photo" style="width: 100px; height: 100px; border-radius: 8px;">
            <?php else: ?>
              No photo
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php if (!empty($additional_info['enrollments'])): ?>
    <h6 class="mt-3">Enrollments</h6>
    <table class="enrollments-table">
      <thead>
        <tr>
          <th>Batch</th>
          <th>Course</th>
          <th>Status</th>
          <th>Enrolled</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($additional_info['enrollments'] as $enroll): ?>
        <tr>
          <td><?= htmlspecialchars($enroll['batch_code']) ?></td>
          <td><?= htmlspecialchars($enroll['courseName']) ?></td>
          <td><span class="badge bg-<?= $enroll['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($enroll['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($enroll['enrolled_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($role === 'teacher' && !empty($additional_info['teacher'])): ?>
  <div class="info-card">
    <h5 class="mb-3"><i class="bi bi-easel"></i> Teacher Details</h5>
    <div class="mb-3">
      <div class="info-label">Speciality</div>
      <div class="info-value"><?= htmlspecialchars($additional_info['teacher']['subject_speciality'] ?? 'Not specified') ?></div>
    </div>
    <div class="mb-3">
      <div class="info-label">Photo</div>
      <div class="info-value">
        <?php if (!empty($additional_info['teacher']['photo'])): ?>
          <img src="<?= htmlspecialchars($additional_info['teacher']['photo']) ?>" alt="Teacher Photo" style="width: 100px; height: 100px; border-radius: 8px;">
        <?php else: ?>
          No photo
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($additional_info['assignments'])): ?>
    <h6 class="mt-3">Assigned Batches</h6>
    <table class="assignments-table">
      <thead>
        <tr>
          <th>Batch</th>
          <th>Course</th>
          <th>Assigned</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($additional_info['assignments'] as $assign): ?>
        <tr>
          <td><?= htmlspecialchars($assign['batch_code']) ?></td>
          <td><?= htmlspecialchars($assign['courseName']) ?></td>
          <td><?= date('M j, Y', strtotime($assign['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($role === 'parent' && !empty($additional_info['parent'])): ?>
  <div class="info-card">
    <h5 class="mb-3"><i class="bi bi-people"></i> Parent Details</h5>
    <div class="mb-3">
      <div class="info-label">Relationship</div>
      <div class="info-value"><?= htmlspecialchars($additional_info['parent']['relationship'] ?? 'Not specified') ?></div>
    </div>
    <div class="mb-3">
      <div class="info-label">Photo</div>
      <div class="info-value">
        <?php if (!empty($additional_info['parent']['photo'])): ?>
          <img src="<?= htmlspecialchars($additional_info['parent']['photo']) ?>" alt="Parent Photo" style="width: 100px; height: 100px; border-radius: 8px;">
        <?php else: ?>
          No photo
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($additional_info['students'])): ?>
    <h6 class="mt-3">Linked Students</h6>
    <table class="students-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Email</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($additional_info['students'] as $student): ?>
        <tr>
          <td><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></td>
          <td><?= htmlspecialchars($student['email']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($additional_info) || !isset($additional_info[$role]) || !is_array($additional_info[$role])): ?>
  <div class="info-card">
    <div class="no-info">No additional role-specific information available.</div>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
