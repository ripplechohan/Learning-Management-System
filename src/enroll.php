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
    $_SESSION['error_message'] = "No course selected for enrollment.";
    header("Location: courses.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = $_GET['id'];

// Check if course exists and is active
$stmt = $conn->prepare("SELECT course_id, title FROM Courses WHERE course_id = ? AND is_active = 1");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "The selected course does not exist or is not active.";
    header("Location: courses.php");
    exit();
}

$course = $result->fetch_assoc();

// Check if student is already enrolled in this course
$stmt = $conn->prepare("SELECT enrollment_id FROM Enrollments WHERE student_id = ? AND course_id = ?");
$stmt->bind_param("ii", $student_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['info_message'] = "You are already enrolled in this course.";
    header("Location: course-details.php?id=" . $course_id);
    exit();
}

// Enroll the student in the course
try {
    $enrollment_date = date('Y-m-d H:i:s');
    $progress = 0;
    $status = 'Enrolled';
    
    $stmt = $conn->prepare("INSERT INTO Enrollments (student_id, course_id, enrollment_date, progress_percentage, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisds", $student_id, $course_id, $enrollment_date, $progress, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "You have successfully enrolled in " . $course['title'] . "!";
        
        // Create a notification for the enrollment
        $type = 'Course Update';
        $stmt = $conn->prepare("INSERT INTO Notifications (user_id, type, sent_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $student_id, $type);
        $stmt->execute();
        
        header("Location: student-dashboard.php");
        exit();
    } else {
        throw new Exception("Database error: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error enrolling in course: " . $e->getMessage();
    header("Location: courses.php");
    exit();
}
?>