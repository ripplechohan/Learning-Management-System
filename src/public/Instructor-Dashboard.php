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

// Fetch all courses (in a real scenario, you would filter by instructor)
$stmt = $conn->prepare("
    SELECT course_id, title, description, category, skill_level, start_date, end_date, is_active 
    FROM Courses 
    LIMIT 10
");
$stmt->execute();
$courses = $stmt->get_result();
$course_count = $courses->num_rows;

// Fetch upcoming assignment deadlines
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, a.due_date, c.title as course_title, c.course_id 
    FROM Assignments a
    JOIN Courses c ON a.course_id = c.course_id
    WHERE a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->execute();
$upcoming_deadlines = $stmt->get_result();

// Fetch all assignments grouped by course for the assignments tab
$stmt = $conn->prepare("
    SELECT c.course_id, c.title as course_title, 
           a.assignment_id, a.title as assignment_title, a.description, 
           a.due_date, a.topic, a.is_active
    FROM Courses c
    LEFT JOIN Assignments a ON c.course_id = a.course_id
    ORDER BY c.title, a.due_date
");
$stmt->execute();
$course_assignments = $stmt->get_result();

// Fetch upcoming quiz deadlines
$stmt = $conn->prepare("
    SELECT c.course_id, c.title as course_title, 
           q.quiz_id, q.title as quiz_title, q.is_automated_grading, 
           q.topic, q.created_at, q.is_published
    FROM Courses c
    LEFT JOIN Quizzes q ON c.course_id = q.course_id
    ORDER BY c.title, q.created_at
");
$stmt->execute();
$upcoming_quizzes = $stmt->get_result();

// Group assignments by course
$assignments_by_course = [];
while ($row = $course_assignments->fetch_assoc()) {
    $course_id = $row['course_id'];
    $course_title = $row['course_title'];
    
    if (!isset($assignments_by_course[$course_id])) {
        $assignments_by_course[$course_id] = [
            'course_title' => $course_title,
            'assignments' => []
        ];
    }
    
    if ($row['assignment_id']) {
        $assignments_by_course[$course_id]['assignments'][] = [
            'id' => $row['assignment_id'],
            'title' => $row['assignment_title'],
            'description' => $row['description'],
            'due_date' => $row['due_date'],
            'topic' => $row['topic'],
            'is_active' => $row['is_active']
        ];
    }
}

// Fetch all quizzes grouped by course for the quizzes tab - WITH is_published field
$stmt = $conn->prepare("
    SELECT c.course_id, c.title as course_title, 
           q.quiz_id, q.title as quiz_title, q.is_automated_grading, 
           q.topic, q.created_at, q.is_published
    FROM Courses c
    LEFT JOIN Quizzes q ON c.course_id = q.course_id
    ORDER BY c.title, q.created_at
");
$stmt->execute();
$course_quizzes = $stmt->get_result();

// Group quizzes by course
$quizzes_by_course = [];
while ($row = $course_quizzes->fetch_assoc()) {
    $course_id = $row['course_id'];
    $course_title = $row['course_title'];

    if (!isset($quizzes_by_course[$course_id])) {
        $quizzes_by_course[$course_id] = [
            'course_title' => $course_title,
            'quizzes' => []
        ];
    }

    if ($row['quiz_id']) {
        $quizzes_by_course[$course_id]['quizzes'][] = [
            'id' => $row['quiz_id'],
            'title' => $row['quiz_title'],
            'automated_grading' => $row['is_automated_grading'],
            'topic' => $row['topic'],
            'created_at' => $row['created_at'],
            'is_published' => $row['is_published']
        ];
    }
}

// Fetch pending student questions (simplified without specific filtering)
$stmt = $conn->prepare("
    SELECT n.notification_id, u.name as student_name, n.sent_at 
    FROM Notifications n
    JOIN Users u ON n.user_id = u.user_id
    WHERE n.type = 'Course Update'
    LIMIT 5
");
$stmt->execute();
$pending_questions = $stmt->get_result();
$question_count = $pending_questions->num_rows;

// Get student performance data by course
$stmt = $conn->prepare("
    SELECT c.course_id, c.title as course_title, u.user_id as student_id, u.name as student_name, 
           e.progress_percentage,
    CASE 
        WHEN e.progress_percentage >= 90 THEN 'A'
        WHEN e.progress_percentage >= 80 THEN 'B'
        WHEN e.progress_percentage >= 70 THEN 'C'
        WHEN e.progress_percentage >= 60 THEN 'D'
        ELSE 'F'
    END as grade
    FROM Enrollments e
    JOIN Users u ON e.student_id = u.user_id
    JOIN Courses c ON e.course_id = c.course_id
    ORDER BY c.title, u.name
");
$stmt->execute();
$student_performance = $stmt->get_result();

// Structure student performance data by course for easier navigation
$performance_by_course = [];
while ($student = $student_performance->fetch_assoc()) {
    $course_id = $student['course_id'];
    $course_title = $student['course_title'];
    
    if (!isset($performance_by_course[$course_id])) {
        $performance_by_course[$course_id] = [
            'course_title' => $course_title,
            'students' => []
        ];
    }
    
    $performance_by_course[$course_id]['students'][] = [
        'id' => $student['student_id'],
        'name' => $student['student_name'],
        'progress' => $student['progress_percentage'],
        'grade' => $student['grade']
    ];
}

// Fetch assignment submissions
$stmt = $conn->prepare("
    SELECT s.submission_id, a.assignment_id, a.title as assignment_title, 
           c.course_id, c.title as course_title,
           u.user_id as student_id, u.name as student_name,
           s.date_submitted, s.grade
    FROM Submissions s
    JOIN Assignments a ON s.assignment_id = a.assignment_id
    JOIN Courses c ON a.course_id = c.course_id
    JOIN Users u ON s.student_id = u.user_id
    ORDER BY c.title, a.title, u.name
");
$stmt->execute();
$assignment_submissions = $stmt->get_result();

// Group assignment submissions by course and assignment
$submissions_by_course = [];
while ($submission = $assignment_submissions->fetch_assoc()) {
    $course_id = $submission['course_id'];
    $course_title = $submission['course_title'];
    $assignment_id = $submission['assignment_id'];
    $assignment_title = $submission['assignment_title'];
    
    if (!isset($submissions_by_course[$course_id])) {
        $submissions_by_course[$course_id] = [
            'course_title' => $course_title,
            'assignments' => []
        ];
    }
    
    if (!isset($submissions_by_course[$course_id]['assignments'][$assignment_id])) {
        $submissions_by_course[$course_id]['assignments'][$assignment_id] = [
            'title' => $assignment_title,
            'submissions' => []
        ];
    }
    
    $submissions_by_course[$course_id]['assignments'][$assignment_id]['submissions'][] = [
        'id' => $submission['submission_id'],
        'student_id' => $submission['student_id'],
        'student_name' => $submission['student_name'],
        'date_submitted' => $submission['date_submitted'],
        'grade' => $submission['grade'],
        'feedback' => $submission['feedback']
    ];
}

// Fetch quiz results
$stmt = $conn->prepare("
    SELECT sq.student_quiz_id, q.quiz_id, q.title as quiz_title, 
           c.course_id, c.title as course_title,
           u.user_id as student_id, u.name as student_name,
           sq.score, sq.total_questions, sq.completed_at
    FROM StudentQuizzes sq
    JOIN Quizzes q ON sq.quiz_id = q.quiz_id
    JOIN Courses c ON q.course_id = c.course_id
    JOIN Users u ON sq.student_id = u.user_id
    WHERE sq.is_completed = 1
    ORDER BY c.title, q.title, u.name
");
$stmt->execute();
$quiz_results = $stmt->get_result();

// Group quiz results by course and quiz
$results_by_course = [];
while ($result = $quiz_results->fetch_assoc()) {
    $course_id = $result['course_id'];
    $course_title = $result['course_title'];
    $quiz_id = $result['quiz_id'];
    $quiz_title = $result['quiz_title'];
    
    if (!isset($results_by_course[$course_id])) {
        $results_by_course[$course_id] = [
            'course_title' => $course_title,
            'quizzes' => []
        ];
    }
    
    if (!isset($results_by_course[$course_id]['quizzes'][$quiz_id])) {
        $results_by_course[$course_id]['quizzes'][$quiz_id] = [
            'title' => $quiz_title,
            'results' => []
        ];
    }
    
    $percentage = ($result['total_questions'] > 0) ? 
        round(($result['score'] / $result['total_questions']) * 100) : 0;
    
    $results_by_course[$course_id]['quizzes'][$quiz_id]['results'][] = [
        'id' => $result['student_quiz_id'],
        'student_id' => $result['student_id'],
        'student_name' => $result['student_name'],
        'score' => $result['score'],
        'total_questions' => $result['total_questions'],
        'percentage' => $percentage,
        'completed_at' => $result['completed_at']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Instructor Dashboard - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Instructor Dashboard</title>
    
    <!-- core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/prettyPhoto.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: white;
        }
        #dashboard {
            padding: 50px 0;
        }
        .dashboard-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .dashboard-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            width: 80%;
            text-align: center;
            margin: 0 auto;
        }
        .nav-tabs {
            display: flex;
            justify-content: center;
            border-bottom: 2px solid #ddd;
            width: 80%;
            margin: 0 auto 20px;
        }
        .nav-tabs a {
            padding: 10px 20px;
            text-decoration: none;
            color: black;
            border: 1px solid transparent;
            margin: 5px;
            border-radius: 5px;
        }
        .nav-tabs a.active {
            background-color: #007bff;
            color: white;
        }
        table {
            width: 100%;
            text-align: left; 
        }
        th, td {
            padding: 10px; 
        }
        th {
            text-align: left; 
        }
        .btn {
            margin-top: 10px; 
        }
        @media (max-width: 768px) {
            .dashboard-box, .nav-tabs {
                width: 95%;
            }
        }
        .welcome-message {
            margin-bottom: 20px;
            font-weight: bold;
            color: #333;
        }
        .course-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .course-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
        }
        .course-content {
            padding: 15px;
            display: none;
        }
        .assignment-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .assignment-item:last-child {
            border-bottom: none;
        }
        .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .student-performance-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .student-performance-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
        }
        .student-performance-content {
            padding: 15px;
            display: none;
        }
        .assessment-card {
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 15px;
            padding: 10px;
        }
        .assessment-header {
            background-color: #f9f9f9;
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .assessment-content {
            padding: 10px;
            display: none;
        }
        .grade-a {
            color: #28a745;
            font-weight: bold;
        }
        .grade-b {
            color: #17a2b8;
            font-weight: bold;
        }
        .grade-c {
            color: #ffc107;
            font-weight: bold;
        }
        .grade-d {
            color: #fd7e14;
            font-weight: bold;
        }
        .grade-f {
            color: #dc3545;
            font-weight: bold;
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
                    <li><a href="profile.php">Profile</a></li>
                    <li><a class="btn btn-primary" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Section -->
    <section id="dashboard">
        <div class="container">
            <h2 class="text-center">Instructor Dashboard</h2>
            <p class="text-center welcome-message">Welcome, <?php echo htmlspecialchars($instructor_name); ?>!</p>
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
            <!-- Tabs Navigation -->
            <div class="nav-tabs">
                <a href="#overview" class="tab-link active" onclick="showTab(event, 'overview')">Overview</a>
                <a href="#courses" class="tab-link" onclick="showTab(event, 'courses')">Course Management</a>
                <a href="#assignments" class="tab-link" onclick="showTab(event, 'assignments')">Assignments</a>
                <a href="#quizzes" class="tab-link" onclick="showTab(event, 'quizzes')">Quizzes</a>
                <a href="#students" class="tab-link" onclick="showTab(event, 'students')">Student Performance</a>
                <a href="#reports" class="tab-link" onclick="showTab(event, 'reports')">Reports & Analytics</a>
            </div>

            <!-- Overview Section -->
            <div id="overview" class="dashboard-box tab-content">
                <h3>Overview</h3>
                <p>Here's a quick look at your courses and tasks.</p>
                <p>Courses Assigned: <strong><?php echo $course_count; ?></strong> | 
                   Assignments Due Soon: <strong><?php echo $upcoming_deadlines->num_rows; ?></strong> | 
                   Student Questions Pending Response: <strong><?php echo $question_count; ?></strong></p>
                
                <h4>Upcoming Assignment Deadlines</h4>
                <ul class="list-group">
                    <?php if ($upcoming_deadlines->num_rows > 0): ?>
                        <?php while($deadline = $upcoming_deadlines->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <?php echo htmlspecialchars($deadline['title']); ?> 
                                (<?php echo htmlspecialchars($deadline['course_title']); ?>) - 
                                Due in <?php echo ceil((strtotime($deadline['due_date']) - time()) / (60 * 60 * 24)); ?> days
                            </li>
                        <?php endwhile; ?>
                        <?php $upcoming_deadlines->data_seek(0); // Reset pointer ?>
                    <?php else: ?>
                        <li class="list-group-item">No upcoming deadlines</li>
                    <?php endif; ?>
                </ul>

                <h4>Recent Quizzes</h4>
                <ul class="list-group">
                    <?php if ($upcoming_quizzes->num_rows > 0): ?>
                        <?php while($quiz = $upcoming_quizzes->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <?php echo htmlspecialchars($quiz['quiz_title']); ?> 
                                (<?php echo htmlspecialchars($quiz['course_title']); ?>) - 
                                Created on <?php echo date('d/m/Y', strtotime($quiz['created_at'])); ?>
                            </li>
                        <?php endwhile; ?>
                        <?php $upcoming_quizzes->data_seek(0); // Reset pointer ?>
                    <?php else: ?>
                        <li class="list-group-item">No quizzes created yet</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Course Management Section -->
            <div id="courses" class="dashboard-box tab-content" style="display:none;">
                <h3>Course Management</h3>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createCourseModal">
                    Create New Course
                </button>
                <table class="table mt-3">
                    <thead>
                        <tr>
                            <th>Course Title</th>
                            <th>Category</th>
                            <th>Skill Level</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($courses->num_rows > 0): ?>
                            <?php while($course = $courses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo htmlspecialchars($course['category']); ?></td>
                                    <td><?php echo htmlspecialchars($course['skill_level']); ?></td>
                                    <td><?php echo $course['start_date'] ? htmlspecialchars(date('d/m/Y', strtotime($course['start_date']))) : 'Not set'; ?></td>
                                    <td><?php echo $course['end_date'] ? htmlspecialchars(date('d/m/Y', strtotime($course['end_date']))) : 'Not set'; ?></td>
                                    <td><?php echo $course['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>'; ?></td>
                                    <td>
                                        <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $course['course_id']; ?>)">Delete</button>
                                        <a href="#assignments" class="btn btn-info btn-sm" onclick="showTab(event, 'assignments'); highlightCourse(<?php echo $course['course_id']; ?>)">
                                            Manage Assignments
                                        </a>
                                        <a href="#quizzes" class="btn btn-info btn-sm" onclick="showTab(event, 'quizzes'); highlightCourse(<?php echo $course['course_id']; ?>)">
                                            Manage Quizzes
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php $courses->data_seek(0); // Reset pointer ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No courses found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Assignments Section -->
            <div id="assignments" class="dashboard-box tab-content" style="display:none;">
                <h3>Assignments by Course</h3>
                
                <?php if (count($assignments_by_course) > 0): ?>
                    <?php foreach ($assignments_by_course as $course_id => $course_data): ?>
                        <div class="course-card" id="course-<?php echo $course_id; ?>">
                            <div class="course-header" onclick="toggleCourseContent(<?php echo $course_id; ?>)">
                                <h4>
                                    <i class="fa fa-chevron-right" id="course-icon-<?php echo $course_id; ?>"></i>
                                    <?php echo htmlspecialchars($course_data['course_title']); ?>
                                    <span class="badge"><?php echo count($course_data['assignments']); ?> assignments</span>
                                </h4>
                            </div>
                            <div class="course-content" id="course-content-<?php echo $course_id; ?>">
                                <button type="button" class="btn btn-success btn-sm" onclick="createAssignment(<?php echo $course_id; ?>)">
                                    Add New Assignment
                                </button>
                                
                                <?php if (count($course_data['assignments']) > 0): ?>
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Topic</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($course_data['assignments'] as $assignment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['topic']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($assignment['due_date']))); ?></td>
                                                    <td><?php echo $assignment['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>'; ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm" onclick="editAssignment(<?php echo $assignment['id']; ?>)">Edit</button>
                                                        <button class="btn btn-danger btn-sm" onclick="confirmDeleteAssignment(<?php echo $assignment['id']; ?>)">Delete</button>
                                                        <a href="grade_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-info btn-sm">Grade</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-center">No assignments for this course yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">No courses available. Please create a course first.</p>
                <?php endif; ?>
            </div>

            <div id="quizzes" class="dashboard-box tab-content" style="display:none;">
    <h3>Quizzes by Course</h3>
    
    <?php if (count($quizzes_by_course) > 0): ?>
        <?php foreach ($quizzes_by_course as $course_id => $course_data): ?>
            <div class="course-card" id="quiz-course-<?php echo $course_id; ?>">
                <div class="course-header" onclick="toggleQuizCourseContent(<?php echo $course_id; ?>)">
                    <h4>
                        <i class="fa fa-chevron-right" id="quiz-course-icon-<?php echo $course_id; ?>"></i>
                        <?php echo htmlspecialchars($course_data['course_title']); ?>
                        <span class="badge"><?php echo count($course_data['quizzes']); ?> quizzes</span>
                    </h4>
                </div>
                <div class="course-content" id="quiz-course-content-<?php echo $course_id; ?>">
                    <button type="button" class="btn btn-success btn-sm" onclick="createQuiz(<?php echo $course_id; ?>)">
                        Add New Quiz
                    </button>
                    
                    <?php if (count($course_data['quizzes']) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Topic</th>
                                    <th>Created Date</th>
                                    <th>Automated Grading</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_data['quizzes'] as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['topic']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($quiz['created_at']))); ?></td>
                                        <td><?php echo $quiz['automated_grading'] ? '<span class="text-success">Enabled</span>' : '<span class="text-danger">Disabled</span>'; ?></td>
                                        <td>
                                            <?php if (isset($quiz['is_published']) && $quiz['is_published']): ?>
                                                <span class="label label-success">Published</span>
                                            <?php else: ?>
                                                <span class="label label-warning">Draft</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
    <a href="manage_questions.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm" onclick="setActiveTab('quizzes')">
        <i class="fa fa-list"></i> Manage Questions
    </a>
    
    <?php if (isset($quiz['is_published']) && $quiz['is_published'] == 1): ?>
        <a href="unpublish_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-warning btn-sm"
           onclick="return confirm('Are you sure you want to unpublish this quiz? Students will no longer be able to access it.')">
            <i class="fa fa-eye-slash"></i> Unpublish
        </a>
    <?php else: ?>
        <a href="post_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-success btn-sm">
            <i class="fa fa-paper-plane"></i> Publish
        </a>
    <?php endif; ?>
    
    <button class="btn btn-danger btn-sm" onclick="confirmDeleteQuiz(<?php echo $quiz['id']; ?>)">
        <i class="fa fa-trash"></i> Delete
    </button>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center">No quizzes for this course yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center">No courses available. Please create a course first.</p>
    <?php endif; ?>
</div>

            <!-- Student Performance Section (Modified to include quiz/assignment results) -->
            <div id="students" class="dashboard-box tab-content" style="display:none;">
                <h3>Student Performance by Course</h3>
                <input type="text" class="form-control mb-3" id="studentSearch" onkeyup="searchStudents()" placeholder="Search Students or Courses">
                
                <?php if (count($performance_by_course) > 0): ?>
                    <?php foreach ($performance_by_course as $course_id => $course_data): ?>
                        <div class="student-performance-card" id="performance-course-<?php echo $course_id; ?>">
                            <div class="student-performance-header" onclick="togglePerformanceContent(<?php echo $course_id; ?>)">
                                <h4>
                                    <i class="fa fa-chevron-right" id="performance-icon-<?php echo $course_id; ?>"></i>
                                    <?php echo htmlspecialchars($course_data['course_title']); ?>
                                    <span class="badge"><?php echo count($course_data['students']); ?> students</span>
                                </h4>
                            </div>
                            <div class="student-performance-content" id="performance-content-<?php echo $course_id; ?>">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Overall Progress</th>
                                            <th>Grade</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($course_data['students'] as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['progress']); ?>%</td>
                                                <td>
                                                    <?php 
                                                        $grade_class = '';
                                                        switch($student['grade']) {
                                                            case 'A': $grade_class = 'grade-a'; break;
                                                            case 'B': $grade_class = 'grade-b'; break;
                                                            case 'C': $grade_class = 'grade-c'; break;
                                                            case 'D': $grade_class = 'grade-d'; break;
                                                            case 'F': $grade_class = 'grade-f'; break;
                                                        }
                                                    ?>
                                                    <span class="<?php echo $grade_class; ?>"><?php echo htmlspecialchars($student['grade']); ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-info btn-sm" onclick="toggleStudentDetails(<?php echo $course_id; ?>, <?php echo $student['id']; ?>)">
                                                        View Details
                                                    </button>
                                                </td>
                                            </tr>
                                            <!-- Student Details Section - Hidden by Default -->
                                            <tr id="student-details-<?php echo $course_id; ?>-<?php echo $student['id']; ?>" style="display:none;">
                                                <td colspan="4">
                                                    <div class="student-details-content">
                                                        <h5>Performance Details for <?php echo htmlspecialchars($student['name']); ?></h5>
                                                        
                                                        <!-- Assignment Submissions -->
                                                        <div class="assessment-card">
                                                            <div class="assessment-header" onclick="toggleAssessmentContent('assignments', <?php echo $course_id; ?>, <?php echo $student['id']; ?>)">
                                                                <h6>
                                                                    <i class="fa fa-chevron-right" id="assignments-icon-<?php echo $course_id; ?>-<?php echo $student['id']; ?>"></i>
                                                                    Assignment Submissions
                                                                </h6>
                                                            </div>
                                                            <div class="assessment-content" id="assignments-content-<?php echo $course_id; ?>-<?php echo $student['id']; ?>">
                                                                <?php if (isset($submissions_by_course[$course_id])): ?>
                                                                    <table class="table table-striped">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Assignment</th>
                                                                                <th>Submission Date</th>
                                                                                <th>Grade</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php 
                                                                            $has_submissions = false;
                                                                            foreach ($submissions_by_course[$course_id]['assignments'] as $assignment_id => $assignment_data): 
                                                                                foreach ($assignment_data['submissions'] as $submission):
                                                                                    if ($submission['student_id'] == $student['id']):
                                                                                        $has_submissions = true;
                                                                            ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($assignment_data['title']); ?></td>
                                                                                    <td><?php echo date('d/m/Y', strtotime($submission['date_submitted'])); ?></td>
                                                                                    <td>
                                                                                        <?php if ($submission['grade'] !== null): ?>
                                                                                            <?php 
                                                                                                $grade_class = '';
                                                                                                if ($submission['grade'] >= 90) $grade_class = 'grade-a';
                                                                                                else if ($submission['grade'] >= 80) $grade_class = 'grade-b';
                                                                                                else if ($submission['grade'] >= 70) $grade_class = 'grade-c';
                                                                                                else if ($submission['grade'] >= 60) $grade_class = 'grade-d';
                                                                                                else $grade_class = 'grade-f';
                                                                                            ?>
                                                                                            <span class="<?php echo $grade_class; ?>"><?php echo $submission['grade']; ?>%</span>
                                                                                        <?php else: ?>
                                                                                            <span class="text-warning">Not Graded</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                                                                        <?php if ($submission['grade'] === null): ?>
                                                                                            <a href="grade_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-success">Grade</a>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php 
                                                                                    endif;
                                                                                endforeach;
                                                                            endforeach; 
                                                                            
                                                                            if (!$has_submissions):
                                                                            ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="text-center">No assignment submissions found for this student.</td>
                                                                                </tr>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php else: ?>
                                                                    <p class="text-center">No assignments are available for this course.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Quiz Results -->
                                                        <div class="assessment-card">
                                                            <div class="assessment-header" onclick="toggleAssessmentContent('quizzes', <?php echo $course_id; ?>, <?php echo $student['id']; ?>)">
                                                                <h6>
                                                                    <i class="fa fa-chevron-right" id="quizzes-icon-<?php echo $course_id; ?>-<?php echo $student['id']; ?>"></i>
                                                                    Quiz Results
                                                                </h6>
                                                            </div>
                                                            <div class="assessment-content" id="quizzes-content-<?php echo $course_id; ?>-<?php echo $student['id']; ?>">
                                                                <?php if (isset($results_by_course[$course_id])): ?>
                                                                    <table class="table table-striped">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Quiz</th>
                                                                                <th>Completion Date</th>
                                                                                <th>Score</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php 
                                                                            $has_results = false;
                                                                            foreach ($results_by_course[$course_id]['quizzes'] as $quiz_id => $quiz_data): 
                                                                                foreach ($quiz_data['results'] as $result):
                                                                                    if ($result['student_id'] == $student['id']):
                                                                                        $has_results = true;
                                                                            ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($quiz_data['title']); ?></td>
                                                                                    <td><?php echo date('d/m/Y', strtotime($result['completed_at'])); ?></td>
                                                                                    <td>
                                                                                        <?php 
                                                                                            $grade_class = '';
                                                                                            if ($result['percentage'] >= 90) $grade_class = 'grade-a';
                                                                                            else if ($result['percentage'] >= 80) $grade_class = 'grade-b';
                                                                                            else if ($result['percentage'] >= 70) $grade_class = 'grade-c';
                                                                                            else if ($result['percentage'] >= 60) $grade_class = 'grade-d';
                                                                                            else $grade_class = 'grade-f';
                                                                                        ?>
                                                                                        <span class="<?php echo $grade_class; ?>">
                                                                                            <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?> 
                                                                                            (<?php echo $result['percentage']; ?>%)
                                                                                        </span>
                                                                                    </td>
                                                                                    <td>
                                                                                        <a href="view_quiz_result.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php 
                                                                                    endif;
                                                                                endforeach;
                                                                            endforeach; 
                                                                            
                                                                            if (!$has_results):
                                                                            ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="text-center">No quiz results found for this student.</td>
                                                                                </tr>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php else: ?>
                                                                    <p class="text-center">No quizzes are available for this course.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">No student performance data available yet.</p>
                <?php endif; ?>
            </div>

            <!-- Reports and Analytics Section -->
            <div id="reports" class="dashboard-box tab-content" style="display:none;">
                <h3>Reports and Analytics</h3>
                <canvas id="performanceChart"></canvas>
                <button class="btn btn-info mt-3" onclick="downloadReport()">Download Report</button>
            </div>
        </div>
    </section>
    
    <!-- Create Course Modal -->
    <div class="modal fade" id="createCourseModal" tabindex="-1" role="dialog" aria-labelledby="createCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="createCourseModalLabel">Create New Course</h4>
                </div>
                <div class="modal-body">
                    <form id="courseForm" action="create_course.php" method="post">
                        <div class="form-group">
                            <label for="title">Course Title:</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <input type="text" class="form-control" id="category" name="category">
                        </div>
                        
                        <div class="form-group">
                            <label for="skill_level">Skill Level:</label>
                            <select class="form-control" id="skill_level" name="skill_level" required>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="prereq_course_id">Prerequisite Course (if any):</label>
                            <select class="form-control" id="prereq_course_id" name="prereq_course_id">
                                <option value="">None</option>
                                <?php 
                                $courses->data_seek(0);
                                while($course = $courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Start Date:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">End Date:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="is_active">Status:</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitCourseForm()">Create Course</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Assignment Modal -->
    <div class="modal fade" id="assignmentModal" tabindex="-1" role="dialog" aria-labelledby="assignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="assignmentModalLabel">Create New Assignment</h4>
                </div>
                <div class="modal-body">
                    <form id="assignmentForm" action="create_assignment.php" method="post">
                        <input type="hidden" id="course_id" name="course_id">
                        
                        <div class="form-group">
                            <label for="assignment_title">Assignment Title:</label>
                            <input type="text" class="form-control" id="assignment_title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="assignment_description">Description:</label>
                            <textarea class="form-control" id="assignment_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="topic">Topic:</label>
                            <input type="text" class="form-control" id="topic" name="topic">
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date:</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grading_criteria">Grading Criteria:</label>
                            <textarea class="form-control" id="grading_criteria" name="grading_criteria" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="assignment_is_active">Status:</label>
                            <select class="form-control" id="assignment_is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAssignmentForm()">Create Assignment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal fade" id="quizModal" tabindex="-1" role="dialog" aria-labelledby="quizModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="quizModalLabel">Create New Quiz</h4>
                </div>
                <div class="modal-body">
                    <form id="quizForm" action="create_quiz.php" method="post">
                        <input type="hidden" id="quiz_course_id" name="course_id">
                        
                        <div class="form-group">
                            <label for="quiz_title">Quiz Title:</label>
                            <input type="text" class="form-control" id="quiz_title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quiz_topic">Topic:</label>
                            <input type="text" class="form-control" id="quiz_topic" name="topic" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="is_automated_grading">Automated Grading:</label>
                            <select class="form-control" id="is_automated_grading" name="is_automated_grading">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitQuizForm()">Create Quiz</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/chart.min.js"></script>
    <script>
        // Function to set which tab should be active
        function setActiveTab(tabName) {
            localStorage.setItem('activeTab', tabName);
        }
        
        function showTab(event, tabId) {
            event.preventDefault();
            let tabs = document.querySelectorAll('.tab-content');
            let links = document.querySelectorAll('.tab-link');
            tabs.forEach(tab => tab.style.display = 'none');
            links.forEach(link => link.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            
            // Find the correct link and add the active class
            const activeLink = document.querySelector(`.tab-link[href="#${tabId}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            } else {
                event.target.classList.add('active');
            }
            
            // Store the active tab in localStorage
            localStorage.setItem('activeTab', tabId);
        }

        function toggleCourseContent(courseId) {
            const content = document.getElementById('course-content-' + courseId);
            const icon = document.getElementById('course-icon-' + courseId);
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fa fa-chevron-right';
            } else {
                content.style.display = 'block';
                icon.className = 'fa fa-chevron-down';
            }
        }
        
        function toggleQuizCourseContent(courseId) {
            const content = document.getElementById('quiz-course-content-' + courseId);
            const icon = document.getElementById('quiz-course-icon-' + courseId);
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fa fa-chevron-right';
            } else {
                content.style.display = 'block';
                icon.className = 'fa fa-chevron-down';
            }
        }
        
        function togglePerformanceContent(courseId) {
            const content = document.getElementById('performance-content-' + courseId);
            const icon = document.getElementById('performance-icon-' + courseId);
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fa fa-chevron-right';
            } else {
                content.style.display = 'block';
                icon.className = 'fa fa-chevron-down';
            }
        }
        
        function toggleStudentDetails(courseId, studentId) {
            const details = document.getElementById('student-details-' + courseId + '-' + studentId);
            
            if (details.style.display === 'table-row') {
                details.style.display = 'none';
            } else {
                // Hide all other details first
                document.querySelectorAll('[id^="student-details-"]').forEach(el => {
                    el.style.display = 'none';
                });
                details.style.display = 'table-row';
            }
        }
        
        function toggleAssessmentContent(type, courseId, studentId) {
            const content = document.getElementById(type + '-content-' + courseId + '-' + studentId);
            const icon = document.getElementById(type + '-icon-' + courseId + '-' + studentId);
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fa fa-chevron-right';
            } else {
                content.style.display = 'block';
                icon.className = 'fa fa-chevron-down';
            }
        }
        
        function highlightCourse(courseId) {
            // Check which tab we're in and highlight accordingly
            if (document.getElementById('assignments').style.display === 'block') {
                // We're in the assignments tab
                // First close all courses
                document.querySelectorAll('[id^="course-content-"]').forEach(content => {
                    content.style.display = 'none';
                });
                document.querySelectorAll('[id^="course-icon-"]').forEach(icon => {
                    icon.className = 'fa fa-chevron-right';
                });
                
                // Then open the selected course
                const content = document.getElementById('course-content-' + courseId);
                const icon = document.getElementById('course-icon-' + courseId);
                const courseCard = document.getElementById('course-' + courseId);
                
                if (content && icon && courseCard) {
                    content.style.display = 'block';
                    icon.className = 'fa fa-chevron-down';
                    
                    // Scroll to the course card
                    setTimeout(() => {
                        courseCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            } else if (document.getElementById('quizzes').style.display === 'block') {
                // We're in the quizzes tab
                // First close all courses
                document.querySelectorAll('[id^="quiz-course-content-"]').forEach(content => {
                    content.style.display = 'none';
                });
                document.querySelectorAll('[id^="quiz-course-icon-"]').forEach(icon => {
                    icon.className = 'fa fa-chevron-right';
                });
                
                // Then open the selected course
                const content = document.getElementById('quiz-course-content-' + courseId);
                const icon = document.getElementById('quiz-course-icon-' + courseId);
                const courseCard = document.getElementById('quiz-course-' + courseId);
                
                if (content && icon && courseCard) {
                    content.style.display = 'block';
                    icon.className = 'fa fa-chevron-down';
                    
                    // Scroll to the course card
                    setTimeout(() => {
                        courseCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            }
        }

        function confirmDelete(courseId) {
            if (confirm("Are you sure you want to delete this course? This action cannot be undone.")) {
                window.location.href = "delete_course.php?id=" + courseId;
            }
        }

        function confirmDeleteAssignment(assignmentId) {
            if (confirm("Are you sure you want to delete this assignment? This action cannot be undone.")) {
                window.location.href = "delete_assignment.php?id=" + assignmentId;
            }
        }
        
        function confirmDeleteQuiz(quizId) {
            if (confirm("Are you sure you want to delete this quiz? This action cannot be undone.")) {
                window.location.href = "delete_quiz.php?id=" + quizId;
            }
        }
        
        function editCourse(courseId) {
            window.location.href = "edit_course.php?id=" + courseId;
        }
        
        function editAssignment(assignmentId) {
            // This would load assignment data and open the modal
            alert("Edit assignment " + assignmentId + " (would open modal with assignment data)");
        }
        
        function editQuiz(quizId) {
            // This would load quiz data and open the modal
            alert("Edit quiz " + quizId + " (would open modal with quiz data)");
        }
        
        function createQuiz(courseId) {
            // Set the course ID in the modal form
            document.getElementById('quiz_course_id').value = courseId;
            
            // Reset the form fields
            document.getElementById('quizForm').reset();
            
            // Update modal title
            document.getElementById('quizModalLabel').innerText = 'Create New Quiz';
            
            // Show the modal
            $('#quizModal').modal('show');
        }

        function createAssignment(courseId) {
            // Set the course ID in the modal form
            document.getElementById('course_id').value = courseId;
            
            // Reset the form fields
            document.getElementById('assignmentForm').reset();
            
            // Update modal title
            document.getElementById('assignmentModalLabel').innerText = 'Create New Assignment';
            
            // Show the modal
            $('#assignmentModal').modal('show');
        }
        
        function submitCourseForm() {
            document.getElementById('courseForm').submit();
        }
        
        function submitAssignmentForm() {
            document.getElementById('assignmentForm').submit();
        }
        
        function submitQuizForm() {
            document.getElementById('quizForm').submit();
        }
       
        function searchStudents() {
            let input = document.getElementById("studentSearch");
            let filter = input.value.toUpperCase();
            let courseCards = document.querySelectorAll('.student-performance-card');

            courseCards.forEach(card => {
                let courseName = card.querySelector('.student-performance-header h4').textContent.trim();
                let rows = card.querySelectorAll('tbody tr');
                let studentMatched = false;
                
                // Check for course name match first
                if (courseName.toUpperCase().indexOf(filter) > -1) {
                    card.style.display = "";
                    // Make all student rows visible in this matching course
                    rows.forEach(row => {
                        if (!row.id.startsWith('student-details-')) {
                            row.style.display = "";
                        }
                    });
                    return;
                }
                
                // If course name doesn't match, check individual students
                rows.forEach(row => {
                    if (row.id.startsWith('student-details-')) {
                        // Skip the detail rows
                        return;
                    }
                    
                    let studentName = row.querySelector('td:first-child').textContent;
                    if (studentName.toUpperCase().indexOf(filter) > -1) {
                        row.style.display = "";
                        studentMatched = true;
                    } else {
                        row.style.display = "none";
                    }
                });
                
                // Show/hide the course card based on student matches
                card.style.display = studentMatched ? "" : "none";
            });
        }

        function downloadReport() {
            alert("Report download functionality will be implemented here.");
        }

        // Sample chart for Reports section
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('performanceChart').getContext('2d');
            
            // Get course data for chart
            let courses = [];
            let averages = [];
            
            <?php 
            if ($courses->num_rows > 0) {
                $courses->data_seek(0); // Reset pointer
                while($course = $courses->fetch_assoc()): 
            ?>
                courses.push("<?php echo addslashes($course['title']); ?>");
                // This would normally be calculated from actual student performance
                averages.push(Math.floor(Math.random() * 30) + 70); // Random between 70-100 for demo
            <?php 
                endwhile;
            } else {
            ?>
                courses = ['No Courses'];
                averages = [0];
            <?php
            }
            ?>
            
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: courses,
                    datasets: [{
                        label: 'Average Student Performance',
                        data: averages,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)'
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
            
            // When the page loads, check if there's a stored active tab or URL hash
            const hash = window.location.hash.substring(1);
            const storedTab = localStorage.getItem('activeTab');
            
            // Priority: URL hash > stored tab > default (overview)
            const tabToShow = hash || storedTab || 'overview';
            
            // Find the tab link and trigger a click to show the tab
            const tabLink = document.querySelector('.tab-link[href="#' + tabToShow + '"]');
            if (tabLink) {
                // Create a synthetic event
                const event = {
                    preventDefault: function() {},
                    target: tabLink
                };
                
                // Call the showTab function
                showTab(event, tabToShow);
            }
        });
    </script>
</body>
</html>
