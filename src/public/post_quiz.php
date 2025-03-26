<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid quiz ID";
    header("Location: instructor-dashboard.php");
    exit();
}

$quiz_id = $_GET['id'];

// Verify the quiz exists
$stmt = $conn->prepare("
    SELECT quiz_id, title 
    FROM Quizzes 
    WHERE quiz_id = ?
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Quiz not found";
    header("Location: instructor-dashboard.php");
    exit();
}

// Check if the quiz has any questions
$stmt = $conn->prepare("
    SELECT COUNT(*) as question_count 
    FROM Questions 
    WHERE quiz_id = ?
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$question_count = $stmt->get_result()->fetch_assoc()['question_count'];

if ($question_count == 0) {
    $_SESSION['error_message'] = "Cannot publish a quiz without questions. Please add at least one question.";
    header("Location: manage_questions.php?id=" . $quiz_id);
    exit();
}

// Update the quiz to be published
$stmt = $conn->prepare("
    UPDATE Quizzes 
    SET is_published = 1 
    WHERE quiz_id = ?
");
$stmt->bind_param("i", $quiz_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Quiz has been published and is now available to students.";
} else {
    $_SESSION['error_message'] = "Error publishing quiz: " . $conn->error;
}

// Redirect back to instructor dashboard
header("Location: instructor-dashboard.php#quizzes");
exit();
?>