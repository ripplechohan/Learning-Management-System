<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learning Management System - Logout">
    <meta name="author" content="LMS Team">
    <title>Logout | LMS</title>
    
    <!-- core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">
    <link href="css/prettyPhoto.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
                <ul class="nav navbar-nav">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <section>
        <div class="container text-center" style="margin-top: 100px;">
            <h2>You have been logged out</h2>
            <p>Thank you for using our Learning Management System. Click below to log in again.</p>
            <a href="login.php" class="btn btn-primary">Login Again</a>
        </div>
        <div class="container text-center" style="margin-top: 100px;">
            <p>Redirecting to Home in 5 seconds...</p>
        </div>
    </section>
    
    <script>
        setTimeout(function() { window.location.href = "home.php"; }, 5000);
    </script>
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
