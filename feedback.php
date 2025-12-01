<?php
// feedback.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new survey
if (isset($_POST['create_survey']) && isset($_SESSION['user_id'])) {
    $title = $_POST['survey_title'];
    $target_group = $_POST['target_group'];
    $questions = json_encode($_POST['questions'] ?? []); // Array of questions
    $status = 'active'; // Default status
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO marketing_feedback_surveys (title, questions, target_group, status, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $questions, $target_group, $status, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: feedback.php');
    exit();
} elseif (isset($_POST['create_survey']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch surveys
$surveyStmt = $pdo->query("SELECT s.*, u.firstName, s.responses_count FROM marketing_feedback_surveys s 
                           LEFT JOIN users u ON s.created_by = u.user_id 
                           ORDER BY s.created_at DESC");
$surveys = $surveyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Feedback & Surveys</h2>
                <p>Launch surveys to collect feedback from students, parents, and stakeholders. Analyze responses for insights.</p>
                
                <!-- Create New Survey Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="feedback.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Launch New Survey
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Survey Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Launch New Survey</h3>
                        <form action="feedback.php" method="POST" id="surveyForm" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="survey_title">Survey Title</label>
                                <input type="text" id="survey_title" name="survey_title" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="target_group">Target Group</label>
                                <select id="target_group" name="target_group" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">Select...</option>
                                    <option value="students">Students</option>
                                    <option value="parents">Parents</option>
                                    <option value="teachers">Teachers</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                            <div>
                                <label>Questions (Add dynamically)</label>
                                <div id="questionsContainer">
                                    <div class="question-row" style="display: grid; gap: 0.5rem; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                        <input type="text" name="questions[0][text]" placeholder="Question 1" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                                        <select name="questions[0][type]" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                                            <option value="text">Short Text</option>
                                            <option value="multiple">Multiple Choice</option>
                                            <option value="rating">Rating (1-5)</option>
                                        </select>
                                        <button type="button" onclick="removeQuestion(this)" style="background: var(--error-red); color: var(--white); border: none; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer;">Remove</button>
                                    </div>
                                </div>
                                <button type="button" onclick="addQuestion()" style="background: var(--primary-blue); color: var(--white); border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer;">Add Question</button>
                            </div>
                            <button type="submit" name="create_survey" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Launch Survey</button>
                            <a href="feedback.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Surveys List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Active Surveys</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Title</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Target Group</th>
                                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border-light);">Responses</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Created By</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($surveys)): ?>
                                    <tr>
                                        <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-light);">No surveys yet. Launch one to collect feedback!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($surveys as $survey): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($survey['title']); ?></td>
                                            <td style="padding: 1rem;"><?php echo ucfirst($survey['target_group']); ?></td>
                                            <td style="padding: 1rem; text-align: center;"><?php echo $survey['responses_count']; ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php echo $survey['status'] === 'active' ? 'var(--success-green)' : 'var(--error-red)'; ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($survey['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($survey['firstName'] ?? 'Unknown'); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($survey['created_at'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-eye"></i> View Responses</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $survey['survey_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
        let questionIndex = 1;
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const row = document.createElement('div');
            row.className = 'question-row';
            row.style = 'display: grid; gap: 0.5rem; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--border-light); border-radius: 8px;';
            row.innerHTML = `
                <input type="text" name="questions[${questionIndex}][text]" placeholder="Question ${questionIndex + 1}" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                <select name="questions[${questionIndex}][type]" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 4px;">
                    <option value="text">Short Text</option>
                    <option value="multiple">Multiple Choice</option>
                    <option value="rating">Rating (1-5)</option>
                </select>
                <button type="button" onclick="removeQuestion(this)" style="background: var(--error-red); color: var(--white); border: none; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer;">Remove</button>
            `;
            container.appendChild(row);
            questionIndex++;
        }

        function removeQuestion(button) {
            button.parentElement.remove();
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this survey?')) {
                // AJAX delete or form submit
                fetch('feedback.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>