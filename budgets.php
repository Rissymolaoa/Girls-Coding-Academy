<?php
// budgets.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new budget
if (isset($_POST['create_budget']) && isset($_SESSION['user_id'])) {
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $period_start = $_POST['period_start'];
    $period_end = $_POST['period_end'];
    $notes = $_POST['notes'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO accounts_budgets (category_id, amount, period_start, period_end, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $amount, $period_start, $period_end, $notes, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: budgets.php');
    exit();
} elseif (isset($_POST['create_budget']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'accounts_navigation.php';

// Fetch categories for dropdown (both income and expense)
$categoryStmt = $pdo->query("SELECT category_id, name, type FROM accounts_categories ORDER BY type, name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch budgets with category names and simple variance calculation
$budgetStmt = $pdo->query("
    SELECT b.*, c.name as category_name, c.type,
           (SELECT SUM(amount) FROM accounts_income ai WHERE ai.category_id = b.category_id AND ai.date BETWEEN b.period_start AND b.period_end) as actual_income,
           (SELECT SUM(amount) FROM accounts_expenses ae WHERE ae.category_id = b.category_id AND ae.date BETWEEN b.period_start AND b.period_end) as actual_expense
    FROM accounts_budgets b 
    JOIN accounts_categories c ON b.category_id = c.category_id 
    ORDER BY b.period_start DESC, b.created_at DESC
");
$budgets = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate variance for each (income: actual - budget; expense: budget - actual)
foreach ($budgets as &$budget) {
    if ($budget['type'] === 'income') {
        $budget['variance'] = ($budget['actual_income'] ?? 0) - $budget['amount'];
        $budget['variance_color'] = ($budget['variance'] >= 0) ? 'success-green' : 'error-red';
    } else {
        $budget['variance'] = $budget['amount'] - ($budget['actual_expense'] ?? 0);
        $budget['variance_color'] = ($budget['variance'] >= 0) ? 'success-green' : 'error-red';
    }
}
?>
            <div class="dashboard">
                <h2>Budget Management</h2>
                <p>Create and monitor budgets for income and expense categories over specific periods. Track variance against actuals for financial control.</p>
                
                <!-- Create New Budget Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="budgets.php?new=1" style="background: var(--primary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Create New Budget
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Budget Form -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Create Budget</h3>
                        <form action="budgets.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name'] . ' (' . $category['type'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="amount">Budget Amount (ZAR)</label>
                                <input type="number" id="amount" name="amount" step="0.01" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label for="period_start">Period Start</label>
                                    <input type="date" id="period_start" name="period_start" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                                <div>
                                    <label for="period_end">Period End</label>
                                    <input type="date" id="period_end" name="period_end" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                            </div>
                            <div>
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="2" placeholder="e.g., Annual budget for utilities" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <button type="submit" name="create_budget" style="background: var(--primary-blue); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Create Budget</button>
                            <a href="budgets.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Budgets List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Budgets Overview</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Budget Amount (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Period</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Actual Amount</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Variance (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Notes</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($budgets)): ?>
                                    <tr>
                                        <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-light);">No budgets created yet. Set one up above.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($budgets as $budget): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($budget['category_name']); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold;"><?php echo ($budget['type'] === 'expense' ? '-' : '+'); ?> R <?php echo number_format($budget['amount'], 2); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($budget['period_start'])) . ' - ' . date('M d, Y', strtotime($budget['period_end'])); ?></td>
                                            <td style="padding: 1rem; text-align: right;"><?php echo ($budget['type'] === 'expense' ? '-' : '+'); ?> R <?php echo number_format($budget['type'] === 'income' ? ($budget['actual_income'] ?? 0) : ($budget['actual_expense'] ?? 0), 2); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: <?php echo $budget['type'] === 'income' ? ($budget['variance'] >= 0 ? 'var(--success-green)' : 'var(--error-red)') : ($budget['variance'] >= 0 ? 'var(--success-green)' : 'var(--error-red)'); ?>;">
                                                <?php echo $budget['type'] === 'income' ? '+' : ''; ?> R <?php echo number_format($budget['variance'], 2); ?>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($budget['notes'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $budget['budget_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
            if (confirm('Are you sure you want to delete this budget?')) {
                // AJAX delete or form submit
                fetch('budgets.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>