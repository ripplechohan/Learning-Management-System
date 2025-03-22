<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_role = $logged_in ? $_SESSION['role'] : '';
$user_name = $logged_in ? $_SESSION['name'] : '';

// Fetch active courses from database
$stmt = $conn->prepare("
    SELECT course_id, title, description, category, skill_level 
    FROM Courses 
    WHERE is_active = 1
    ORDER BY title
");
$stmt->execute();
$courses = $stmt->get_result();

// If user is logged in as student, get enrolled courses
$enrolled_courses = [];
if ($logged_in && $user_role == 'Student') {
    $student_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT course_id 
        FROM Enrollments 
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $enrolled_courses[] = $row['course_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Courses - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Courses | LMS</title>
    
    <!-- Core CSS -->
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
        
        h1 {
            text-align: center;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .course-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            margin-bottom: 50px;
        }
        .course-card {
            width: 350px;
            border-radius: 10px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease-in-out;
            background: white;
        }
        .course-card:hover {
            transform: translateY(-10px);
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.15);
        }
        .course-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
        }
        .course-content {
            padding: 20px;
        }
        .course-description {
            color: #555;
            margin-bottom: 15px;
            min-height: 80px;
        }
        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
        }
        .course-meta {
            display: flex;
            flex-direction: column;
        }
        .course-level {
            font-size: 14px;
            color: #6c757d;
        }
        .course-category {
            font-size: 14px;
            font-weight: bold;
            color: #343a40;
        }
        .btn-enroll {
            padding: 8px 20px;
            font-weight: bold;
        }
        .already-enrolled {
            background-color: #28a745;
            color: white;
        }
        .course-filters {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .filter-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: #343a40;
        }
        .filter-group {
            margin-bottom: 15px;
        }
        .no-courses {
            text-align: center;
            padding: 50px;
            font-size: 18px;
            color: #6c757d;
        }
        footer {
            margin-top: 50px;
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
                    <li class="active"><a href="courses.php">Courses</a></li>
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

    <section>
        <div class="container">
           
            
            <div class="row">
                <div class="col-md-3">
                    <div class="course-filters">
                        <h4 class="filter-title">Filter Courses</h4>
                        
                        <div class="filter-group">
                            <label for="categoryFilter">Category:</label>
                            <select class="form-control" id="categoryFilter">
                                <option value="">All Categories</option>
                                <?php
                                // Fetch unique categories
                                $stmt = $conn->query("SELECT DISTINCT category FROM Courses WHERE category IS NOT NULL AND category != '' ORDER BY category");
                                while($category = $stmt->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($category['category']) . '">' . htmlspecialchars($category['category']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="levelFilter">Skill Level:</label>
                            <select class="form-control" id="levelFilter">
                                <option value="">All Levels</option>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="searchFilter">Search:</label>
                            <input type="text" class="form-control" id="searchFilter" placeholder="Search courses...">
                        </div>
                        
                        <button class="btn btn-primary btn-block" onclick="resetFilters()">Reset Filters</button>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="course-container" id="courseContainer">
                        <?php if ($courses->num_rows > 0): ?>
                            <?php while($course = $courses->fetch_assoc()): ?>
                                <div class="course-card" 
                                     data-category="<?php echo htmlspecialchars($course['category']); ?>" 
                                     data-level="<?php echo htmlspecialchars($course['skill_level']); ?>">
                                    <div class="course-header">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </div>
                                    <div class="course-content">
                                        <div class="course-description">
                                            <?php 
                                            if (!empty($course['description'])) {
                                                echo htmlspecialchars(substr($course['description'], 0, 150)) . (strlen($course['description']) > 150 ? '...' : '');
                                            } else {
                                                echo 'No description available for this course.';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="course-footer">
                                        <div class="course-meta">
                                            <span class="course-category"><?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'General'; ?></span>
                                            <span class="course-level"><?php echo htmlspecialchars($course['skill_level']); ?></span>
                                        </div>
                                        <?php if ($logged_in && $user_role == 'Student'): ?>
                                            <?php if (in_array($course['course_id'], $enrolled_courses)): ?>
                                                <a href="course-details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-success already-enrolled">
                                                    <i class="fa fa-check"></i> Enrolled
                                                </a>
                                            <?php else: ?>
                                                <a href="enroll.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary btn-enroll">
                                                    Enroll Now
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($logged_in && $user_role == 'Instructor'): ?>
                                            <a href="course-details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-info btn-enroll">
                                                View Details
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $logged_in ? 'course-details.php?id=' . $course['course_id'] : 'login.php'; ?>" class="btn btn-primary btn-enroll">
                                                <?php echo $logged_in ? 'View Details' : 'Login to Enroll'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-courses">
                                <i class="fa fa-book fa-3x mb-3"></i>
                                <p>No courses are available at the moment.</p>
                                <p>Please check back later or contact an administrator.</p>
                            </div>
                        <?php endif; ?>
                    </div>
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
    
    <script>
        $(document).ready(function() {
            // Filter functionality
            $("#categoryFilter, #levelFilter").change(filterCourses);
            $("#searchFilter").keyup(filterCourses);
            
            function filterCourses() {
                var categoryFilter = $("#categoryFilter").val().toLowerCase();
                var levelFilter = $("#levelFilter").val();
                var searchFilter = $("#searchFilter").val().toLowerCase();
                
                $(".course-card").each(function() {
                    var category = $(this).data("category").toLowerCase();
                    var level = $(this).data("level");
                    var title = $(this).find(".course-header").text().toLowerCase();
                    var description = $(this).find(".course-description").text().toLowerCase();
                    
                    var categoryMatch = categoryFilter === "" || category === categoryFilter;
                    var levelMatch = levelFilter === "" || level === levelFilter;
                    var searchMatch = title.indexOf(searchFilter) > -1 || description.indexOf(searchFilter) > -1;
                    
                    if (categoryMatch && levelMatch && searchMatch) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                
                // Check if no courses are visible
                if ($(".course-card:visible").length === 0) {
                    if ($("#noCoursesMessage").length === 0) {
                        $("#courseContainer").append('<div id="noCoursesMessage" class="no-courses"><p>No courses match your filters.</p><p>Try adjusting your filter criteria.</p></div>');
                    }
                } else {
                    $("#noCoursesMessage").remove();
                }
            }
        });
        
        function resetFilters() {
            $("#categoryFilter").val("");
            $("#levelFilter").val("");
            $("#searchFilter").val("");
            $(".course-card").show();
            $("#noCoursesMessage").remove();
        }
    </script>
</body>
</html>
