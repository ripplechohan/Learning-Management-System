<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_role = $logged_in ? $_SESSION['role'] : '';
$user_name = $logged_in ? $_SESSION['name'] : '';

// Fetch course count for statistics
$stmt = $conn->query("SELECT COUNT(*) as course_count FROM Courses WHERE is_active = 1");
$course_count = $stmt->fetch_assoc()['course_count'];

// Fetch student count for statistics
$stmt = $conn->query("SELECT COUNT(*) as student_count FROM Users WHERE role = 'Student' AND active = 1");
$student_count = $stmt->fetch_assoc()['student_count'];

// Fetch instructor count for statistics
$stmt = $conn->query("SELECT COUNT(*) as instructor_count FROM Users WHERE role = 'Instructor' AND active = 1");
$instructor_count = $stmt->fetch_assoc()['instructor_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learning Management System - Enhance your learning experience.">
    <meta name="author" content="LMS Team">
    <title>Home | LMS</title>
    
    <!-- core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/prettyPhoto.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .stats-container {
            background-color: #f8f9fa;
            padding: 30px 0;
            margin: 30px 0;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-10px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .stat-title {
            font-size: 1.2rem;
            color: #343a40;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #6c757d;
        }
        
        .welcome-message {
            background-color: rgba(0, 123, 255, 0.1);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        
        .cta-section {
            background-color: #343a40;
            color: #fff;
            padding: 60px 0;
            text-align: center;
            margin: 40px 0 0 0;
        }
        
        .cta-section h2 {
            margin-bottom: 20px;
        }
        
        .cta-buttons {
            margin-top: 30px;
        }
        
        .cta-buttons .btn {
            margin: 0 10px;
            padding: 10px 30px;
            font-size: 18px;
        }
        
        /* Fix for the footer */
        .footer {
            padding: 20px 0;
            background-color: #212529;
            color: #fff;
        }
        
        /* Fix spelling error */
        .typo-fix {
            font-size: inherit;
        }
    </style>
</head>
<body class="homepage">
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
                    <li class="active"><a href="home.php">Home</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    
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

    <?php if($logged_in): ?>
    <div class="container">
        <div class="welcome-message">
            <h3>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h3>
            <p>Continue your learning journey or explore new courses to expand your knowledge.</p>
            <a href="<?php echo strtolower($user_role); ?>-dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
    <?php endif; ?>

    <section id="main-slider" class="no-margin">
        <div class="carousel slide">
            <ol class="carousel-indicators">
                <li data-target="#main-slider" data-slide-to="0" class="active"></li>
                <li data-target="#main-slider" data-slide-to="1"></li>
                <li data-target="#main-slider" data-slide-to="2"></li>
            </ol>
            <div class="carousel-inner">
                <div class="item active" style="background-image: url(images/slider/bg1.jpg)">
                    <div class="container">
                        <div class="row slide-margin">
                            <div class="col-sm-6">
                                <div class="carousel-content">
                                    <h1 class="animation animated-item-1">Welcome to Online Learning Management System</h1>
                                    <h2 class="animation animated-item-2">Enhance your education with AI-powered insights...</h2>
                                </div>
                            </div>
                            <div class="col-sm-6 hidden-xs animation animated-item-4">
                                <div class="slider-img">
                                    <img src="images/slider/img1.png" class="img-responsive">
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--/.item-->

                <div class="item" style="background-image: url(images/slider/bg2.jpg)">
                    <div class="container">
                        <div class="row slide-margin">
                            <div class="col-sm-6">
                                <div class="carousel-content">
                                    <h1 class="animation animated-item-1">Teach with Us</h1>
                                    <h2 class="animation animated-item-2">Join LMS as an instructor and inspire learners worldwide...</h2>
                                </div>
                            </div>
                            <div class="col-sm-6 hidden-xs animation animated-item-4">
                                <div class="slider-img">
                                    <img src="images/slider/img2.png" class="img-responsive">
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--/.item-->

                <div class="item" style="background-image: url(images/slider/bg3.jpg)">
                    <div class="container">
                        <div class="row slide-margin">
                            <div class="col-sm-6">
                                <div class="carousel-content">
                                    <h1 class="animation animated-item-1">Interactive Learning</h1>
                                    <h2 class="animation animated-item-2">Enhance your education with AI-powered insights...</h2>
                                </div>
                            </div>
                            <div class="col-sm-6 hidden-xs animation animated-item-4">
                                <div class="slider-img">
                                    <img src="images/slider/img3.png" class="img-responsive">
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--/.item-->
            </div><!--/.carousel-inner-->
        </div><!--/.carousel-->
        <a class="prev hidden-xs" href="#main-slider" data-slide="prev">
            <i class="fa fa-chevron-left"></i>
        </a>
        <a class="next hidden-xs" href="#main-slider" data-slide="next">
            <i class="fa fa-chevron-right"></i>
        </a>
    </section>
    
    <!-- Statistics Section -->
    <div class="stats-container">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fa fa-book"></i>
                        </div>
                        <div class="stat-number"><?php echo $course_count; ?>+</div>
                        <div class="stat-title">Active Courses</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fa fa-user-graduate"></i>
                        </div>
                        <div class="stat-number"><?php echo $student_count; ?>+</div>
                        <div class="stat-title">Students Enrolled</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fa fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-number"><?php echo $instructor_count; ?>+</div>
                        <div class="stat-title">Expert Instructors</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <section id="feature">
        <div class="container">
           <div class="center wow fadeInDown">
                <h2>Key Features of Our Online Learning Management System</h2>
                <p class="lead">Enhancing online education with AI, automation, and seamless course management</p>
            </div>

            <div class="row">
                <div class="features">
                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-user-cog"></i>
                           <h3>User & Course Management</h3>
                            <p>Admins & instructors can manage users, create courses, and track progress.</p>
                        </div>
                    </div><!--/.col-md-4-->

                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-brain"></i>
                            <h3>AI-Powered Grading</h3>
                            <p>Automated quiz grading with AI-generated feedback and instant notifications.</p>
                        </div>
                    </div><!--/.col-md-4-->

                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-chart-line"></i>
                            <h3>Student Progress Tracking</h3>
                            <p>View grades, completed modules, and receive automatic deadline reminders.</p>
                        </div>
                    </div><!--/.col-md-4-->
                
                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-comments"></i>
                            <h3>Ask a Professor</h3>
                            <p>Students can submit questions to instructors, attach files, and receive expert guidance.</p>
                        </div>
                    </div><!--/.col-md-4-->

                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-cogs"></i>
                            <h3>Discussion & Collaboration</h3>
                            <p>Engage in forums and peer discussions for interactive learning.</p>
                        </div>
                    </div><!--/.col-md-4-->

                    <div class="col-md-4 col-sm-6 wow fadeInDown" data-wow-duration="1000ms" data-wow-delay="600ms">
                        <div class="feature-wrap">
                            <i class="fa fa-lock"></i>
                            <h3>Secure Access & Authentication</h3>
                            <p>Role-Based Access Control for data protection.</p>
                        </div>
                    </div><!--/.col-md-4-->
                </div><!--/.services-->
            </div><!--/.row-->    
        </div><!--/.container-->
    </section><!--/#feature-->
    
    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Start Your Learning Journey?</h2>
            <p>Join thousands of students and instructors in our online learning community.</p>
            
            <div class="cta-buttons">
                <?php if(!$logged_in): ?>
                    <a href="register.php" class="btn btn-primary">Register Now</a>
                    <a href="login.php" class="btn btn-default">Login</a>
                <?php else: ?>
                    <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                    <a href="<?php echo strtolower($user_role); ?>-dashboard.php" class="btn btn-default">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2025 Online Learning Management System | All Rights Reserved</p>
                </div>
                <div class="col-md-6 text-right">
                    <ul class="list-inline">
                        <li><a href="about-us.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize wow.js for animations
            new WOW().init();
            
            // Activate the carousel
            $('.carousel').carousel({
                interval: 5000
            });
        });
    </script>
</body>
</html>
