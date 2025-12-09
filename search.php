<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include 'db.php';

$search_query = trim($_GET['q'] ?? '');
if (empty($search_query)) {
    header("Location: admin_dashboard.php");
    exit();
}

$like  = "%" . $conn->real_escape_string($search_query) . "%";
$exact = $conn->real_escape_string($search_query);
$results = [];

// FINAL WORKING SEARCH – ALL TABLES & COLUMNS FIXED
$sql = "
    -- 1. Users
    SELECT 'user'       AS type, user_id      AS id, CONCAT(firstName,' ',lastName) AS name, email, username,                'manage_users.php'        AS page 
    FROM users
    WHERE firstName LIKE ? OR lastName LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?

    UNION ALL

    -- 2. Students
    SELECT 'student'    AS type, s.student_id AS id, CONCAT(u.firstName,' ',u.lastName) AS name, u.email, u.phone,          'manage_students.php'     AS page
    FROM students s JOIN users u ON s.user_id = u.user_id
    WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phone LIKE ?

    UNION ALL

    -- 3. Teachers
    SELECT 'teacher'    AS type, t.teacher_id AS id, CONCAT(u.firstName,' ',u.lastName) AS name, u.email, t.subject_speciality, 'manage_teacher.php'      AS page
    FROM teachers t JOIN users u ON t.user_id = u.user_id
    WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR t.subject_speciality LIKE ?

    UNION ALL

    -- 4. Parents
    SELECT 'parent'     AS type, p.parent_id  AS id, CONCAT(u.firstName,' ',u.lastName) AS name, u.email, u.phone,          'manage_parents.php'      AS page
    FROM parents p JOIN users u ON p.user_id = u.user_id
    WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phone LIKE ?

    UNION ALL

    -- 5. Courses
    SELECT 'course'     AS type, course_id    AS id, title                               AS name, courseName, category,      'manage_courses.php'      AS page
    FROM courses
    WHERE title LIKE ? OR courseName LIKE ? OR category LIKE ?

    UNION ALL

    -- 6. Batches
    SELECT 'batch'      AS type, batch_id     AS id, batch_code                          AS name, status, NULL,              'add_batch.php'           AS page
    FROM batches
    WHERE batch_code LIKE ? OR status LIKE ?

    UNION ALL

    -- 7. Activities
    SELECT 'activity'   AS type, activity_id  AS id, title                               AS name, description, NULL,         'academics.php'           AS page
    FROM activities
    WHERE title LIKE ? OR description LIKE ?

    UNION ALL

    -- 8. Events
    SELECT 'event'      AS type, event_id     AS id, title                               AS name, description, location,    'events.php'              AS page
    FROM events
    WHERE title LIKE ? OR description LIKE ? OR location LIKE ?

    UNION ALL

    -- 9. Rooms (correct table)
    SELECT 'room'       AS type, id           AS id, room_name                           AS name, room_type, capacity,      'manage_rooms.php'        AS page
    FROM school_rooms
    WHERE room_name LIKE ? OR room_type LIKE ? OR status LIKE ?

    UNION ALL

    -- 10. Equipment (your real table – assuming school_equipments)
    SELECT 'equipment'  AS type, id           AS id, item_name                           AS name, category, notes,          'manage_equipments.php'   AS page
    FROM school_equipment
    WHERE item_name LIKE ? OR category LIKE ? OR notes LIKE ?

    ORDER BY
        CASE 
            WHEN name = ? THEN 0
            WHEN name LIKE ? THEN 1
            ELSE 2 
        END,
        name
    LIMIT 100
";

$stmt = $conn->prepare($sql);

// CORRECT PARAMETER COUNT: 5+4+4+4+3+2+2+3+3+3 + 2 = 35
$params = [
    // Users
    $like, $like, $like, $like, $like,
    // Students
    $like, $like, $like, $like,
    // Teachers
    $like, $like, $like, $like,
    // Parents
    $like, $like, $like, $like,
    // Courses
    $like, $like, $like,
    // Batches
    $like, $like,
    // Activities
    $like, $like,
    // Events
    $like, $like, $like,
    // Rooms
    $like, $like, $like,
    // Equipment
    $like, $like, $like,
    // Sorting priority
    $exact, $like
];


$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}

// Smart redirect if only ONE exact match
if (count($results) === 1) {
    $item = $results[0];
    $url = $item['page'] . "?id=" . $item['id'];
    if ($item['type'] === 'batch')     $url = "add_batch.php?id=" . $item['id'];
    if ($item['type'] === 'activity')  $url = "academics.php#activity-" . $item['id'];
    header("Location: $url");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%);padding-top:80px}
        .card{transition:all .3s}@media(hover:hover){.card:hover{transform:translateY(-6px);box-shadow:0 25px 50px -12px rgba(0,0,0,.15)}}
        .badge{@apply px-3 py-1 rounded-full text-xs font-bold text-white}
    </style>
</head>
<body class="min-h-screen">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="text-center mb-12">
        <h1 class="text-5xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
            Search Results
        </h1>
        <p class="text-xl text-gray-600 mt-4">
            Found <strong><?=count($results)?></strong> result<?=count($results)!==1?'s':''?> for
            <span class="text-indigo-600 font-bold">"<?=htmlspecialchars($search_query)?>"</span>
        </p>
        <a href="admin_dashboard.php" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 mt-6">
            Back to Dashboard
        </a>
    </div>

    <?php if(empty($results)): ?>
        <div class="text-center py-20">
            <i class="bi bi-search text-7xl text-gray-300"></i>
            <h3 class="text-2xl text-gray-500 mt-6">No results found</h3>
            <p class="text-gray-400 mt-2">Try searching by name, email, batch code, room, equipment, etc.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-5">
            <?php foreach($results as $r):
                $icons = [
                    'user'=>'bi-person-circle','student'=>'bi-mortarboard-fill','teacher'=>'bi-person-workspace',
                    'parent'=>'bi-people-fill','course'=>'bi-book-half','batch'=>'bi-collection-fill',
                    'activity'=>'bi-list-check','event'=>'bi-calendar-event','room'=>'bi-building',
                    'equipment'=>'bi-tools'
                ];
                $colors = [
                    'user'=>'bg-purple-600','student'=>'bg-blue-600','teacher'=>'bg-emerald-600','parent'=>'bg-pink-600',
                    'course'=>'bg-indigo-600','batch'=>'bg-cyan-600','activity'=>'bg-orange-600','event'=>'bg-rose-600',
                    'room'=>'bg-slate-600','equipment'=>'bg-amber-600'
                ];
                $icon  = $icons[$r['type']]  ?? 'bi-circle-fill';
                $color = $colors[$r['type']] ?? 'bg-gray-600';

                $url = $r['page'] . "?id=" . $r['id'];
                if($r['type']==='batch')    $url = "add_batch.php?id=".$r['id'];
                if($r['type']==='activity') $url = "academics.php#activity-".$r['id'];
            ?>
                <a href="<?=$url?>" class="card block bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:border-indigo-400">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div class="w-14 h-14 rounded-xl <?=$color?> flex items-center justify-center text-white text-2xl">
                                <i class="<?=$icon?>"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800"><?=htmlspecialchars($r['name'])?></h3>
                                <p class="text-gray-600">
                                    <?=htmlspecialchars($r['email'] ?? $r['courseName'] ?? $r['room_type'] ?? $r['category'] ?? $r['notes'] ?? '')?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge <?=$color?>"><?=ucfirst($r['type'])?></span>
                            <p class="text-xs text-gray-500 mt-2">Click to view</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>