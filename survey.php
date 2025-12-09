<?php
// survey.php
// Secure survey response page for all users (students, parents, teachers, etc.)
// Access via link: survey.php?survey_id={id}&token={secure_token}
// Token validation prevents unauthorized access

session_start();

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1:3307;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Security: Require login and valid token
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Please log in to respond.');
    exit();
}

$survey_id = $_GET['survey_id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$survey_id || !$token) {
    die('Invalid access. Please use the provided survey link.');
}

// Fetch survey and validate token (generate token on launch, e.g., hash(user_id + survey_id + salt))
$stmt = $pdo->prepare("SELECT s.*, s.questions FROM marketing_feedback_surveys s WHERE s.survey_id = ? AND s.status = 'active'");
$stmt->execute([$survey_id]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    die('Survey not found or inactive.');
}

// Simple token validation (in production, store tokens in a table or use JWT)
$expected_token = hash('sha256', $_SESSION['user_id'] . $survey_id . 'secret_salt'); // Replace with secure method
if ($token !== $expected_token) {
    die('Invalid or expired link. Please request a new one.');
}

// Check if user belongs to target group (fixed: direct comparison since enum is single value)
$user_role = $_SESSION['role'] ?? '';
$target_group = $survey['target_group'];
if ($target_group !== 'all' && $user_role !== $target_group) {
    die('This survey is not for your role. Contact support.');
}

// Handle form submission
if (isset($_POST['submit_response'])) {
    $answers = $_POST['answers'] ?? []; 
    $respondent_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO marketing_feedback_responses (survey_id, respondent_id, answers) VALUES (?, ?, ?)");
    $stmt->execute([$survey_id, $respondent_id, json_encode($answers)]);

    // Update response count
    $updateStmt = $pdo->prepare("UPDATE marketing_feedback_surveys SET responses_count = responses_count + 1 WHERE survey_id = ?");
    $updateStmt->execute([$survey_id]);

 
    header('Location: survey.php?thankyou=1&survey_id=' . $survey_id);
    exit();
}

// Decode questions (JSON array: [{'text': 'Q1', 'type': 'text'}, ...])
$questions = json_decode($survey['questions'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($survey['title']); ?> - Girls Coding Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #3b82f6;
            --accent-blue: #1d4ed8;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 20px rgba(0,0,0,0.1);
            --text-dark: #1e293b;
            --text-light: #64748b;
            --success-green: #10b981;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background: var(--light-gray);
            color: var(--text-dark);
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-light);
        }
        h1 { color: var(--primary-blue); text-align: center; margin-bottom: 1rem; }
        .survey-info { text-align: center; margin-bottom: 2rem; color: var(--text-light); }
        form { display: grid; gap: 1.5rem; }
        .question { border: 1px solid var(--border-light); padding: 1.5rem; border-radius: 8px; }
        .question h3 { margin: 0 0 1rem; color: var(--text-dark); }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"], input[type="radio"], textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 6px; }
        .radio-group { display: grid; gap: 0.5rem; }
        .radio-option { display: flex; align-items: center; gap: 0.5rem; }
        button { background: var(--success-green); color: var(--white); border: none; padding: 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 1rem; }
        button:hover { background: #059669; }
        .thank-you { text-align: center; padding: 2rem; color: var(--success-green); font-size: 1.2rem; }
        .back-link { text-align: center; margin-top: 1rem; }
        .back-link a { color: var(--secondary-blue); text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_GET['thankyou'])): ?>
            <div class="thank-you">
                <i class="fas fa-check-circle" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h2>Thank You!</h2>
                <p>Your response to "<?php echo htmlspecialchars($survey['title']); ?>" has been submitted successfully.</p>
                <div class="back-link">
                    <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : ($_SESSION['role'] === 'parent' ? 'parent_dashboard.php' : 'dashboard.php'); ?>">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($survey['title']); ?></h1>
            <div class="survey-info">
                <p>This survey is for <?php echo ucfirst($target_group); ?>. It should take about <?php echo rand(5, 15); ?> minutes.</p>
            </div>
            <form action="survey.php?survey_id=<?php echo $survey_id; ?>&token=<?php echo $token; ?>" method="POST">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question">
                        <h3><?php echo htmlspecialchars($q['text']); ?></h3>
                        <?php if ($q['type'] === 'text'): ?>
                            <textarea name="answers[<?php echo $index; ?>]" rows="3" required placeholder="Your response..."></textarea>
                        <?php elseif ($q['type'] === 'multiple'): ?>
                            <div class="radio-group">
                                <div class="radio-option"><input type="radio" name="answers[<?php echo $index; ?>]" value="option1" required> Option 1</div>
                                <div class="radio-option"><input type="radio" name="answers[<?php echo $index; ?>]" value="option2" required> Option 2</div>
                                <div class="radio-option"><input type="radio" name="answers[<?php echo $index; ?>]" value="option3" required> Option 3</div>
                                <!-- Extend options dynamically if needed -->
                            </div>
                        <?php elseif ($q['type'] === 'rating'): ?>
                            <div class="radio-group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="radio-option"><input type="radio" name="answers[<?php echo $index; ?>]" value="<?php echo $i; ?>" required> <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="submit_response"><i class="fas fa-paper-plane"></i> Submit Response</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>