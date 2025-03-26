<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Student') {
    header("Location: login.php");
    exit();
}

// Get student data
$student_id = $_SESSION['user_id'];

// Check if quiz ID was actually passed through
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $quiz_id = $_GET['id'];
} else {
    $_SESSION['error_message'] = "No quiz selected.";
    header("Location: student-dashboard.php");
    exit();
}

// Check if student has already completed this quiz
$stmt = $conn->prepare("
    SELECT student_quiz_id 
    FROM StudentQuizzes 
    WHERE student_id = ? AND quiz_id = ? AND is_completed = 1
");
$stmt->bind_param("ii", $student_id, $quiz_id);
$stmt->execute();
$completed_result = $stmt->get_result();

if ($completed_result->num_rows > 0) {
    $_SESSION['error_message'] = "You have already completed this quiz.";
    header("Location: student-dashboard.php#quizzes");
    exit();
}

// Process quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    // Create a new student quiz record
    $stmt = $conn->prepare("
        INSERT INTO StudentQuizzes (student_id, quiz_id, total_questions)
        VALUES (?, ?, (SELECT COUNT(*) FROM Questions WHERE quiz_id = ?))
    ");
    $stmt->bind_param("iii", $student_id, $quiz_id, $quiz_id);
    $stmt->execute();
    
    // Get the ID of the inserted student quiz
    $student_quiz_id = $conn->insert_id;
    
    // Get all questions for this quiz
    $stmt = $conn->prepare("SELECT question_id, answer FROM Questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $questions_result = $stmt->get_result();
    
    $score = 0;
    $total_questions = $questions_result->num_rows;
    
    // Process each answer
    while ($question = $questions_result->fetch_assoc()) {
        $question_id = $question['question_id'];
        $answer_key = "question_" . $question_id;
        
        if (isset($_POST[$answer_key])) {
            $selected_answer = $_POST[$answer_key];
            
            // Get the correct answer
            $correct_answer = $question['answer'];
            
            // Check if the answer is correct
            $is_correct = ($selected_answer == $correct_answer);
            if ($is_correct) {
                $score++;
            }
            
            // Save the student's answer
            $stmt = $conn->prepare("
                INSERT INTO StudentQuizAnswers (student_quiz_id, question_id, selected_answer, is_correct)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issi", $student_quiz_id, $question_id, $selected_answer, $is_correct);
            $stmt->execute();
        }
    }
    
    // Update the student quiz record with the score and completed status
    $stmt = $conn->prepare("
        UPDATE StudentQuizzes
        SET score = ?, is_completed = 1, completed_at = NOW()
        WHERE student_quiz_id = ?
    ");
    $stmt->bind_param("ii", $score, $student_quiz_id);
    $stmt->execute();
    
    // Redirect to dashboard with success message
    $_SESSION['success_message'] = "Quiz submitted successfully! Your score: $score/$total_questions";
    header("Location: student-dashboard.php#quizzes");
    exit();
}

// Functions to display different question types
function displayMCQOptions($questionText, $question_id) {
    $lines = explode("\n", $questionText);
    
    // First line is the question, the rest are options
    $question = $lines[0];
    $options = array_slice($lines, 1);
    
    echo '<p><strong>Question:</strong> ' . htmlspecialchars($question) . '</p>';
    echo '<p><strong>Options:</strong></p>';
    echo '<ul class="list-group">';
    
    // Loop through and create the radio button options
    foreach ($options as $option) {
        // Extract the option letter (A, B, C, etc.) from the beginning
        $option_letter = trim(substr($option, 0, 1)); 
        
        echo '<li class="list-group-item">';
        echo '<input type="radio" name="question_' . $question_id . '" value="' . htmlspecialchars($option_letter) . '" required> ';
        echo '<label>' . htmlspecialchars($option) . '</label>';
        echo '</li>';
    }
    
    echo '</ul>';
}

function displayTrueFalseOptions($questionText, $question_id) {
    echo '<p><strong>Question:</strong> ' . htmlspecialchars($questionText) . '</p>';
    echo '<ul class="list-group">';
    
    echo '<li class="list-group-item">';
    echo '<input type="radio" name="question_' . $question_id . '" value="True" required> ';
    echo '<label>True</label>';
    echo '</li>';
    
    echo '<li class="list-group-item">';
    echo '<input type="radio" name="question_' . $question_id . '" value="False" required> ';
    echo '<label>False</label>';
    echo '</li>';
    
    echo '</ul>';
}

function getQuestions($quiz_id) {
    global $conn;
    
    // Check if the quiz exists and is published
    $stmt = $conn->prepare("SELECT is_published FROM Quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_result = $stmt->get_result();
    
    if ($quiz_result->num_rows === 0) {
        echo '<div class="alert alert-danger">Quiz not found.</div>';
        return 0;
    }
    
    $quiz = $quiz_result->fetch_assoc();
    if (!$quiz['is_published']) {
        echo '<div class="alert alert-warning">This quiz is not yet available.</div>';
        return 0;
    }
    
    // Get the questions
    $stmt = $conn->prepare("SELECT * FROM Questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-info">No questions available for this quiz.</div>';
        return 0;
    }
    
    // Iterate through each question
    while ($row = $result->fetch_assoc()) {
        $question_id = $row["question_id"];
        $text = $row["text"];
        $question_format = $row["question_format"];
        
        echo '<div class="question-container mb-4">';
        
        // Display based on question format
        if ($question_format == "MCQ") { 
            displayMCQOptions($text, $question_id); 
        } elseif ($question_format == "True/False") {
            displayTrueFalseOptions($text, $question_id);
        }
        
        echo '</div>';
    }
    
    return $result->num_rows; // Return the number of questions
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Attempt Quiz - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Attempt Quiz</title>
    
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

        .questions-container {
            margin: 30px auto;
            max-width: 800px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .question-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
        }
        
        .list-group-item {
            border-left: 4px solid #007bff;
            margin-bottom: 5px;
        }
        
        .list-group-item:hover {
            background-color: #f5f5f5;
        }

        .quiz-info {
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        
        .mb-4 {
            margin-bottom: 1.5rem;
        }
        
        .mt-4 {
            margin-top: 1.5rem;
        }
        
        .ml-2 {
            margin-left: 0.5rem;
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
                    <li><a href="student-dashboard.php">Dashboard</a></li>
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
                        <h1 class="text-center">Attempt Quiz</h1>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <div class="questions-container">
        <?php
        // Get quiz information
        $stmt = $conn->prepare("SELECT q.title, c.title as course_title, q.is_published FROM Quizzes q JOIN Courses c ON q.course_id = c.course_id WHERE q.quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $quiz_info = $stmt->get_result()->fetch_assoc();
        
        if ($quiz_info && $quiz_info['is_published']): 
        ?>
            <div class="quiz-info">
                <h3><?php echo htmlspecialchars($quiz_info['title']); ?></h3>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($quiz_info['course_title']); ?></p>
                <p><strong>Instructions:</strong> Select the best answer for each question. All questions are required.</p>
            </div>
            
            <form action="attempt_quiz.php?id=<?php echo $quiz_id; ?>" method="POST">
                <?php 
                // Get and display all questions
                $question_count = getQuestions($quiz_id);
                
                if ($question_count > 0): 
                ?>
                <div class="mt-4">
                    <button type="submit" name="submit_quiz" class="btn btn-primary">Submit Quiz</button>
                    <a href="student-dashboard.php#quizzes" class="btn btn-secondary ml-2">Cancel</a>
                </div>
                <?php endif; ?>
            </form>
        <?php elseif ($quiz_info && !$quiz_info['is_published']): ?>
            <div class="alert alert-warning">
                <h4>Quiz Not Available</h4>
                <p>This quiz has not been published by the instructor yet.</p>
                <a href="student-dashboard.php#quizzes" class="btn btn-primary mt-4">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4>Quiz Not Found</h4>
                <p>The requested quiz could not be found.</p>
                <a href="student-dashboard.php#quizzes" class="btn btn-primary mt-4">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Confirm before leaving the page
        window.addEventListener('beforeunload', function(e) {
            // Only prompt if the form has been started but not submitted
            const radioButtons = document.querySelectorAll('input[type="radio"]:checked');
            if (radioButtons.length > 0 && radioButtons.length < <?php echo $question_count ?? 0; ?>) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Disable the confirmation when the form is submitted
        document.querySelector('form')?.addEventListener('submit', function() {
            window.removeEventListener('beforeunload', function() {});
        });
    </script>
</body>
</html>