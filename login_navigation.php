<?php
// login_navigation.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            --text-dark: #1e293b;
            --text-light: #64748b;
        }

        header {
            background: rgba(30, 58, 138, 0.95);
            backdrop-filter: blur(10px);
            color: var(--white);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-light);
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--secondary-blue);
            transform: translateY(-2px);
        }

        .nav-links i {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            header h1 {
                font-size: 1.5rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                flex-direction: column;
                gap: 0.75rem;
            }

            .nav-links a {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Girls Coding Academy</h1>
        <nav class="nav-links">
            <a href="login.html"><i class="fas fa-home"></i> Login</a>
            <a href="help_desk.php"><i class="fas fa-headset"></i> Help Desk</a>
            <a href="prospectus.php"><i class="fas fa-book"></i> Prospectus</a>
            <a href="about.php"><i class="fas fa-info-circle"></i> About the School</a>
            <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        </nav>
    </header>
</body>
</html>