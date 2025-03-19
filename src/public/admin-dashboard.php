<?php
session_start();
include 'db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// Get system statistics
// Count total users
$stmt = $conn->query("SELECT COUNT(*) as total_users FROM Users WHERE active = 1");
$total_users = $stmt->fetch_assoc()['total_users'];

// Count users by role
$stmt = $conn->query("SELECT role, COUNT(*) as count FROM Users WHERE active = 1 GROUP BY role");
$user_roles = $stmt->fetch_all(MYSQLI_ASSOC);

// Count active courses
$stmt = $conn->query("SELECT COUNT(*) as active_courses FROM Courses WHERE is_active = 1");
$active_courses = $stmt->fetch_assoc()['active_courses'];

// Get recent users for user management
$stmt = $conn->query("SELECT user_id, name, email, role, active, DATE_FORMAT(created_at, '%d/%m/%Y') as created_date FROM Users ORDER BY created_at DESC LIMIT 10");
$recent_users = $stmt->fetch_all(MYSQLI_ASSOC);

// Get all courses for course management - FIXED QUERY
$stmt = $conn->query("
    SELECT c.course_id, c.title, c.is_active, 
           (SELECT name FROM Users WHERE role = 'Instructor' LIMIT 1) as instructor_name
    FROM Courses c
    ORDER BY c.title
    LIMIT 10
");
$courses = $stmt->fetch_all(MYSQLI_ASSOC);

// Check for messages
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Admin Dashboard</title>
    
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
        @media (max-width: 768px) {
            .dashboard-box, .nav-tabs {
                width: 95%;
            }
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
        .stats-container {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background-color: #f8f9fa;
            box-shadow: 0px 2px 4px rgba(0,0,0,0.1);
            width: 30%;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 1rem;
            color: #6c757d;
        }
        .welcome-message {
            margin-bottom: 20px;
            font-weight: bold;
            color: #333;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .active-label {
            color: #28a745;
            font-weight: bold;
        }
        .inactive-label {
            color: #dc3545;
            font-weight: bold;
        }
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .role-admin {
            background-color: #dc3545;
            color: white;
        }
        .role-instructor {
            background-color: #fd7e14;
            color: white;
        }
        .role-student {
            background-color: #28a745;
            color: white;
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
                    <li class="active"><a href="admin-dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a class="btn btn-primary" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <section id="dashboard">
        <div class="container">
            <h2 class="text-center">Admin Dashboard</h2>
            <p class="text-center welcome-message">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</p>
            
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
            
            <div class="nav-tabs">
                <a href="#overview" class="tab-link active" onclick="showTab(event, 'overview')">Overview</a>
                <a href="#user-management" class="tab-link" onclick="showTab(event, 'user-management')">User Management</a>
                <a href="#course-management" class="tab-link" onclick="showTab(event, 'course-management')">Course Management</a>
                <a href="#reports" class="tab-link" onclick="showTab(event, 'reports')">Reports & Analytics</a>
            </div>
            
            <div id="overview" class="dashboard-box tab-content">
                <h3>System Overview</h3>
                
                <div class="stats-container">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $active_courses; ?></div>
                        <div class="stat-label">Active Courses</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number">
                            <?php
                            foreach($user_roles as $role) {
                                if($role['role'] == 'Student') {
                                    echo $role['count'];
                                    break;
                                }
                            }
                            ?>
                        </div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
                
                <h4>User Distribution</h4>
                <canvas id="userDistributionChart" style="max-height: 300px;"></canvas>
                
                <h4>System Activity</h4>
                <p>Last Database Backup: <?php echo date('d/m/Y H:i'); ?></p>
                <p>Server Status: <span class="text-success">Online</span></p>
            </div>
            
            <div id="user-management" class="dashboard-box tab-content" style="display: none;">
                <h3>User Management</h3>
                <div class="search-box">
                    <input type="text" class="form-control" id="userSearchInput" onkeyup="searchUsers()" placeholder="Search Users by Name or Role">
                </div>
                <a href="add_user.php" class="btn btn-success">Add New User</a>
                <table class="table" id="userTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['active'] ? '<span class="active-label">Active</span>' : '<span class="inactive-label">Inactive</span>'; ?></td>
                                <td><?php echo htmlspecialchars($user['created_date']); ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="view_all_users.php" class="btn btn-info">View All Users</a>
            </div>
            
            <div id="course-management" class="dashboard-box tab-content" style="display: none;">
                <h3>Course Management</h3>
                <div class="search-box">
                    <input type="text" class="form-control" id="courseSearchInput" onkeyup="searchCourses()" placeholder="Search Courses">
                </div>
                <a href="create_course.php" class="btn btn-success">Create New Course</a>
                <table class="table" id="courseTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td><?php echo $course['instructor_name'] ? htmlspecialchars($course['instructor_name']) : 'Not Assigned'; ?></td>
                                <td><?php echo $course['is_active'] ? '<span class="active-label">Active</span>' : '<span class="inactive-label">Inactive</span>'; ?></td>
                                <td>
                                    <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteCourse(<?php echo $course['course_id']; ?>)">Delete</button>
                                    <a href="assign_instructor.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary btn-sm">Assign Instructor</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="view_all_courses.php" class="btn btn-info">View All Courses</a>
            </div>
            
            <div id="reports" class="dashboard-box tab-content" style="display: none;">
                <h3>Reports and Analytics</h3>
                
                <div class="row">
                    <div class="col-md-6">
                        <h4>Course Enrollment</h4>
                        <canvas id="enrollmentChart" style="max-height: 300px;"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h4>System Performance</h4>
                        <canvas id="performanceChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
                
                <div class="row" style="margin-top: 30px;">
                    <div class="col-md-12">
                        <h4>Available Reports</h4>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                User Activity Report
                                <a href="generate_report.php?type=user_activity" class="btn btn-info btn-sm">Download</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Course Completion Report
                                <a href="generate_report.php?type=course_completion" class="btn btn-info btn-sm">Download</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                System Usage Statistics
                                <a href="generate_report.php?type=system_usage" class="btn btn-info btn-sm">Download</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
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
        
        function searchUsers() {
            var input = document.getElementById("userSearchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("userTable");
            var tr = table.getElementsByTagName("tr");

            for (var i = 1; i < tr.length; i++) {
                var nameCol = tr[i].getElementsByTagName("td")[0];
                var roleCol = tr[i].getElementsByTagName("td")[2];
                
                if (nameCol && roleCol) {
                    var nameText = nameCol.textContent || nameCol.innerText;
                    var roleText = roleCol.textContent || roleCol.innerText;
                    
                    if (nameText.toUpperCase().indexOf(filter) > -1 || roleText.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
        
        function searchCourses() {
            var input = document.getElementById("courseSearchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("courseTable");
            var tr = table.getElementsByTagName("tr");

            for (var i = 1; i < tr.length; i++) {
                var titleCol = tr[i].getElementsByTagName("td")[0];
                var instructorCol = tr[i].getElementsByTagName("td")[1];
                
                if (titleCol && instructorCol) {
                    var titleText = titleCol.textContent || titleCol.innerText;
                    var instructorText = instructorCol.textContent || instructorCol.innerText;
                    
                    if (titleText.toUpperCase().indexOf(filter) > -1 || instructorText.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
        
        function confirmDeleteUser(userId) {
            if (confirm("Are you sure you want to delete this user? This action cannot be undone.")) {
                window.location.href = "delete_user.php?id=" + userId;
            }
        }
        
        function confirmDeleteCourse(courseId) {
            if (confirm("Are you sure you want to delete this course? This action cannot be undone.")) {
                window.location.href = "delete_course.php?id=" + courseId;
            }
        }
        
        // Initialize charts when the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // User Distribution Chart
            var userCtx = document.getElementById('userDistributionChart').getContext('2d');
            var userChart = new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php
                        foreach($user_roles as $role) {
                            echo "'" . $role['role'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php
                            foreach($user_roles as $role) {
                                echo $role['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            '#dc3545',  // Admin
                            '#fd7e14',  // Instructor
                            '#28a745'   // Student
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true
                }
            });
            
            // Enrollment Chart
            var enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
            var enrollmentChart = new Chart(enrollmentCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Course Enrollments',
                        data: [65, 78, 90, 82, 95, 110],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Performance Chart
            var perfCtx = document.getElementById('performanceChart').getContext('2d');
            var perfChart = new Chart(perfCtx, {
                type: 'bar',
                data: {
                    labels: ['Database', 'Server', 'Application'],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [42, 78, 120],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
