<?php
session_start();
include 'db_connection.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: admin-dashboard.php");
            break;
        case 'Instructor':
            header("Location: instructor-dashboard.php");
            break;
        case 'Student':
            header("Location: student-dashboard.php");
            break;
    }
    exit();
}

// Check if registration was successful
if (isset($_SESSION['reg_success'])) {
    $success_message = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $user_type = $_POST['user-type'];
    
    // Validate form data
    $errors = array();
    
    if (empty($username) || empty($password) || empty($user_type)) {
        $errors[] = "All fields are required";
    }
    
    // If no validation errors, check credentials
    if (empty($errors)) {
        // Convert user type to match database role format
        $role = ucfirst($user_type);
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, name, password, role FROM Users WHERE email = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password (use password_verify if passwords are hashed)
            if (password_verify($password, $user['password']) && $user['role'] == $role) {
                // Login successful, set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login timestamp
                $update_stmt = $conn->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'Admin':
                        header("Location: admin-dashboard.php");
                        break;
                    case 'Instructor':
                        header("Location: instructor-dashboard.php");
                        break;
                    case 'Student':
                        header("Location: student-dashboard.php");
                        break;
                }
                exit();
            } else {
                $errors[] = "Invalid email/password or user type";
            }
        } else {
            $errors[] = "User not found";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LMS</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">

    <style>
        body {
            background-color: white;
        }
        #login {
            background-color: #f8f9fa;
            padding: 50px 0;
        }
        .login-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
		footer {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60px; /* Adjust height as needed */
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
                <a class="navbar-brand" href="home.php"><img src="images/logo.png" alt="OLMS Logo" width="200" height="74" ></a>
            </div>
            <div class="collapse navbar-collapse navbar-right">
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <section id="login">
        <div class="container">
            <div class="row">
                <div class="col-md-4 col-md-offset-4 login-box">
                    <h2 class="text-center">LOGIN</h2>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post">
                        <div class="form-group">
                            <label for="username">Email:</label>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="•••••••" required>
                        </div>
                        <div class="form-group">
                            <label for="user-type">User Type:</label>
                            <select id="user-type" name="user-type" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="instructor">Instructor</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">LOGIN</button>
                        <div class="text-center mt-3">
                            <a href="register.php" class="btn btn-link">Don't have an account? Register</a>
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
</body>
</html>
