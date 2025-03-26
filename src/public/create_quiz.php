<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instructor_id = $_SESSION['user_id'];
    
    // Get form data
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $topic = trim($_POST['topic']);
    $is_automated_grading = isset($_POST['is_automated_grading']) ? $_POST['is_automated_grading'] : 0;
    
    // Validate input
    if (empty($title) || empty($topic) || empty($course_id)) {
        $_SESSION['error_message'] = "All fields are required";
        header("Location: instructor-dashboard.php#quizzes");
        exit();
    }
    
    // Check if course exists and instructor has access (optional but recommended)
    $stmt = $conn->prepare("SELECT course_id FROM Courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid course selected";
        header("Location: instructor-dashboard.php#quizzes");
        exit();
    }
    
    // Insert quiz into database
    $stmt = $conn->prepare("
        INSERT INTO Quizzes (course_id, title, is_automated_grading, topic, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isis", $course_id, $title, $is_automated_grading, $topic);
    
    if ($stmt->execute()) {
        $quiz_id = $conn->insert_id;
        $_SESSION['success_message'] = "Quiz created successfully";
        
        // Redirect to quiz question management page
        header("Location: manage_questions.php?id=" . $quiz_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Error creating quiz: " . $conn->error;
        header("Location: instructor-dashboard.php#quizzes");
        exit();
    }
} else {
    // If accessed directly without form submission
    header("Location: instructor-dashboard.php");
    exit();
}
?>
