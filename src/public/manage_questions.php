<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit();
}

// Get instructor data
$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'];

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check if quiz_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid quiz ID";
    header("Location: instructor-dashboard.php");
    exit();
}

$quiz_id = $_GET['id'];

// Process the form submission first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim($_POST['question_text']);
    $question_format = $_POST['question_format'];
    
    if ($question_format === 'MCQ') {
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = $_POST['correct_option'];
        
        if (empty($option_a) || empty($option_b)) {
            $error_message = "At least options A and B are required for MCQ";
        } else {
            $answer = $correct_option;
            
            $options_text = "\nA. " . $option_a;
            $options_text .= "\nB. " . $option_b;
            
            if (!empty($option_c)) {
                $options_text .= "\nC. " . $option_c;
            }
            
            if (!empty($option_d)) {
                $options_text .= "\nD. " . $option_d;
            }
            
            $question_text .= $options_text;
        }
    } else if ($question_format === 'True/False') {
        $answer = trim($_POST['true_false_answer']);
    }
    
    if (empty($question_text) || !isset($answer)) {
        $error_message = "Question text and answer are required";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO Questions (quiz_id, text, question_format, answer)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $quiz_id, $question_text, $question_format, $answer);
            
            if ($stmt->execute()) {
                $success_message = "Question added successfully!";
                // Use JavaScript redirect to force fresh page load
                echo "<script>window.location.href='manage_questions.php?id=$quiz_id&t=" . time() . "';</script>";
                exit();
            } else {
                $error_message = "Database error: " . $conn->error;
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Delete question if requested
if (isset($_GET['delete_question']) && is_numeric($_GET['delete_question'])) {
    $question_id = $_GET['delete_question'];
    
    $stmt = $conn->prepare("DELETE FROM Questions WHERE question_id = ? AND quiz_id = ?");
    $stmt->bind_param("ii", $question_id, $quiz_id);
    
    if ($stmt->execute()) {
        $success_message = "Question deleted successfully";
        // Use JavaScript redirect for a fresh page load
        echo "<script>window.location.href='manage_questions.php?id=$quiz_id&t=" . time() . "';</script>";
        exit();
    } else {
        $error_message = "Error deleting question: " . $conn->error;
    }
}

// Now get the quiz information
$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, q.topic, q.is_automated_grading, q.is_published, c.title as course_title, c.course_id
    FROM Quizzes q
    JOIN Courses c ON q.course_id = c.course_id
    WHERE q.quiz_id = ?
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz_result = $stmt->get_result();

if ($quiz_result->num_rows === 0) {
    $_SESSION['error_message'] = "Quiz not found";
    header("Location: instructor-dashboard.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Get quiz questions - do this AFTER form processing to get fresh data
$stmt = $conn->prepare("
    SELECT question_id, text, question_format, answer
    FROM Questions
    WHERE quiz_id = ?
    ORDER BY question_id
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$question_count = $questions_result->num_rows;

// Function to parse and display MCQ options
function displayMCQOptions($questionText) {
    $lines = explode("\n", $questionText);
    
    // First line is the question, the rest are options
    $question = $lines[0];
    $options = array_slice($lines, 1);
    
    echo '<p><strong>Question:</strong> ' . htmlspecialchars($question) . '</p>';
    echo '<p><strong>Options:</strong></p>';
    echo '<ul class="list-group">';
    
    foreach ($options as $option) {
        echo '<li class="list-group-item">' . htmlspecialchars($option) . '</li>';
    }
    
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="Manage Quiz Questions - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Manage Quiz Questions</title>
    
    <!-- core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: white;
        }
        
        #page-breadcrumb {
            background: #2A95BE;
            padding: 4px 0;
            color: white;
            margin-bottom: 40px;
        }
        .vertical-center {
            display: flex;
            align-items: center;
            min-height: 100px;
        }
        .sun {
            background-image: url('images/bg1.jpg');
            background-size: cover;
        }
        .questions-container {
            margin-top: 30px;
        }
        .question-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .quiz-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .add-question-form {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .mcq-options {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .true-false-options {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .list-group-item {
            border-left: 4px solid #007bff;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .quiz-status {
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-left: 10px;
        }
        .status-draft {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-inverse" role="banner">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="home.php"><img src="images/logo.png" alt="OLMS Logo" width="200" height="74"></a>
            </div>
            <div class="collapse navbar-collapse navbar-right">
                <ul class="nav navbar-nav">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="instructor-dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a class="btn btn-primary" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="page-breadcrumb">
        <div class="vertical-center sun">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="text-center">Manage Quiz Questions</h1>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <p class="mt-3"><a href="instructor-dashboard.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Dashboard</a></p>
                
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="quiz-info">
                    <h3>
                        <?php echo htmlspecialchars($quiz['title']); ?>
                        <span class="quiz-status <?php echo isset($quiz['is_published']) && $quiz['is_published'] ? 'status-published' : 'status-draft'; ?>">
                            <?php echo isset($quiz['is_published']) && $quiz['is_published'] ? 'Published' : 'Draft'; ?>
                        </span>
                    </h3>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?></p>
                    <p><strong>Topic:</strong> <?php echo htmlspecialchars($quiz['topic']); ?></p>
                    <p><strong>Automated Grading:</strong> <?php echo $quiz['is_automated_grading'] ? 'Enabled' : 'Disabled'; ?></p>
                    
                    <div class="action-buttons">
                    <?php if (isset($quiz['is_published']) && $quiz['is_published'] == 1): ?>
    <a href="unpublish_quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-warning"
       onclick="return confirm('Are you sure you want to unpublish this quiz? Students will no longer be able to access it.')">
        <i class="fa fa-eye-slash"></i> Unpublish Quiz
    </a>
<?php else: ?>
    <a href="post_quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-success">
        <i class="fa fa-paper-plane"></i> Publish Quiz
    </a>
<?php endif; ?>
                        
                        <a href="instructor-dashboard.php#quizzes" class="btn btn-default" onclick="setActiveTab('quizzes')">
    <i class="fa fa-th-list"></i> View All Quizzes
</a>
                    </div>
                </div>
                
                <div class="add-question-form">
                    <h4>Add New Question</h4>
                    <form id="question-form" method="post" action="">
                        <div class="form-group">
                            <label for="question_text">Question Text:</label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_format">Question Format:</label>
                            <select class="form-control" id="question_format" name="question_format" required onchange="toggleAnswerFields()">
                                <option value="MCQ">Multiple Choice (MCQ)</option>
                                <option value="True/False">True/False</option>
                            </select>
                        </div>
                        
                        <!-- MCQ Options - initially visible -->
                        <div id="mcq_options" class="mcq-options">
                            <h5>Answer Options</h5>
                            
                            <div class="form-group">
                                <label for="option_a">Option A:</label>
                                <input type="text" class="form-control" id="option_a" name="option_a" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="option_b">Option B:</label>
                                <input type="text" class="form-control" id="option_b" name="option_b" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="option_c">Option C: (optional)</label>
                                <input type="text" class="form-control" id="option_c" name="option_c">
                            </div>
                            
                            <div class="form-group">
                                <label for="option_d">Option D: (optional)</label>
                                <input type="text" class="form-control" id="option_d" name="option_d">
                            </div>
                            
                            <div class="form-group">
                                <label>Correct Answer:</label>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="correct_option" value="A" checked> Option A
                                    </label>
                                </div>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="correct_option" value="B"> Option B
                                    </label>
                                </div>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="correct_option" value="C"> Option C
                                    </label>
                                </div>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="correct_option" value="D"> Option D
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- True/False Options -->
                        <div id="true_false_options" class="true-false-options" style="display:none;">
                            <h5>True/False Answer</h5>
                            <div class="form-group">
                                <label>Correct Answer:</label>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="true_false_answer" value="True" checked> True
                                    </label>
                                </div>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="true_false_answer" value="False"> False
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                    </form>
                </div>
                
                <div class="questions-container">
                    <h4>Current Questions (<?php echo $question_count; ?>)</h4>
                    
                    <?php if ($question_count > 0): ?>
                        <?php while($question = $questions_result->fetch_assoc()): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <span class="label label-primary"><?php echo htmlspecialchars($question['question_format']); ?></span>
                                    <div>
                                        <a href="manage_questions.php?id=<?php echo $quiz_id; ?>&delete_question=<?php echo $question['question_id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this question?')">
                                            <i class="fa fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                                
                                <?php if ($question['question_format'] === 'MCQ'): ?>
                                    <?php displayMCQOptions($question['text']); ?>
                                    <p><strong>Correct Answer:</strong> Option <?php echo htmlspecialchars($question['answer']); ?></p>
                                <?php elseif ($question['question_format'] === 'True/False'): ?>
                                    <p><strong>Question:</strong> <?php echo htmlspecialchars($question['text']); ?></p>
                                    <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['answer']); ?></p>
                                <?php else: ?>
                                    <p><strong>Question:</strong> <?php echo htmlspecialchars($question['text']); ?></p>
                                    <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['answer']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No questions have been added to this quiz yet. Use the form above to add questions.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons" style="margin-bottom: 30px;">
                    <a href="instructor-dashboard.php" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <?php if ($question_count > 0): ?>
                        <?php if (isset($quiz['is_published']) && $quiz['is_published']): ?>
                            <a href="unpublish_quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-warning"
                               onclick="return confirm('Are you sure you want to unpublish this quiz?')">
                                <i class="fa fa-eye-slash"></i> Unpublish Quiz
                            </a>
                        <?php else: ?>
                            <a href="post_quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-success">
                                <i class="fa fa-paper-plane"></i> Publish Quiz
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function toggleAnswerFields() {
            const questionFormat = document.getElementById('question_format').value;
            const mcqOptions = document.getElementById('mcq_options');
            const trueFalseOptions = document.getElementById('true_false_options');
            
            if (questionFormat === 'MCQ') {
                mcqOptions.style.display = 'block';
                trueFalseOptions.style.display = 'none';
                
                // Make MCQ fields required
                document.getElementById('option_a').required = true;
                document.getElementById('option_b').required = true;
            } else if (questionFormat === 'True/False') {
                mcqOptions.style.display = 'none';
                trueFalseOptions.style.display = 'block';
                
                // Make MCQ fields not required
                document.getElementById('option_a').required = false;
                document.getElementById('option_b').required = false;
            }
        }
        
        // Initialize the form on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleAnswerFields();
            
            // Set focus to quizzes tab when back button is clicked
            const backButton = document.querySelector('a[href="instructor-dashboard.php#quizzes"]');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    localStorage.setItem('activeTab', 'quizzes');
                });
            }
            
            // Optional: Confirm form submission to prevent accidental reloads
            document.getElementById('question-form').addEventListener('submit', function(e) {
                var questionText = document.getElementById('question_text').value.trim();
                if (questionText === '') {
                    e.preventDefault();
                    alert('Please enter a question text.');
                    return false;
                }
                return true;
            });
        });

        function setActiveTab(tabName) {
    localStorage.setItem('activeTab', tabName);
}
    </script>
</body>
</html>
