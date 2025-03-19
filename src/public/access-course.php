<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has the student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Student') {
    header("Location: login.php");
    exit();
}

// Check if course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No course selected to access.";
    header("Location: student-dashboard.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = $_GET['id'];

// Check if course exists and is active
$stmt = $conn->prepare("
    SELECT c.course_id, c.title 
    FROM Courses c
    WHERE c.course_id = ? AND c.is_active = 1
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "The selected course does not exist or is not active.";
    header("Location: student-dashboard.php");
    exit();
}

$course = $result->fetch_assoc();

// Check if student is enrolled in this course
$stmt = $conn->prepare("
    SELECT e.enrollment_id, e.status, e.progress_percentage
    FROM Enrollments e
    WHERE e.student_id = ? AND e.course_id = ?
");
$stmt->bind_param("ii", $student_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "You are not enrolled in this course.";
    header("Location: student-dashboard.php");
    exit();
}

$enrollment = $result->fetch_assoc();

// Update last_accessed date in Courses table
$stmt = $conn->prepare("UPDATE Courses SET last_accessed = CURDATE() WHERE course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();

// Redirect to course-details.php with the course ID
header("Location: course-details.php?id=" . $course_id);
exit();
?>
