<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

$student_id = (int)($_GET['student_id'] ?? 0);

// First query: Get student info and active enrollment
$student_sql = "
    SELECT u.firstName, u.lastName, c.courseName, b.batch_code
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN course_enrollments ce ON s.student_id = ce.student_id AND ce.status = 'active'
    LEFT JOIN batches b ON ce.batch_id = b.batch_id
    LEFT JOIN courses c ON b.course_id = c.course_id
    WHERE s.student_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();

if (!$student) die("Student not found");

// Second query: Get invoices with their individual payments
$invoices_sql = "
    SELECT 
        i.invoice_id, 
        i.invoice_number, 
        i.amount, 
        i.status, 
        i.due_date,
        COALESCE(SUM(p.amount), 0) as paid_amount
    FROM students s
    LEFT JOIN course_enrollments ce ON s.student_id = ce.student_id
    LEFT JOIN invoices i ON ce.enrollment_id = i.enrollment_id
    LEFT JOIN payments p ON i.invoice_id = p.invoice_id AND p.status = 'completed'
    WHERE s.student_id = ? AND i.invoice_id IS NOT NULL
    GROUP BY i.invoice_id
    ORDER BY i.due_date DESC
";

$stmt = $conn->prepare($invoices_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$invoices_result = $stmt->get_result();

$invoices = [];
$total_due = 0;
$total_paid = 0;

while ($row = $invoices_result->fetch_assoc()) {
    $invoices[] = $row;
    $total_due += $row['amount'];
    $total_paid += $row['paid_amount'];
}

$outstanding = $total_due - $total_paid;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - <?= htmlspecialchars($student['firstName']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>:root { --primary: #4f46e5; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8">
    <div class="max-w-5xl mx-auto bg-white rounded-xl shadow-lg border border-gray-200">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-8 rounded-t-xl">
            <h1 class="text-3xl font-bold">Payment History</h1>
            <p class="text-indigo-100 text-lg mt-2">
                <?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?>
                • <?= htmlspecialchars(($student['courseName'] ?? '—') . ' • ' . ($student['batch_code'] ?? '—')) ?>
            </p>
        </div>

        <div class="p-10">
            <div class="grid grid-cols-3 gap-6 mb-10">
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                    <p class="text-green-700 font-medium">Total Paid</p>
                    <p class="text-3xl font-bold text-green-700 mt-2">M <?= number_format($total_paid, 2) ?></p>
                </div>
                <div class="bg-gray-50 border border-gray-300 rounded-lg p-6 text-center">
                    <p class="text-gray-700 font-medium">Total Due</p>
                    <p class="text-3xl font-bold text-gray-700 mt-2">M <?= number_format($outstanding, 2) ?></p>
                </div>
                <div class="rounded-lg p-6 text-center border <?= $outstanding > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200' ?>">
                    <p class="<?= $outstanding > 0 ? 'text-red-700' : 'text-green-700' ?> font-medium">Outstanding</p>
                    <p class="text-3xl font-bold <?= $outstanding > 0 ? 'text-red-700' : 'text-green-700' ?> mt-2">
                        M <?= number_format($outstanding, 2) ?>
                    </p>
                </div>
            </div>

            <?php if (empty($invoices)): ?>
                <div class="text-center py-16 bg-gray-50 rounded-lg">
                    <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                    <p class="text-2xl font-medium text-gray-700">No invoices found</p>
                    <p class="text-gray-500">This student has no payment records.</p>
                </div>
            <?php else: ?>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-6 py-4 font-semibold">Invoice #</th>
                            <th class="px-6 py-4 font-semibold">Amount</th>
                            <th class="px-6 py-4 font-semibold">Due Date</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): 
                            $paid = $inv['paid_amount'];
                            $outstanding_inv = $inv['amount'] - $paid;
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4"><?= htmlspecialchars($inv['invoice_number'] ?? '—') ?></td>
                            <td class="px-6 py-4">M <?= number_format($inv['amount'], 2) ?></td>
                            <td class="px-6 py-4"><?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—' ?></td>
                            <td class="px-6 py-4">
                                <span class="px-4 py-2 rounded-full text-xs font-medium
                                    <?= $inv['status'] == 'paid' ? 'bg-green-100 text-green-700' : 
                                        ($inv['status'] == 'overdue' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') ?>">
                                    <?= ucfirst($inv['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="text-sm">
                                    <span class="font-medium text-green-700">M <?= number_format($paid, 2) ?></span>
                                    <?php if ($outstanding_inv > 0): ?>
                                        <span class="text-red-600"> (-M <?= number_format($outstanding_inv, 2) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="mt-10 text-center">
                <a href="academics.php" class="inline-block px-8 py-4 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                    Back to Students
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>