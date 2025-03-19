<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_role = $logged_in ? $_SESSION['role'] : '';
$user_id = $logged_in ? $_SESSION['user_id'] : 0;

// Check if course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: courses.php");
    exit();
}

$course_id = $_GET['id'];

// Get course details
$stmt = $conn->prepare("
    SELECT c.course_id, c.title, c.description, c.category, c.skill_level, 
           c.start_date, c.end_date, c.is_active
    FROM Courses c
    WHERE c.course_id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: courses.php");
    exit();
}

$course = $result->fetch_assoc();

// Check if student is enrolled in this course
$is_enrolled = false;
$enrollment = null;

if ($logged_in && $user_role == 'Student') {
    $stmt = $conn->prepare("
        SELECT enrollment_id, enrollment_date, progress_percentage, status
        FROM Enrollments 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $is_enrolled = true;
        $enrollment = $result->fetch_assoc();
    }
}

// Get course assignments
$stmt = $conn->prepare("
    SELECT assignment_id, title, description, due_date, topic
    FROM Assignments
    WHERE course_id = ? AND is_active = 1
    ORDER BY due_date
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$assignments = $stmt->get_result();

// Display messages
$success_message = '';
$error_message = '';
$info_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['info_message'])) {
    $info_message = $_SESSION['info_message'];
    unset($_SESSION['info_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Course Details - Learning Management System">
    <meta name="author" content="LMS Team">
    <title><?php echo htmlspecialchars($course['title']); ?> | LMS</title>
    
    <!-- Core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/prettyPhoto.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .course-header {
            background-color: #f8f9fa;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .course-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .course-meta {
            margin-bottom: 20px;
        }
        .course-meta span {
            margin-right: 20px;
            color: #6c757d;
        }
        .course-meta i {
            margin-right: 5px;
            color: #007bff;
        }
        .course-description {
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .enrollment-status {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .progress {
            height: 25px;
            margin-top: 10px;
        }
        .progress-bar {
            line-height: 25px;
            font-size: 14px;
        }
        .assignment-list {
            margin-top: 40px;
        }
        .assignment-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .assignment-date {
            color: #dc3545;
            font-weight: bold;
        }
        .assignment-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            margin-bottom: 40px;
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
                <a class="navbar-brand" href="home.php"><img src="images/logo.png" alt="LMS Logo" width="200" height="74"></a>
            </div>
            <div class="collapse navbar-collapse navbar-right">
                <ul class="nav navbar-nav">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    
                    <?php if($logged_in): ?>
                        <?php if($user_role == 'Admin'): ?>
                            <li><a href="admin-dashboard.php">Dashboard</a></li>
                        <?php elseif($user_role == 'Instructor'): ?>
                            <li><a href="instructor-dashboard.php">Dashboard</a></li>
                        <?php elseif($user_role == 'Student'): ?>
                            <li><a href="student-dashboard.php">Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a class="btn btn-primary" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a class="btn btn-primary" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Course Header -->
    <section class="course-header">
        <div class="container">
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
            
            <?php if(!empty($info_message)): ?>
                <div class="alert alert-info">
                    <?php echo $info_message; ?>
                </div>
            <?php endif; ?>
            
            <h1 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h1>
            
            <div class="course-meta">
                <span><i class="fa fa-folder"></i> <?php echo htmlspecialchars($course['category']); ?></span>
                <span><i class="fa fa-signal"></i> <?php echo htmlspecialchars($course['skill_level']); ?></span>
                <?php if($course['start_date']): ?>
                    <span><i class="fa fa-calendar"></i> Starts: <?php echo date('M d, Y', strtotime($course['start_date'])); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if($is_enrolled): ?>
                <div class="enrollment-status">
                    <h4>You are enrolled in this course</h4>
                    <p>Enrollment Date: <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></p>
                    <p>Status: <?php echo $enrollment['status']; ?></p>
                    <p>Your Progress:</p>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $enrollment['progress_percentage']; ?>%;" 
                             aria-valuenow="<?php echo $enrollment['progress_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $enrollment['progress_percentage']; ?>%
                        </div>
                    </div>
                </div>
            <?php elseif($logged_in && $user_role == 'Student'): ?>
                <a href="enroll.php?id=<?php echo $course_id; ?>" class="btn btn-primary btn-lg">Enroll in This Course</a>
            <?php elseif(!$logged_in): ?>
                <a href="login.php" class="btn btn-primary btn-lg">Login to Enroll</a>
            <?php endif; ?>
        </div>
    </section>
    
    <section>
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <!-- Course Tabs -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#overview" data-toggle="tab">Overview</a></li>
                        <li><a href="#assignments" data-toggle="tab">Assignments</a></li>
                        <?php if($is_enrolled): ?>
                            <li><a href="#materials" data-toggle="tab">Course Materials</a></li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane active" id="overview">
                            <h3>Course Description</h3>
                            <div class="course-description">
                                <?php if(!empty($course['description'])): ?>
                                    <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                                <?php else: ?>
                                    <p>No description available for this course.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="tab-pane" id="assignments">
                            <h3>Course Assignments</h3>
                            <?php if($assignments->num_rows > 0): ?>
                                <div class="assignment-list">
                                    <?php while($assignment = $assignments->fetch_assoc()): ?>
                                        <div class="assignment-item">
                                            <span class="assignment-date">Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                                            <h4 class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($assignment['description']); ?></p>
                                            <p><strong>Topic:</strong> <?php echo htmlspecialchars($assignment['topic']); ?></p>
                                            <?php if($is_enrolled): ?>
                                                <a href="submit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-primary btn-sm">Submit Assignment</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p>No assignments have been added to this course yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($is_enrolled): ?>
                            <div class="tab-pane" id="materials">
                                <h3>Course Materials</h3>
                                <p>Course materials will be available here once they are added by the instructor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Sidebar -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Course Information</h3>
                        </div>
                        <div class="panel-body">
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($course['category']); ?></p>
                            <p><strong>Skill Level:</strong> <?php echo htmlspecialchars($course['skill_level']); ?></p>
                            <?php if($course['start_date']): ?>
                                <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($course['start_date'])); ?></p>
                            <?php endif; ?>
                            <?php if($course['end_date']): ?>
                                <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($course['end_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if($is_enrolled): ?>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">Quick Links</h3>
                            </div>
                            <ul class="list-group">
                                <li class="list-group-item"><a href="#assignments" data-toggle="tab">View Assignments</a></li>
                                <li class="list-group-item"><a href="#materials" data-toggle="tab">Access Course Materials</a></li>
                                <li class="list-group-item"><a href="student-dashboard.php">Go to Dashboard</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white text-center py-3">
        <p>&copy; 2025 Online Learning Management System | All Rights Reserved</p>
    </footer>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
