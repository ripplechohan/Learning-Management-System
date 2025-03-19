<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Instructor') {
    header("Location: login.php");
    exit();
}

// Get instructor ID
$instructor_id = $_SESSION['user_id'];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect form data
    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $description = $_POST['description'] ?? null;
    $due_date = $_POST['due_date'];
    $topic = $_POST['topic'] ?? null;
    $grading_criteria = $_POST['grading_criteria'] ?? null;
    $is_active = $_POST['is_active'];
    
    // Validate required fields
    if (empty($course_id) || empty($title) || empty($due_date)) {
        $_SESSION['error_message'] = "Course ID, assignment title, and due date are required.";
        header("Location: instructor-dashboard.php");
        exit();
    }
    
    try {
        // Insert assignment into database
        $stmt = $conn->prepare("
            INSERT INTO Assignments (
                course_id, title, description, due_date, 
                topic, grading_criteria, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "isssssi", 
            $course_id, $title, $description, $due_date,
            $topic, $grading_criteria, $is_active
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Assignment created successfully!";
        } else {
            $_SESSION['error_message'] = "Error creating assignment: " . $conn->error;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    // Redirect back to instructor dashboard
    header("Location: instructor-dashboard.php");
    exit();
}

// If someone tries to access this file directly without submitting the form
header("Location: instructor-dashboard.php");
exit();
?>