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
$student_name = $_SESSION['name'];

// Fetch enrolled courses
$stmt = $conn->prepare("
    SELECT c.course_id, c.title, e.progress_percentage 
    FROM Enrollments e 
    JOIN Courses c ON e.course_id = c.course_id 
    WHERE e.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result();

// Calculate overall progress
$total_progress = 0;
$course_count = 0;
if ($enrolled_courses->num_rows > 0) {
    while ($course = $enrolled_courses->fetch_assoc()) {
        $total_progress += $course['progress_percentage'];
        $course_count++;
    }
    $overall_progress = $course_count > 0 ? round($total_progress / $course_count) : 0;
    // Reset result pointer to beginning
    $enrolled_courses->data_seek(0);
} else {
    $overall_progress = 0;
}

// Fetch upcoming assignments
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, a.due_date, c.title as course_title 
    FROM Assignments a
    JOIN Courses c ON a.course_id = c.course_id
    JOIN Enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$upcoming_assignments = $stmt->get_result();

// Fetch submitted assignments
$stmt = $conn->prepare("
    SELECT s.submission_id, a.title as assignment_title, s.date_submitted, s.grade, c.title as course_title, c.course_id
    FROM Submissions s
    JOIN Assignments a ON s.assignment_id = a.assignment_id
    JOIN Courses c ON a.course_id = c.course_id
    WHERE s.student_id = ?
    ORDER BY s.date_submitted DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$submitted_assignments = $stmt->get_result();

// Fetch available quizzes (published only)
$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, c.title as course_title, q.created_at
    FROM Quizzes q
    JOIN Courses c ON q.course_id = c.course_id
    JOIN Enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ? AND q.is_published = 1
    ORDER BY q.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$available_quizzes = $stmt->get_result();

// Fetch completed quizzes
$stmt = $conn->prepare("
    SELECT sq.student_quiz_id, sq.quiz_id, q.title, sq.score, sq.total_questions, 
           sq.completed_at, c.title as course_title, c.course_id
    FROM StudentQuizzes sq
    JOIN Quizzes q ON sq.quiz_id = q.quiz_id
    JOIN Courses c ON q.course_id = c.course_id
    WHERE sq.student_id = ? AND sq.is_completed = 1
    ORDER BY sq.completed_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$completed_quizzes = $stmt->get_result();

// Create an array of completed quiz IDs for easy lookup
$completed_quiz_ids = [];
while ($completed = $completed_quizzes->fetch_assoc()) {
    $completed_quiz_ids[$completed['quiz_id']] = $completed;
}
// Reset the result pointer
$completed_quizzes->data_seek(0);

// Organize grades by course
$grades_by_course = [];

// Add assignment grades to the course array
while ($assignment = $submitted_assignments->fetch_assoc()) {
    $course_id = $assignment['course_id'];
    $course_title = $assignment['course_title'];
    
    if (!isset($grades_by_course[$course_id])) {
        $grades_by_course[$course_id] = [
            'title' => $course_title,
            'assignments' => [],
            'quizzes' => []
        ];
    }
    
    $grades_by_course[$course_id]['assignments'][] = [
        'title' => $assignment['assignment_title'],
        'date' => $assignment['date_submitted'],
        'grade' => $assignment['grade']
    ];
}

// Reset pointer
$submitted_assignments->data_seek(0);

// Add quiz grades to the course array
while ($quiz = $completed_quizzes->fetch_assoc()) {
    $course_id = $quiz['course_id'];
    $course_title = $quiz['course_title'];
    
    if (!isset($grades_by_course[$course_id])) {
        $grades_by_course[$course_id] = [
            'title' => $course_title,
            'assignments' => [],
            'quizzes' => []
        ];
    }
    
    $percentage = ($quiz['total_questions'] > 0) ? 
        round(($quiz['score'] / $quiz['total_questions']) * 100) : 0;
    
    $grades_by_course[$course_id]['quizzes'][] = [
        'title' => $quiz['title'],
        'date' => $quiz['completed_at'],
        'score' => $quiz['score'],
        'total' => $quiz['total_questions'],
        'percentage' => $percentage
    ];
}

// Reset pointer
$completed_quizzes->data_seek(0);

// Check for success message
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Dashboard - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Student Dashboard</title>
    
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
        .progress {
            height: 20px;
            margin-bottom: 20px;
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
        .badge-success {
            background-color: #28a745;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .ml-2 {
            margin-left: 0.5rem;
        }
        .alert {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .btn-completed {
            background-color: #6c757d;
            color: white;
            cursor: default;
        }
        .btn-completed:hover {
            background-color: #6c757d;
            color: white;
        }
        .course-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .course-header {
            background-color: #f5f5f5;
            padding: 15px;
            cursor: pointer;
            border-bottom: 1px solid #ddd;
        }
        .course-content {
            padding: 15px;
            display: none;
        }
        .grade-table {
            margin-bottom: 15px;
            width: 100%;
        }
        .grade-table th {
            background-color: #f8f9fa;
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
        .no-grades {
            text-align: center;
            padding: 10px;
            font-style: italic;
            color: #6c757d;
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

    <section id="dashboard">
        <div class="container">
            <h2 class="text-center">Student Dashboard</h2>
            <p class="text-center welcome-message">Welcome, <?php echo htmlspecialchars($student_name); ?>!</p>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="nav-tabs">
                <a href="#overview" class="tab-link active" onclick="showTab(event, 'overview')">Overview</a>
                <a href="#courses" class="tab-link" onclick="showTab(event, 'courses')">Course Access</a>
                <a href="#assignments" class="tab-link" onclick="showTab(event, 'assignments')">Assignments</a>
                <a href="#quizzes" class="tab-link" onclick="showTab(event, 'quizzes')">Quizzes</a>
                <a href="#progress" class="tab-link" onclick="showTab(event, 'progress')">Progress Tracking</a>
            </div>

            <div id="overview" class="dashboard-box tab-content">
                <h3>Overview</h3>
                <h4>Enrolled Courses</h4>
                <ul class="list-group">
                    <?php if ($enrolled_courses->num_rows > 0): ?>
                        <?php while($course = $enrolled_courses->fetch_assoc()): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($course['title']); ?></li>
                        <?php endwhile; ?>
                        <?php $enrolled_courses->data_seek(0); // Reset the result pointer ?>
                    <?php else: ?>
                        <li class="list-group-item">No courses enrolled</li>
                    <?php endif; ?>
                </ul>
                
                <h4>Upcoming Assignments</h4>
                <ul class="list-group">
                    <?php if ($upcoming_assignments->num_rows > 0): ?>
                        <?php while($assignment = $upcoming_assignments->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <?php echo htmlspecialchars($assignment['title']); ?> 
                                (<?php echo htmlspecialchars($assignment['course_title']); ?>) - 
                                Due in <?php echo ceil((strtotime($assignment['due_date']) - time()) / (60 * 60 * 24)); ?> days
                            </li>
                        <?php endwhile; ?>
                        <?php $upcoming_assignments->data_seek(0); // Reset the result pointer ?>
                    <?php else: ?>
                        <li class="list-group-item">No upcoming assignments</li>
                    <?php endif; ?>
                </ul>
                
                <h4>Available Quizzes</h4>
                <ul class="list-group">
                    <?php if ($available_quizzes->num_rows > 0): ?>
                        <?php while($quiz = $available_quizzes->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <?php echo htmlspecialchars($quiz['title']); ?> 
                                (<?php echo htmlspecialchars($quiz['course_title']); ?>) - 
                                Created on <?php echo date('d/m/Y', strtotime($quiz['created_at'])); ?>
                                <?php if (isset($completed_quiz_ids[$quiz['quiz_id']])): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php endif; ?>
                            </li>
                        <?php endwhile; ?>
                        <?php $available_quizzes->data_seek(0); // Reset the result pointer ?>
                    <?php else: ?>
                        <li class="list-group-item">No quizzes available</li>
                    <?php endif; ?>
                </ul>
                
                <h4>Overall Progress</h4>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $overall_progress; ?>%;" 
                         aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo $overall_progress; ?>%
                    </div>
                </div>
            </div>

            <div id="courses" class="dashboard-box tab-content" style="display:none;">
                <h3>Course Access</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Progress</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($enrolled_courses->num_rows > 0): ?>
                            <?php while($course = $enrolled_courses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo htmlspecialchars($course['progress_percentage']); ?>% completed</td>
                                    <td><a href="course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary">Access Course</a></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php $enrolled_courses->data_seek(0); // Reset the result pointer ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No courses enrolled</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="assignments" class="dashboard-box tab-content" style="display:none;">
                <h3>Assignments</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($upcoming_assignments->num_rows > 0): ?>
                            <?php while($assignment = $upcoming_assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                    <td>Not Submitted</td>
                                    <td>
                                        <a href="submit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-primary">Submit Assignment</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        
                        <?php if ($submitted_assignments->num_rows > 0): ?>
                            <?php while($submission = $submitted_assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['course_title']); ?></td>
                                    <td>
                                        <?php if ($submission['grade'] !== null): ?>
                                            Graded - <?php echo $submission['grade']; ?>%
                                        <?php else: ?>
                                            Submitted - Pending Grade
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['grade'] !== null): ?>
                                            <a href="view_feedback.php?id=<?php echo $submission['submission_id']; ?>" class="btn btn-info">View Feedback</a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>Awaiting Feedback</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        
                        <?php if ($upcoming_assignments->num_rows == 0 && $submitted_assignments->num_rows == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center">No assignments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="quizzes" class="dashboard-box tab-content" style="display:none;">
                <h3>Quizzes</h3>
                
                <h4>Available Quizzes</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Course</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($available_quizzes->num_rows > 0): ?>
                            <?php while($quiz = $available_quizzes->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['course_title']); ?></td>
                                    <td>
                                        <?php if (isset($completed_quiz_ids[$quiz['quiz_id']])): ?>
                                            <button class="btn btn-completed" disabled>Completed</button>
                                        <?php else: ?>
                                            <a href="attempt_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-primary">Attempt Quiz</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No available quizzes</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <h4>Completed Quizzes</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Course</th>
                            <th>Score</th>
                            <th>Completed On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($completed_quizzes->num_rows > 0): ?>
                            <?php while($quiz = $completed_quizzes->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['course_title']); ?></td>
                                    <td>
                                        <?php 
                                            $percentage = ($quiz['total_questions'] > 0) ? 
                                                round(($quiz['score'] / $quiz['total_questions']) * 100) : 0;
                                            echo $quiz['score'] . '/' . $quiz['total_questions'] . ' (' . $percentage . '%)';
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($quiz['completed_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No completed quizzes</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="progress" class="dashboard-box tab-content" style="display:none;">
                <h3>Progress Tracking</h3>
                
                <h4>Course Progress Overview</h4>
                <?php if ($enrolled_courses->num_rows > 0): ?>
                    <?php while($course = $enrolled_courses->fetch_assoc()): ?>
                        <h5><?php echo htmlspecialchars($course['title']); ?></h5>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" 
                                style="width: <?php echo $course['progress_percentage']; ?>%;" 
                                aria-valuenow="<?php echo $course['progress_percentage']; ?>" 
                                aria-valuemin="0" aria-valuemax="100">
                                <?php echo $course['progress_percentage']; ?>%
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No courses to track progress</p>
                <?php endif; ?>
                
                <h4 class="mt-4">Course Details and Grades</h4>
                
                <?php if (!empty($grades_by_course)): ?>
                    <?php foreach ($grades_by_course as $course_id => $course_data): ?>
                        <div class="course-card">
                            <div class="course-header" onclick="toggleCourseGrades(<?php echo $course_id; ?>)">
                                <h5>
                                    <i class="fa fa-chevron-right" id="course-grades-icon-<?php echo $course_id; ?>"></i>
                                    <?php echo htmlspecialchars($course_data['title']); ?>
                                </h5>
                            </div>
                            <div class="course-content" id="course-grades-content-<?php echo $course_id; ?>">
                                <?php if (!empty($course_data['assignments'])): ?>
                                    <h6>Assignments</h6>
                                    <table class="grade-table table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Assignment</th>
                                                <th>Submission Date</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($course_data['assignments'] as $assignment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($assignment['date'])); ?></td>
                                                    <td>
                                                        <?php if ($assignment['grade'] !== null): ?>
                                                            <?php 
                                                                $grade_class = '';
                                                                $grade_letter = '';
                                                                
                                                                if ($assignment['grade'] >= 90) {
                                                                    $grade_class = 'grade-a';
                                                                    $grade_letter = 'A';
                                                                } elseif ($assignment['grade'] >= 80) {
                                                                    $grade_class = 'grade-b';
                                                                    $grade_letter = 'B';
                                                                } elseif ($assignment['grade'] >= 70) {
                                                                    $grade_class = 'grade-c';
                                                                    $grade_letter = 'C';
                                                                } elseif ($assignment['grade'] >= 60) {
                                                                    $grade_class = 'grade-d';
                                                                    $grade_letter = 'D';
                                                                } else {
                                                                    $grade_class = 'grade-f';
                                                                    $grade_letter = 'F';
                                                                }
                                                            ?>
                                                            <span class="<?php echo $grade_class; ?>">
                                                                <?php echo $assignment['grade']; ?>% (<?php echo $grade_letter; ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            Pending
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-grades">No completed assignments for this course</p>
                                <?php endif; ?>
                                
                                <?php if (!empty($course_data['quizzes'])): ?>
                                    <h6>Quizzes</h6>
                                    <table class="grade-table table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Quiz</th>
                                                <th>Completion Date</th>
                                                <th>Score</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($course_data['quizzes'] as $quiz): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($quiz['date'])); ?></td>
                                                    <td><?php echo $quiz['score']; ?>/<?php echo $quiz['total']; ?></td>
                                                    <td>
                                                        <?php 
                                                            $grade_class = '';
                                                            $grade_letter = '';
                                                            
                                                            if ($quiz['percentage'] >= 90) {
                                                                $grade_class = 'grade-a';
                                                                $grade_letter = 'A';
                                                            } elseif ($quiz['percentage'] >= 80) {
                                                                $grade_class = 'grade-b';
                                                                $grade_letter = 'B';
                                                            } elseif ($quiz['percentage'] >= 70) {
                                                                $grade_class = 'grade-c';
                                                                $grade_letter = 'C';
                                                            } elseif ($quiz['percentage'] >= 60) {
                                                                $grade_class = 'grade-d';
                                                                $grade_letter = 'D';
                                                            } else {
                                                                $grade_class = 'grade-f';
                                                                $grade_letter = 'F';
                                                            }
                                                        ?>
                                                        <span class="<?php echo $grade_class; ?>">
                                                            <?php echo $quiz['percentage']; ?>% (<?php echo $grade_letter; ?>)
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-grades">No completed quizzes for this course</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">No grades available yet. Complete assignments and quizzes to see your grades here.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
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
        
        function toggleCourseGrades(courseId) {
            const content = document.getElementById('course-grades-content-' + courseId);
            const icon = document.getElementById('course-grades-icon-' + courseId);
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fa fa-chevron-right';
            } else {
                content.style.display = 'block';
                icon.className = 'fa fa-chevron-down';
            }
        }
        
        // When the page loads, check if there's a stored active tab or URL hash
        document.addEventListener('DOMContentLoaded', function() {
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
