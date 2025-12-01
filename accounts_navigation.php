<?php
// accounts_navigation.php
// Common navigation bar for Accounts role, styled like navigation.php
// Assumes $_SESSION['role'] is set after login
//session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'accounts') {
    header('Location: login.php');
    exit();
}
$role = $_SESSION['role'] ?? 'accounts';
$userName = $_SESSION['firstName'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Girls Coding Academy - <?php echo ucfirst($role); ?> Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #3b82f6;
            --accent-blue: #1d4ed8;
            --accent-hover: #1e40af;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 20px rgba(0,0,0,0.1);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
            --error-red: #e74c3c;
            --success-green: #10b981;
            --text-dark: #1e293b;
            --text-light: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: var(--light-gray);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .header {
            background: var(--primary-blue);
            color: var(--white);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-light);
            z-index: 10;
            flex-shrink: 0;
        }
        .header h1 { margin: 0; font-size: 1.8rem; font-weight: 600; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-info span { font-weight: 500; }
        .logout { color: var(--white); text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; transition: background 0.3s; }
        .logout:hover { background: rgba(255,255,255,0.1); }
        .nav-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .sidebar {
            width: 250px;
            background: var(--white);
            box-shadow: var(--shadow-light);
            padding: 1rem 0;
            overflow-y: auto;
            flex-shrink: 0;
        }
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--light-gray);
            color: var(--secondary-blue);
            border-left-color: var(--secondary-blue);
        }
        .nav-item i { font-size: 1.2rem; width: 20px; }
        .main-content { 
            flex: 1; 
            padding: 2rem; 
            overflow-y: auto; 
            background: var(--light-gray);
        }
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (max-width: 768px) {
            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            .nav-container { flex-direction: column; height: auto; }
            .sidebar { width: 100%; height: auto; overflow-y: visible; }
            .main-content { padding: 1rem; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><i class="fas fa-graduation-cap"></i> Girls Coding Academy - Accounts</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($userName); ?> (<?php echo ucfirst($role); ?>)</span>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    <div class="nav-container">
        <nav class="sidebar">
            <ul class="nav-menu">
                <li><a href="accounts_dashboard.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="income.php" class="nav-item"><i class="fas fa-arrow-down"></i> Income</a></li>
                <li><a href="expenses.php" class="nav-item"><i class="fas fa-arrow-up"></i> Expenses</a></li>
                <li><a href="budgets.php" class="nav-item"><i class="fas fa-piggy-bank"></i> Budgets</a></li>
                <li><a href="accounts_reports.php" class="nav-item"><i class="fas fa-file-invoice"></i> Reports</a></li>
                <li><a href="accounts_events.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Events</a></li>
                <li><a href="accounts_help_desk.php" class="nav-item"><i class="fas fa-headset"></i> Help Desk</a></li>
            </ul>
        </nav>
        <main class="main-content">