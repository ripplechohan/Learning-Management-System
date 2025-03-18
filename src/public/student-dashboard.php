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
    SELECT s.submission_id, a.title as assignment_title, s.date_submitted, s.grade, c.title as course_title
    FROM Submissions s
    JOIN Assignments a ON s.assignment_id = a.assignment_id
    JOIN Courses c ON a.course_id = c.course_id
    WHERE s.student_id = ?
    ORDER BY s.date_submitted DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$submitted_assignments = $stmt->get_result();

// Fetch instructors for communication
$stmt = $conn->prepare("
    SELECT u.user_id, u.name, c.title as course_title
    FROM Users u
    JOIN Courses c ON c.course_id IN (
        SELECT e.course_id FROM Enrollments e WHERE e.student_id = ?
    )
    WHERE u.role = 'Instructor'
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$instructors = $stmt->get_result();
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
            
            <div class="nav-tabs">
                <a href="#overview" class="tab-link active" onclick="showTab(event, 'overview')">Overview</a>
                <a href="#courses" class="tab-link" onclick="showTab(event, 'courses')">Course Access</a>
                <a href="#assignments" class="tab-link" onclick="showTab(event, 'assignments')">Assignments</a>
                <a href="#progress" class="tab-link" onclick="showTab(event, 'progress')">Progress Tracking</a>
                <a href="#communication" class="tab-link" onclick="showTab(event, 'communication')">Communication</a>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No pending assignments</td>
                            </tr>
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
                    </tbody>
                </table>
            </div>

            <div id="progress" class="dashboard-box tab-content" style="display:none;">
                <h3>Progress Tracking</h3>
                
                <?php if ($enrolled_courses->num_rows > 0): ?>
                    <?php while($course = $enrolled_courses->fetch_assoc()): ?>
                        <h4><?php echo htmlspecialchars($course['title']); ?></h4>
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
                
                <h4>Completed Modules</h4>
                <ul class="list-group">
                    <!-- This would need additional database queries to show completed modules -->
                    <!-- For now, we'll show placeholder data -->
                    <li class="list-group-item">Module 1: AI Basics - Grade: A</li>
                    <li class="list-group-item">Module 2: Machine Learning - Grade: B+</li>
                    <li class="list-group-item">Module 3: Neural Networks - Grade: A-</li>
                    <li class="list-group-item">Module 4: Deep Learning - Grade: B</li>
                </ul>
            </div>

            <div id="communication" class="dashboard-box tab-content" style="display:none;">
                <h3>Communication</h3>
                <form action="send_message.php" method="post">
                    <div class="form-group">
                        <label for="instructorSelect">Select Instructor:</label>
                        <select class="form-control" id="instructorSelect" name="instructor_id" required>
                            <?php if ($instructors->num_rows > 0): ?>
                                <?php while($instructor = $instructors->fetch_assoc()): ?>
                                    <option value="<?php echo $instructor['user_id']; ?>">
                                        <?php echo htmlspecialchars($instructor['name'] . ' - ' . $instructor['course_title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="">No instructors available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="questionText">Your Question:</label>
                        <textarea class="form-control" id="questionText" name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachmentFile">Attach Screenshot or File:</label>
                        <input type="file" class="form-control-file" id="attachmentFile" name="attachment">
                    </div>
                    <button type="submit" class="btn btn-primary">Send Question</button>
                </form>
            </div>
        </div>
    </section>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function showTab(event, tabId) {
            event.preventDefault();
            let tabs = document.querySelectorAll('.tab-content');
            let links = document.querySelectorAll('.tab-link');
            tabs.forEach(tab => tab.style.display = 'none');
            links.forEach(link => link.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
