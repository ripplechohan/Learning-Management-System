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

// Get student performance data
$stmt = $conn->prepare("
    SELECT u.name, c.title as course_title, e.progress_percentage,
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
    LIMIT 10
");
$stmt->execute();
$student_performance = $stmt->get_result();
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
            
            <!-- Tabs Navigation -->
            <div class="nav-tabs">
                <a href="#overview" class="tab-link active" onclick="showTab(event, 'overview')">Overview</a>
                <a href="#courses" class="tab-link" onclick="showTab(event, 'courses')">Course Management</a>
                <a href="#assignments" class="tab-link" onclick="showTab(event, 'assignments')">Assignments & Quizzes</a>
                <a href="#students" class="tab-link" onclick="showTab(event, 'students')">Student Performance</a>
                <a href="#communication" class="tab-link" onclick="showTab(event, 'communication')">Communication</a>
                <a href="#reports" class="tab-link" onclick="showTab(event, 'reports')">Reports & Analytics</a>
            </div>

            <!-- Overview Section -->
            <div id="overview" class="dashboard-box tab-content">
                <h3>Overview</h3>
                <p>Here's a quick look at your courses and tasks.</p>
                <p>Courses Assigned: <strong><?php echo $course_count; ?></strong> | 
                   Assignments Due Soon: <strong><?php echo $upcoming_deadlines->num_rows; ?></strong> | 
                   Student Questions Pending Response: <strong><?php echo $question_count; ?></strong></p>
                
                <h4>Upcoming Deadlines</h4>
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
                                        <button type="button" class="btn btn-warning btn-sm" onclick="editCourse(<?php echo $course['course_id']; ?>)">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $course['course_id']; ?>)">Delete</button>
                                        <a href="#assignments" class="btn btn-info btn-sm" onclick="showTab(event, 'assignments'); highlightCourse(<?php echo $course['course_id']; ?>)">
                                            Manage Assignments
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
                <h3>Assignments & Quizzes by Course</h3>
                
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

            <!-- Student Performance Section -->
            <div id="students" class="dashboard-box tab-content" style="display:none;">
                <h3>Student Performance</h3>
                <input type="text" class="form-control mb-3" id="studentSearch" onkeyup="searchStudents()" placeholder="Search Students">
                <table class="table" id="studentTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Progress</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($student_performance->num_rows > 0): ?>
                            <?php while($student = $student_performance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars($student['progress_percentage']); ?>%</td>
                                    <td><?php echo htmlspecialchars($student['grade']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No student data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Communication Section -->
            <div id="communication" class="dashboard-box tab-content" style="display:none;">
                <h3>Communication</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Question</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_questions->num_rows > 0): ?>
                            <?php while($question = $pending_questions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($question['student_name']); ?></td>
                                    <td>Question about course material</td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($question['sent_at']))); ?></td>
                                    <td>Pending</td>
                                    <td>
                                        <a href="respond.php?id=<?php echo $question['notification_id']; ?>" class="btn btn-primary">Respond</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No pending questions</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/chart.min.js"></script>
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
        
        function highlightCourse(courseId) {
            // First close all courses
            document.querySelectorAll('.course-content').forEach(content => {
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
        
        function editCourse(courseId) {
            // This would load course data and open the modal
            alert("Edit course " + courseId + " (would open modal with course data)");
        }
        
        function editAssignment(assignmentId) {
            // This would load assignment data and open the modal
            alert("Edit assignment " + assignmentId + " (would open modal with assignment data)");
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
       
       function searchStudents() {
           let input = document.getElementById("studentSearch");
           let filter = input.value.toUpperCase();
           let table = document.getElementById("studentTable");
           let tr = table.getElementsByTagName("tr");

           for (let i = 1; i < tr.length; i++) { // Start from 1 to skip header row
               let name = tr[i].getElementsByTagName("td")[0];
               let course = tr[i].getElementsByTagName("td")[1];
               if (name || course) {
                   let txtName = name.textContent || name.innerText;
                   let txtCourse = course.textContent || course.innerText;
                   if (txtName.toUpperCase().indexOf(filter) > -1 || txtCourse.toUpperCase().indexOf(filter) > -1) {
                       tr[i].style.display = "";
                   } else {
                       tr[i].style.display = "none";
                   }
               }
           }
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
       });
   </script>
</body>
</html>
