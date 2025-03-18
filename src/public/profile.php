<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, role, profile_image FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = $_POST['name'];
        $email = $_POST['email'];
        
        // Check if email is already in use by another user
        $check_stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ? AND user_id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email is already in use by another user.";
        } else {
            // Update user information
            $update_stmt = $conn->prepare("UPDATE Users SET name = ?, email = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $name, $email, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Profile updated successfully!";
                // Update session data
                $_SESSION['name'] = $name;
                
                // Refresh user data
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error = "Error updating profile: " . $conn->error;
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $pwd_stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = ?");
        $pwd_stmt->bind_param("i", $user_id);
        $pwd_stmt->execute();
        $pwd_result = $pwd_stmt->get_result();
        $user_pwd = $pwd_result->fetch_assoc();
        
        if (password_verify($current_password, $user_pwd['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_pwd_stmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
                    $update_pwd_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($update_pwd_stmt->execute()) {
                        $message = "Password changed successfully!";
                    } else {
                        $error = "Error changing password: " . $conn->error;
                    }
                } else {
                    $error = "New password must be at least 8 characters long.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        $filename = $_FILES['profile_image']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = 'images/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Update profile image in database
                $img_stmt = $conn->prepare("UPDATE Users SET profile_image = ? WHERE user_id = ?");
                $img_stmt->bind_param("si", $upload_path, $user_id);
                
                if ($img_stmt->execute()) {
                    $message = "Profile image updated successfully!";
                    
                    // Update user data
                    $user['profile_image'] = $upload_path;
                } else {
                    $error = "Error updating profile image in database: " . $conn->error;
                }
            } else {
                $error = "Error uploading image.";
            }
        } else {
            $error = "Invalid file type. Allowed types: jpg, jpeg, png, gif.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learning Management System - Profile Page">
    <meta name="author" content="LMS Team">
    <title>Profile | LMS</title>
    
    <!-- Core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/prettyPhoto.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* Centering the Profile Section */
        .profile-container {
            max-width: 800px;
            margin: 60px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        /* Profile Image Section */
        .profile-img-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .profile-img-container img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            background-color: #f5f5f5;
        }

        /* Default profile icon styling */
        .default-profile-icon {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid #007bff;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: #adb5bd;
        }

        .upload-btn {
            margin-top: 10px;
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        .upload-btn:hover {
            background: #0056b3;
        }

        /* Form Fields */
        .profile-container label {
            font-weight: bold;
            display: block;
            text-align: left;
            margin-top: 10px;
        }

        .profile-container input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        /* Button Styling */
        .profile-container .btn-save {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            transition: 0.3s;
        }

        .profile-container .btn-save:hover {
            background: #0056b3;
        }
        
        /* Tabs styling */
        .profile-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .profile-tabs .tab {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        
        .profile-tabs .tab.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .user-role {
            background-color: #6c757d;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .admin {
            background-color: #dc3545;
        }
        
        .instructor {
            background-color: #fd7e14;
        }
        
        .student {
            background-color: #28a745;
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
                    <?php if($_SESSION['role'] == 'Admin'): ?>
                        <li><a href="admin-dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'Instructor'): ?>
                        <li><a href="instructor-dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'Student'): ?>
                        <li><a href="student-dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="active"><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Section -->
    <div class="profile-container">
        <?php if(!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="profile-img-container">
            <?php if(!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                <img src="<?php echo $user['profile_image']; ?>" alt="Profile Picture">
            <?php else: ?>
                <!-- Display default icon instead of broken image -->
                <div class="default-profile-icon">
                    <i class="fa fa-user"></i>
                </div>
            <?php endif; ?>
            
            <form action="profile.php" method="post" enctype="multipart/form-data" id="imageForm">
                <input type="file" name="profile_image" id="profile_image" style="display: none;" onchange="document.getElementById('imageForm').submit();">
                <button type="button" class="upload-btn" onclick="document.getElementById('profile_image').click();">
                    <i class="fa fa-upload"></i> Change Picture
                </button>
            </form>
        </div>

        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
        <span class="user-role <?php echo strtolower($user['role']); ?>"><?php echo $user['role']; ?></span>
        
        <div class="profile-tabs">
            <div class="tab active" onclick="showTab('profile')">Profile Info</div>
            <div class="tab" onclick="showTab('password')">Change Password</div>
            <?php if($_SESSION['role'] == 'Student'): ?>
                <div class="tab" onclick="showTab('academic')">Academic Info</div>
            <?php endif; ?>
        </div>
        
        <div id="profile-tab" class="tab-content active">
            <form action="profile.php" method="post">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                
                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fa fa-save"></i> Update Profile
                </button>
            </form>
        </div>
        
        <div id="password-tab" class="tab-content">
            <form action="profile.php" method="post">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
                
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
                <small>Password must be at least 8 characters long</small>
                
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                
                <button type="submit" name="change_password" class="btn-save">
                    <i class="fa fa-key"></i> Change Password
                </button>
            </form>
        </div>
        
        <?php if($_SESSION['role'] == 'Student'): ?>
            <div id="academic-tab" class="tab-content">
                <?php
                // Fetch student's enrolled courses
                $stmt = $conn->prepare("
                    SELECT c.title, e.enrollment_date, e.progress_percentage, e.status
                    FROM Enrollments e
                    JOIN Courses c ON e.course_id = c.course_id
                    WHERE e.student_id = ?
                    ORDER BY e.enrollment_date DESC
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $enrollments = $stmt->get_result();
                ?>
                
                <h3>Enrolled Courses</h3>
                
                <?php if($enrollments->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Enrollment Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($enrollment = $enrollments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($enrollment['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $enrollment['progress_percentage']; ?>%;" 
                                                aria-valuenow="<?php echo $enrollment['progress_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $enrollment['progress_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = $enrollment['status'];
                                            $status_class = '';
                                            
                                            if($status == 'Completed') $status_class = 'label-success';
                                            else if($status == 'Enrolled') $status_class = 'label-primary';
                                            else if($status == 'Dropped') $status_class = 'label-danger';
                                        ?>
                                        <span class="label <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You are not enrolled in any courses yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show the selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Set the clicked tab as active
            document.querySelector(`.tab[onclick="showTab('${tabName}')"]`).classList.add('active');
        }
    </script>
</body>
</html>
