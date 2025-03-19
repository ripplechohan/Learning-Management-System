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
    $title = $_POST['title'];
    $description = $_POST['description'] ?? null;
    $category = $_POST['category'] ?? null;
    $skill_level = $_POST['skill_level'];
    $prereq_course_id = !empty($_POST['prereq_course_id']) ? $_POST['prereq_course_id'] : null;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $is_active = $_POST['is_active'];
    
    // Validate required fields
    if (empty($title) || empty($skill_level)) {
        $_SESSION['error_message'] = "Course title and skill level are required.";
        header("Location: instructor-dashboard.php");
        exit();
    }
    
    try {
        // Insert course into database
        $stmt = $conn->prepare("
            INSERT INTO Courses (
                title, description, category, skill_level, 
                prereq_course_id, start_date, end_date, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssssssi", 
            $title, $description, $category, $skill_level,
            $prereq_course_id, $start_date, $end_date, $is_active
        );
        
        if ($stmt->execute()) {
            $course_id = $conn->insert_id;
            
            // In a real application, you would need a linking table to associate instructors with courses
            // For now, we'll assume all courses are visible to all instructors
            
            $_SESSION['success_message'] = "Course created successfully!";
        } else {
            $_SESSION['error_message'] = "Error creating course: " . $conn->error;
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