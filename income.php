<?php
// income.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new income
if (isset($_POST['create_income']) && isset($_SESSION['user_id'])) {
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $source = $_POST['source'];
    $invoice_id = !empty($_POST['invoice_id']) ? $_POST['invoice_id'] : null;
    $date = $_POST['date'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO accounts_income (category_id, amount, source, invoice_id, date, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $amount, $source, $invoice_id, $date, $description, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: income.php');
    exit();
} elseif (isset($_POST['create_income']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'accounts_navigation.php';

// Fetch categories for dropdown
$categoryStmt = $pdo->query("SELECT category_id, name FROM accounts_categories WHERE type = 'income' ORDER BY name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch invoices for linking (optional)
$invoiceStmt = $pdo->query("SELECT invoice_id, invoice_number, amount FROM invoices WHERE status = 'paid' ORDER BY created_at DESC LIMIT 20");
$invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch income records
$incomeStmt = $pdo->query("SELECT i.*, c.name as category_name, inv.invoice_number 
                           FROM accounts_income i 
                           JOIN accounts_categories c ON i.category_id = c.category_id 
                           LEFT JOIN invoices inv ON i.invoice_id = inv.invoice_id 
                           ORDER BY i.date DESC, i.created_at DESC");
$incomes = $incomeStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Income Management</h2>
                <p>Record and track all incoming revenues, such as enrollment fees, grants, and event proceeds. Link to invoices for automatic reconciliation.</p>
                
                <!-- Create New Income Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="income.php?new=1" style="background: var(--success-green); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Record New Income
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Income Form -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Record Income</h3>
                        <form action="income.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
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
                                <label for="source">Source</label>
                                <input type="text" id="source" name="source" placeholder="e.g., Enrollment Fees from Batch CSS_3" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="invoice_id">Link to Invoice (Optional)</label>
                                <select id="invoice_id" name="invoice_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">No Invoice</option>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <option value="<?php echo $invoice['invoice_id']; ?>"><?php echo htmlspecialchars($invoice['invoice_number'] . ' - R ' . $invoice['amount']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label for="date">Date</label>
                                    <input type="date" id="date" name="date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                            </div>
                            <div>
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2" placeholder="Additional details..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <button type="submit" name="create_income" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Record Income</button>
                            <a href="income.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Income List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Income Records</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Source</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Amount (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Invoice</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Description</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($incomes)): ?>
                                    <tr>
                                        <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-light);">No income records yet. Add one above.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($incomes as $income): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($income['date'])); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($income['category_name']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($income['source']); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: var(--success-green);">R <?php echo number_format($income['amount'], 2); ?></td>
                                            <td style="padding: 1rem;"><?php echo $income['invoice_number'] ? htmlspecialchars($income['invoice_number']) : 'N/A'; ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars(substr($income['description'], 0, 50)) . '...'; ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $income['income_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
            if (confirm('Are you sure you want to delete this income record?')) {
                // AJAX delete or form submit
                fetch('income.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>