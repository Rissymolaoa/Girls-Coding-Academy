<?php
// accounts_dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accounts') {
    header('Location: login.php');
    exit();
}
include 'accounts_navigation.php';

// Fetch metrics (real DB queries)
$totalIncome = 0; // Query accounts_income
$totalExpenses = 0; // Query accounts_expenses
$balance = $totalIncome - $totalExpenses; // Simplified
// ... (extend with actual queries)
?>
<div class="dashboard">
    <h2>Accounts Dashboard</h2>
    <p>Overview of school finances. Track income, expenses, and ensure balanced budgeting.</p>
    
    <!-- Metrics -->
    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
        <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
            <h3 style="color: var(--success-green);">Total Income</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--text-dark);">R <?php echo number_format($totalIncome, 2); ?></p>
            <a href="income.php">View Details →</a>
        </div>
        <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
            <h3 style="color: var(--error-red);">Total Expenses</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--text-dark);">R <?php echo number_format($totalExpenses, 2); ?></p>
            <a href="expenses.php">View Details →</a>
        </div>
        <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
            <h3 style="color: var(--primary-blue);">Net Balance</h3>
            <p style="font-size: 2rem; font-weight: 700; color: <?php echo $balance >= 0 ? 'var(--success-green)' : 'var(--error-red)'; ?>;">
                R <?php echo number_format($balance, 2); ?>
            </p>
            <a href="reports.php">Full Report →</a>
        </div>
    </div>

    <!-- Quick Actions -->
    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
        <h3>Quick Actions</h3>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a style="background: var(--success-green); color: var(--white); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;" href="income.php?new=1">Record Income</a>
            <a style="background: var(--error-red); color: var(--white); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;" href="expenses.php?new=1">Log Expense</a>
            <a style="background: var(--primary-blue); color: var(--white); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;" href="budgets.php">Manage Budgets</a>
        </div>
    </section>

    <!-- Recent Transactions -->
    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
        <h3>Recent Transactions</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-gray);">
                        <th style="padding: 1rem; text-align: left;">Type</th>
                        <th style="padding: 1rem; text-align: left;">Amount</th>
                        <th style="padding: 1rem; text-align: left;">Date</th>
                        <th style="padding: 1rem; text-align: left;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Mock rows; replace with DB query -->
                    <tr><td>Income - Fees</td><td>+R 5,000</td><td>Nov 10, 2025</td><td>Approved</td></tr>
                    <tr><td>Expense - WiFi</td><td>-R 1,200</td><td>Nov 9, 2025</td><td>Paid</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>