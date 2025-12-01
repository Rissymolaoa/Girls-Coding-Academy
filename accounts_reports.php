<?php
// accounts_reports.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output (e.g., for custom date range reports)

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle custom report generation (e.g., by date range)
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default current month end

// Fetch income summary for period
$incomeStmt = $pdo->prepare("SELECT SUM(amount) as total_income, c.name as category 
                             FROM accounts_income i 
                             JOIN accounts_categories c ON i.category_id = c.category_id 
                             WHERE i.date BETWEEN ? AND ? 
                             GROUP BY c.category_id ORDER BY total_income DESC");
$incomeStmt->execute([$start_date, $end_date]);
$incomeSummary = $incomeStmt->fetchAll(PDO::FETCH_ASSOC);
$totalIncome = array_sum(array_column($incomeSummary, 'total_income'));

// Fetch expense summary for period
$expenseStmt = $pdo->prepare("SELECT SUM(amount) as total_expense, c.name as category 
                              FROM accounts_expenses e 
                              JOIN accounts_categories c ON e.category_id = c.category_id 
                              WHERE e.date BETWEEN ? AND ? 
                              GROUP BY c.category_id ORDER BY total_expense DESC");
$expenseStmt->execute([$start_date, $end_date]);
$expenseSummary = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
$totalExpenses = array_sum(array_column($expenseSummary, 'total_expense'));

// Fetch budget variance for period (fixed query: use subquery alias 'summary' correctly)
$budgetVarianceStmt = $pdo->prepare("
    SELECT summary.budgeted, summary.category_name,
           COALESCE(summary.actual_income, 0) as actual_income,
           COALESCE(summary.actual_expense, 0) as actual_expense,
           CASE WHEN summary.type = 'income' THEN COALESCE(summary.actual_income, 0) - summary.budgeted 
                ELSE summary.budgeted - COALESCE(summary.actual_expense, 0) END as variance
    FROM (
        SELECT b.amount as budgeted, c.name as category_name, c.type,
               SUM(ai.amount) as actual_income,
               SUM(ae.amount) as actual_expense
        FROM accounts_budgets b 
        JOIN accounts_categories c ON b.category_id = c.category_id
        LEFT JOIN accounts_income ai ON b.category_id = ai.category_id AND ai.date BETWEEN ? AND ?
        LEFT JOIN accounts_expenses ae ON b.category_id = ae.category_id AND ae.date BETWEEN ? AND ?
        WHERE b.period_start <= ? AND b.period_end >= ?
        GROUP BY b.budget_id
    ) as summary
");
$budgetVarianceStmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
$budgetVariance = $budgetVarianceStmt->fetchAll(PDO::FETCH_ASSOC);

// Net P&L
$netPL = $totalIncome - $totalExpenses;

// Now include navigation after processing
include 'accounts_navigation.php';
?>
            <div class="dashboard">
                <h2>Financial Reports</h2>
                <p>Generate and view comprehensive reports for the selected period (<?php echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?>). Customize dates above.</p>
                
                <!-- Date Range Filter -->
                <section style="background: var(--white); padding: 1rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <form method="GET" style="display: inline-flex; gap: 1rem; align-items: center;">
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" style="padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" style="padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                        <button type="submit" style="background: var(--secondary-blue); color: var(--white); border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Generate Report</button>
                    </form>
                </section>

                <!-- Key Metrics -->
                <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--success-green);">Total Income</h3>
                        <p style="font-size: 2rem; font-weight: 700; color: var(--text-dark);">R <?php echo number_format($totalIncome, 2); ?></p>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--error-red);">Total Expenses</h3>
                        <p style="font-size: 2rem; font-weight: 700; color: var(--text-dark);">R <?php echo number_format($totalExpenses, 2); ?></p>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--<?php echo ($netPL >= 0) ? 'success' : 'error'; ?>-green);">Net P&L</h3>
                        <p style="font-size: 2rem; font-weight: 700; color: <?php echo ($netPL >= 0) ? 'var(--success-green)' : 'var(--error-red)'; ?>;">
                            <?php echo ($netPL >= 0) ? '+' : ''; ?> R <?php echo number_format($netPL, 2); ?>
                        </p>
                    </div>
                </div>

                <!-- Income Breakdown -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Income Breakdown</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Amount (ZAR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($incomeSummary)): ?>
                                    <tr><td colspan="2" style="padding: 2rem; text-align: center; color: var(--text-light);">No income in this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($incomeSummary as $income): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($income['category']); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: var(--success-green);">R <?php echo number_format($income['total_income'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Expense Breakdown -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Expense Breakdown</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Amount (ZAR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenseSummary)): ?>
                                    <tr><td colspan="2" style="padding: 2rem; text-align: center; color: var(--text-light);">No expenses in this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($expenseSummary as $expense): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($expense['category']); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: var(--error-red);">- R <?php echo number_format($expense['total_expense'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Budget Variance -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Budget Variance</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Budgeted (ZAR)</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Actual (ZAR)</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Variance (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Period</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($budgetVariance)): ?>
                                    <tr><td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-light);">No budgets for this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($budgetVariance as $variance): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($variance['category_name']); ?></td>
                                            <td style="padding: 1rem; text-align: right;"><?php echo ($variance['type'] === 'expense' ? '-' : '+'); ?> R <?php echo number_format($variance['budgeted'], 2); ?></td>
                                            <td style="padding: 1rem; text-align: right;"><?php echo ($variance['type'] === 'income' ? '+' : '-'); ?> R <?php echo number_format($variance['type'] === 'income' ? $variance['actual_income'] : $variance['actual_expense'], 2); ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: <?php echo $variance['variance'] >= 0 ? 'var(--success-green)' : 'var(--error-red)'; ?>;">
                                                <?php echo $variance['variance'] >= 0 ? '+' : ''; ?> R <?php echo number_format($variance['variance'], 2); ?>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($variance['period_start'])) . ' - ' . date('M d, Y', strtotime($variance['period_end'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Export Options -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="#" style="background: var(--secondary-blue); color: var(--white); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">Export to PDF</a>
                    <a href="#" style="background: var(--primary-blue); color: var(--white); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin-left: 1rem;">Export to CSV</a>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Simple date range validation (client-side)
        document.querySelector('form').addEventListener('submit', function(e) {
            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;
            if (new Date(start) > new Date(end)) {
                e.preventDefault();
                alert('Start date must be before end date.');
            }
        });
    </script>
</body>
</html>