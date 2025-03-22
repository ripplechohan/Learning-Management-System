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
    header("Location: instructor_dashboard.php");
    exit();
}

$quiz_id = $_GET['id'];

// Get quiz information
$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, q.topic, q.is_automated_grading, c.title as course_title, c.course_id
    FROM Quizzes q
    JOIN Courses c ON q.course_id = c.course_id
    WHERE q.quiz_id = ?
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if (!$quiz) {
    $_SESSION['error_message'] = "Quiz not found";
    header("Location: instructor_dashboard.php");
    exit();
}

// Get quiz questions
$stmt = $conn->prepare("
    SELECT question_id, text, question_format, answer
    FROM Questions
    WHERE quiz_id = ?
    ORDER BY question_id
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();

// Add new question if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim($_POST['question_text']);
    $question_format = $_POST['question_format'];
    
    // Process answer based on format
    if ($question_format === 'MCQ') {
        // For MCQs, we need to store options and the correct answer
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = $_POST['correct_option'];
        
        // Check if options are provided
        if (empty($option_a) || empty($option_b)) {
            $error_message = "At least options A and B are required for MCQ";
        } else {
            // Correct option letter (A, B, C, D)
            $answer = $correct_option;
            
            // Append options to question text for storage
            $options_text = "\nA. " . $option_a;
            $options_text .= "\nB. " . $option_b;
            
            if (!empty($option_c)) {
                $options_text .= "\nC. " . $option_c;
            }
            
            if (!empty($option_d)) {
                $options_text .= "\nD. " . $option_d;
            }
            
            // Combine question and options
            $question_text .= $options_text;
        }
    } else {
        // For other question types, just get the answer directly
        $answer = trim($_POST['answer']);
    }
    
    if (empty($question_text)) {
        $error_message = "Question text cannot be empty";
    } else if (!isset($error_message)) { // Only proceed if no error message set
        $stmt = $conn->prepare("
            INSERT INTO Questions (quiz_id, text, question_format, answer)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $quiz_id, $question_text, $question_format, $answer);
        
        if ($stmt->execute()) {
            $success_message = "Question added successfully";
            // Refresh the page to show the new question
            header("Location: manage_questions.php?id=" . $quiz_id);
            exit();
        } else {
            $error_message = "Error adding question: " . $conn->error;
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
        // Refresh the page to update the question list
        header("Location: manage_questions.php?id=" . $quiz_id);
        exit();
    } else {
        $error_message = "Error deleting question: " . $conn->error;
    }
}

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
        .list-group-item {
            border-left: 4px solid #007bff;
        }
        .mt-3 {
            margin-top: 15px;
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
                    <li><a href="instructor_dashboard.php">Dashboard</a></li>
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
                <p class="mt-3"><a href="instructor_dashboard.php#quizzes" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Dashboard</a></p>
                
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
                    <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?></p>
                    <p><strong>Topic:</strong> <?php echo htmlspecialchars($quiz['topic']); ?></p>
                    <p><strong>Automated Grading:</strong> <?php echo $quiz['is_automated_grading'] ? 'Enabled' : 'Disabled'; ?></p>
                </div>
                
                <div class="add-question-form">
                    <h4>Add New Question</h4>
                    <form method="post" action="manage_questions.php?id=<?php echo $quiz_id; ?>">
                        <div class="form-group">
                            <label for="question_text">Question Text:</label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_format">Question Format:</label>
                            <select class="form-control" id="question_format" name="question_format" required onchange="toggleAnswerFields()">
                                <option value="MCQ">Multiple Choice (MCQ)</option>
                                <option value="Short Answer">Short Answer</option>
                                <option value="Standardized">Standardized</option>
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
                        
                        <!-- Short Answer / Standardized Answer Field - initially hidden -->
                        <div id="text_answer" class="form-group" style="display:none;">
                            <label for="answer">Correct Answer:</label>
                            <input type="text" class="form-control" id="answer" name="answer">
                        </div>
                        
                        <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                    </form>
                </div>
                
                <div class="questions-container">
                    <h4>Current Questions (<?php echo $questions_result->num_rows; ?>)</h4>
                    
                    <?php if ($questions_result->num_rows > 0): ?>
                        <?php while($question = $questions_result->fetch_assoc()): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <span class="label label-primary"><?php echo htmlspecialchars($question['question_format']); ?></span>
                                    <div>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="editQuestion(<?php echo $question['question_id']; ?>)">
                                            <i class="fa fa-edit"></i> Edit
                                        </button>
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
            </div>
        </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function toggleAnswerFields() {
            const questionFormat = document.getElementById('question_format').value;
            const mcqOptions = document.getElementById('mcq_options');
            const textAnswer = document.getElementById('text_answer');
            
            if (questionFormat === 'MCQ') {
                mcqOptions.style.display = 'block';
                textAnswer.style.display = 'none';
                
                // Make MCQ fields required
                document.getElementById('option_a').required = true;
                document.getElementById('option_b').required = true;
                document.getElementById('answer').required = false;
            } else {
                mcqOptions.style.display = 'none';
                textAnswer.style.display = 'block';
                
                // Make text answer field required
                document.getElementById('option_a').required = false;
                document.getElementById('option_b').required = false;
                document.getElementById('answer').required = true;
            }
        }
        
        function editQuestion(questionId) {
            alert('Edit question functionality would be implemented here for question ID: ' + questionId);
            // In a real implementation, this would open a modal or redirect to an edit page
        }
        
        // Initialize the form on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleAnswerFields();
        });
    </script>
</body>
</html>