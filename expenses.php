<?php
// expenses.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new expense
if (isset($_POST['create_expense']) && isset($_SESSION['user_id'])) {
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $vendor = $_POST['vendor'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];
    $receipt_file = null;

    // Handle file upload for receipt
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/expenses/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = basename($_FILES['receipt_file']['name']);
        $target_path = $upload_dir . time() . '_' . $file_name;
        if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_path)) {
            $receipt_file = $target_path;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO accounts_expenses (category_id, amount, vendor, date, description, receipt_file, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $amount, $vendor, $date, $description, $receipt_file, $status, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: expenses.php');
    exit();
} elseif (isset($_POST['create_expense']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'accounts_navigation.php';

// Fetch categories for dropdown (expense type only)
$categoryStmt = $pdo->query("SELECT category_id, name FROM accounts_categories WHERE type = 'expense' ORDER BY name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expense records
$expenseStmt = $pdo->query("SELECT e.*, c.name as category_name 
                            FROM accounts_expenses e 
                            JOIN accounts_categories c ON e.category_id = c.category_id 
                            ORDER BY e.date DESC, e.created_at DESC");
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Expense Management</h2>
                <p>Log and approve expenses such as utilities, WiFi, airtime, electricity bills, and infrastructure costs. Attach receipts for auditing.</p>
                
                <!-- Create New Expense Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="expenses.php?new=1" style="background: var(--error-red); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Log New Expense
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Expense Form -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Log Expense</h3>
                        <form action="expenses.php" method="POST" enctype="multipart/form-data" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="amount">Amount (ZAR)</label>
                                <input type="number" id="amount" name="amount" step="0.01" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="vendor">Vendor/Supplier</label>
                                <input type="text" id="vendor" name="vendor" placeholder="e.g., Eskom Electricity, Telkom WiFi" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label for="date">Date</label>
                                    <input type="date" id="date" name="date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                                <div>
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="paid">Paid</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="receipt_file">Receipt File (Optional)</label>
                                <input type="file" id="receipt_file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2" placeholder="e.g., Monthly electricity bill for campus" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <button type="submit" name="create_expense" style="background: var(--error-red); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Log Expense</button>
                            <a href="expenses.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Expenses List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Expense Records</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Vendor</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Amount (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Receipt</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Description</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No expense records yet. Log one above.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($expense['vendor']); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: var(--error-red);">- R <?php echo number_format($expense['amount'], 2); ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php 
                                                    switch($expense['status']) {
                                                        case 'pending': echo 'var(--error-red)'; break;
                                                        case 'approved': echo 'var(--secondary-blue)'; break;
                                                        case 'paid': echo 'var(--success-green)'; break;
                                                        default: echo 'var(--text-light)';
                                                    }
                                                ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($expense['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <?php if ($expense['receipt_file']): ?>
                                                    <a href="<?php echo htmlspecialchars($expense['receipt_file']); ?>" target="_blank" style="color: var(--secondary-blue); text-decoration: none;"><i class="fas fa-file-pdf"></i> View</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars(substr($expense['description'], 0, 50)) . '...'; ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $expense['expense_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script>
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this expense record?')) {
                // AJAX delete or form submit
                fetch('expenses.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>