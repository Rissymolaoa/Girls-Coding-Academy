<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Handle actions
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $time_start = $_POST['event_time_start'];
    $time_end = $_POST['event_time_end'] ?? null;
    $location = trim($_POST['location']);
    $is_posted = isset($_POST['is_posted']) ? 1 : 0;

    if (isset($_POST['add_event'])) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time_start, event_time_end, location, is_posted, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssi", $title, $description, $event_date, $time_start, $time_end, $location, $is_posted);
        $message = $stmt->execute() ? "Event created successfully!" : "Error: " . $stmt->error;
        $stmt->close();
    }

    if (isset($_POST['edit_event'])) {
        $event_id = intval($_POST['event_id']);
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time_start=?, event_time_end=?, location=?, is_posted=? WHERE event_id=?");
        $stmt->bind_param("ssssssii", $title, $description, $event_date, $time_start, $time_end, $location, $is_posted, $event_id);
        $message = $stmt->execute() ? "Event updated!" : "Error: " . $stmt->error;
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? "Event deleted!" : "Error deleting.";
    $stmt->close();
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

$sql = "SELECT * FROM events $where ORDER BY event_date DESC, event_time_start DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; --primary-dark: #4338ca; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        .card-hover:hover { transform: translateY(-10px); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); }
        .event-upcoming { background: linear-gradient(135deg, #dbeafe 0%, #c7d2fe 100%); border-left: 6px solid #4f46e5; }
        .event-ongoing { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 6px solid #f59e0b; }
        .event-past { background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-left: 6px solid #6b7280; }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-800">Manage Events</h1>
            <p class="text-xl text-gray-600 mt-3">Create and organize academy events</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-8 px-8 py-5 rounded-2xl text-white font-medium text-lg <?= strpos($message, 'success') ? 'bg-green-500' : 'bg-red-500' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Add Event Form -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-8">Create New Event</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <input type="text" name="title" placeholder="Event Title" required class="px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 text-lg">
                <input type="date" name="event_date" required class="px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                <div class="flex gap-4">
                    <input type="time" name="event_time_start" required class="flex-1 px-6 py-4 border border-gray-300 rounded-xl">
                    <input type="time" name="event_time_end" placeholder="End (optional)" class="flex-1 px-6 py-4 border border-gray-300 rounded-xl">
                </div>
                <input type="text" name="location" placeholder="Location" required class="px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                <textarea name="description" rows="3" placeholder="Event Description" required class="md:col-span-2 px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200"></textarea>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_posted" value="1" checked class="w-6 h-6 text-indigo-600 rounded focus:ring-indigo-500">
                        <span class="text-lg font-medium">Publish Event</span>
                    </label>
                </div>
                <div class="md:col-span-3">
                    <button type="submit" name="add_event" class="w-full md:w-auto px-12 py-5 bg-primary hover:bg-primary-dark text-white font-bold text-xl rounded-2xl shadow-lg transition flex items-center justify-center gap-4">
                        <i class="fas fa-calendar-plus text-2xl"></i>
                        Create Event
                    </button>
                </div>
            </form>
        </div>

        <!-- Search -->
        <form method="get" class="mb-8">
            <div class="flex gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search events..." class="flex-1 px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 text-lg">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-8 py-4 rounded-xl font-semibold transition flex items-center gap-3">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>

        <!-- Events Grid -->
        <div class="space-y-8">
            <?php if ($events->num_rows === 0): ?>
                <div class="text-center py-20 bg-white rounded-2xl shadow-lg border border-gray-200">
                    <i class="fas fa-calendar-times text-8xl text-gray-300 mb-6"></i>
                    <p class="text-2xl text-gray-600">No events found</p>
                </div>
            <?php else: while ($e = $events->fetch_assoc()): 
                $today = date('Y-m-d');
                $eventDate = $e['event_date'];
                $statusClass = ($eventDate > $today) ? 'event-upcoming' : (($eventDate === $today) ? 'event-ongoing' : 'event-past');
                $statusText = ($eventDate > $today) ? 'Upcoming' : (($eventDate === $today) ? 'Today' : 'Past');
            ?>
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden card-hover transition-all <?= $statusClass ?>">
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($e['title']) ?></h3>
                                <div class="flex items-center gap-6 mt-4 text-sm">
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-calendar-day text-primary"></i>
                                        <strong><?= date("l, F j, Y", strtotime($e['event_date'])) ?></strong>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-clock text-primary"></i>
                                        <?= date("h:i A", strtotime($e['event_time_start'])) ?>
                                        <?= $e['event_time_end'] ? ' - ' . date("h:i A", strtotime($e['event_time_end'])) : '' ?>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                        <?= htmlspecialchars($e['location']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-6 py-3 rounded-full text-white font-bold text-lg
                                    <?= $statusText === 'Upcoming' ? 'bg-indigo-600' : ($statusText === 'Today' ? 'bg-amber-500' : 'bg-gray-500') ?>">
                                    <?= $statusText ?>
                                </span>
                                <?php if ($e['is_posted']): ?>
                                    <div class="mt-3 text-green-600 font-medium">
                                        <i class="fas fa-check-circle"></i> Published
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3 text-gray-500 font-medium">
                                        <i class="fas fa-eye-slash"></i> Draft
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="text-gray-700 text-lg leading-relaxed mb-8">
                            <?= nl2br(htmlspecialchars($e['description'])) ?>
                        </p>

                        <div class="flex justify-end gap-4">
                            <button onclick='openEdit(<?= json_encode($e) ?>)' 
                                    class="px-8 py-4 bg-white border-2 border-indigo-600 text-indigo-600 font-bold rounded-xl hover:bg-indigo-50 transition flex items-center gap-3">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete=<?= $e['event_id'] ?>" 
                               onclick="return confirm('Delete this event permanently?')"
                               class="px-8 py-4 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition flex items-center gap-3">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-8">
        <h3 class="text-3xl font-bold text-gray-800 mb-8">Edit Event</h3>
        <form method="POST">
            <input type="hidden" name="event_id" id="edit-id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <input type="text" name="title" id="edit-title" required placeholder="Event Title" class="px-6 py-4 border border-gray-300 rounded-xl text-lg">
                <input type="date" name="event_date" id="edit-date" required class="px-6 py-4 border border-gray-300 rounded-xl">
                <div class="flex gap-4">
                    <input type="time" name="event_time_start" id="edit-start" required class="flex-1 px-6 py-4 border border-gray-300 rounded-xl">
                    <input type="time" name="event_time_end" id="edit-end" class="flex-1 px-6 py-4 border border-gray-300 rounded-xl">
                </div>
                <input type="text" name="location" id="edit-location" required placeholder="Location" class="px-6 py-4 border border-gray-300 rounded-xl">
                <textarea name="description" id="edit-desc" rows="4" required placeholder="Description" class="md:col-span-2 px-6 py-4 border border-gray-300 rounded-xl"></textarea>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_posted" id="edit-posted" value="1" class="w-6 h-6 text-indigo-600 rounded focus:ring-indigo-500">
                        <span class="text-xl font-medium">Publish Event</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="px-10 py-5 border-2 border-gray-300 rounded-xl font-bold hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" name="edit_event" 
                        class="px-10 py-5 bg-primary text-white font-bold rounded-xl hover:bg-primary-dark transition">Update Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit-id').value = data.event_id;
    document.getElementById('edit-title').value = data.title;
    document.getElementById('edit-desc').value = data.description;
    document.getElementById('edit-date').value = data.event_date;
    document.getElementById('edit-start').value = data.event_time_start;
    document.getElementById('edit-end').value = data.event_time_end || '';
    document.getElementById('edit-location').value = data.location;
    document.getElementById('edit-posted').checked = data.is_posted == 1;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>