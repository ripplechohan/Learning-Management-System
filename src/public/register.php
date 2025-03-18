<?php
session_start();
include 'db_connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role = $_POST['role']; // student or instructor
    
    // Validate form data
    $errors = array();
    
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $errors[] = "All fields are required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    
    // If no errors, insert the new user
    if (empty($errors)) {
        // Convert role to proper format for database (Admin, Instructor, Student)
        $db_role = ucfirst($role);
        
        // In a production environment, you should hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO Users (name, email, password, role, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $db_role);
        
        if ($stmt->execute()) {
            // Registration successful
            $_SESSION['reg_success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register | LMS</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <style>
        body {
            background-color: white;
        }
        #contact-us {
            background-color: #f8f9fa;
            padding: 50px 0;
        }
        .contact-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
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
                <ul class="nav navbar-nav navbar-right">
                <li><a href="home.php">Home</a></li>
                <li><a href="about-us.php">About Us</a></li>
                
                <li><a href="login.php">Login</a></li>
            </ul>
            </div>
        </div>
    </nav>
    
    <section id="register">
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-md-offset-3 register-box">
                    <h2 class="text-center">REGISTER</h2>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="post">
                        <div class="form-group">
                            <label for="fullname">Full Name:</label>
                            <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Enter your full name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password:</label>
                            <input type="password" id="confirm-password" name="confirm-password" class="form-control" placeholder="Confirm your password" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                            </select>
                        </div>
                        <div class="form-group" id="extra-fields" style="display: none;">
                            <label for="extra-info">Institution / Area of Expertise:</label>
                            <input type="text" id="extra-info" name="extra-info" class="form-control" placeholder="Enter institution or expertise">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">REGISTER</button>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-link">Already have an account? Login</a>
                        </div>
                    </form>
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
        document.getElementById('role').addEventListener('change', function() {
            var extraField = document.getElementById('extra-fields');
            if (this.value === 'instructor') {
                extraField.style.display = 'block';
            } else {
                extraField.style.display = 'none';
            }
        });
    </script>
</body>
</html>
