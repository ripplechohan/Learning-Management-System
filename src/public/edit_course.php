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

// Check if course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No course selected for editing.";
    header("Location: instructor-dashboard.php");
    exit();
}

$course_id = $_GET['id'];

// Get course data
$stmt = $conn->prepare("
    SELECT course_id, title, description, category, skill_level, 
           prereq_course_id, start_date, end_date, is_active
    FROM Courses 
    WHERE course_id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "The selected course does not exist.";
    header("Location: instructor-dashboard.php");
    exit();
}

$course = $result->fetch_assoc();

// Get course materials
$stmt = $conn->prepare("
    SELECT material_id, title, type, file_path, upload_date
    FROM CourseMaterials
    WHERE course_id = ?
    ORDER BY upload_date DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$materials = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_course'])) {
        // Update course information
        $title = $_POST['title'];
        $description = $_POST['description'] ?? null;
        $category = $_POST['category'] ?? null;
        $skill_level = $_POST['skill_level'];
        $prereq_course_id = !empty($_POST['prereq_course_id']) ? $_POST['prereq_course_id'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $is_active = $_POST['is_active'];
        
        // Update course in database
        $stmt = $conn->prepare("
            UPDATE Courses SET 
                title = ?, 
                description = ?, 
                category = ?, 
                skill_level = ?,
                prereq_course_id = ?,
                start_date = ?,
                end_date = ?,
                is_active = ?
            WHERE course_id = ?
        ");
        
        $stmt->bind_param(
            "ssssissii", 
            $title, $description, $category, $skill_level,
            $prereq_course_id, $start_date, $end_date, $is_active,
            $course_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Course updated successfully!";
            // Refresh course data
            $stmt = $conn->prepare("SELECT * FROM Courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $course = $result->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Error updating course: " . $conn->error;
        }
    }
    
    // Handle material upload
    if (isset($_POST['add_material'])) {
        $material_title = $_POST['material_title'];
        $material_type = $_POST['material_type'];
        $upload_date = date('Y-m-d H:i:s');
        
        // Handle file upload for videos
        if ($material_type == 'video' && isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
            $allowed = array('mp4', 'webm', 'ogg');
            $filename = $_FILES['video_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'course_' . $course_id . '_' . time() . '.' . $ext;
                $upload_dir = 'uploads/videos/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $upload_path)) {
                    // Insert into database
                    $stmt = $conn->prepare("
                        INSERT INTO CourseMaterials (course_id, title, type, file_path, upload_date)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issss", $course_id, $material_title, $material_type, $upload_path, $upload_date);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Video uploaded successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error adding video to database: " . $conn->error;
                    }
                } else {
                    $_SESSION['error_message'] = "Error uploading video file.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid video file type. Allowed types: mp4, webm, ogg.";
            }
        }
        // Handle notes upload
        else if ($material_type == 'note') {
            $note_content = $_POST['note_content'];
            $upload_dir = 'uploads/notes/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'note_' . $course_id . '_' . time() . '.txt';
            $upload_path = $upload_dir . $new_filename;
            
            // Save note content to file
            if (file_put_contents($upload_path, $note_content)) {
                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO CourseMaterials (course_id, title, type, file_path, upload_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issss", $course_id, $material_title, $material_type, $upload_path, $upload_date);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Note added successfully!";
                } else {
                    $_SESSION['error_message'] = "Error adding note to database: " . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = "Error saving note content.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid material type or missing file.";
        }
        
        // Refresh materials list
        $stmt = $conn->prepare("
            SELECT material_id, title, type, file_path, upload_date
            FROM CourseMaterials
            WHERE course_id = ?
            ORDER BY upload_date DESC
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $materials = $stmt->get_result();
    }
    
    // Handle material deletion
    if (isset($_POST['delete_material']) && isset($_POST['material_id'])) {
        $material_id = $_POST['material_id'];
        
        // Get file path before deleting record
        $stmt = $conn->prepare("SELECT file_path FROM CourseMaterials WHERE material_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $material_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $material = $result->fetch_assoc();
            $file_path = $material['file_path'];
            
            // Delete record from database
            $stmt = $conn->prepare("DELETE FROM CourseMaterials WHERE material_id = ?");
            $stmt->bind_param("i", $material_id);
            
            if ($stmt->execute()) {
                // Delete the file if it exists
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $_SESSION['success_message'] = "Material deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting material: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Material not found or you don't have permission to delete it.";
        }
        
        // Refresh materials list
        $stmt = $conn->prepare("
            SELECT material_id, title, type, file_path, upload_date
            FROM CourseMaterials
            WHERE course_id = ?
            ORDER BY upload_date DESC
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $materials = $stmt->get_result();
    }
}

// Get all courses for prerequisite dropdown
$stmt = $conn->prepare("
    SELECT course_id, title 
    FROM Courses 
    WHERE course_id != ?
    ORDER BY title
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$all_courses = $stmt->get_result();

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
    <meta name="description" content="Edit Course - Learning Management System">
    <meta name="author" content="LMS Team">
    <title>Edit Course | LMS</title>
    
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
        .edit-course-container {
            max-width: 900px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .page-title {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            margin-bottom: 20px;
        }
        .material-item {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .material-item:hover {
            background-color: #f9f9f9;
        }
        .material-item h4 {
            margin-top: 0;
        }
        .material-type {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 10px;
        }
        .material-type.video {
            background-color: #d4edda;
            color: #155724;
        }
        .material-type.note {
            background-color: #cce5ff;
            color: #004085;
        }
        .material-date {
            color: #6c757d;
            font-size: 12px;
        }
        .material-actions {
            margin-top: 10px;
        }
        .form-buttons {
            margin-top: 20px;
            text-align: right;
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
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="instructor-dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a class="btn btn-primary" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="edit-course-container">
        <h2 class="page-title">Edit Course: <?php echo htmlspecialchars($course['title']); ?></h2>
        
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
        
        <ul class="nav nav-tabs">
            <li class="active"><a href="#course-info" data-toggle="tab">Course Information</a></li>
            <li><a href="#course-materials" data-toggle="tab">Course Materials</a></li>
        </ul>
        
        <div class="tab-content">
            <!-- Course Information Tab -->
            <div class="tab-pane active" id="course-info">
                <form action="edit_course.php?id=<?php echo $course_id; ?>" method="post">
                    <div class="form-group">
                        <label for="title">Course Title:</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($course['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="skill_level">Skill Level:</label>
                        <select class="form-control" id="skill_level" name="skill_level" required>
                            <option value="Beginner" <?php echo $course['skill_level'] == 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo $course['skill_level'] == 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced" <?php echo $course['skill_level'] == 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="prereq_course_id">Prerequisite Course (if any):</label>
                        <select class="form-control" id="prereq_course_id" name="prereq_course_id">
                            <option value="">None</option>
                            <?php while($prereq_course = $all_courses->fetch_assoc()): ?>
                                <option value="<?php echo $prereq_course['course_id']; ?>" <?php echo $course['prereq_course_id'] == $prereq_course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prereq_course['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $course['start_date']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $course['end_date']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="is_active">Status:</label>
                        <select class="form-control" id="is_active" name="is_active">
                            <option value="1" <?php echo $course['is_active'] ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo !$course['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <a href="instructor-dashboard.php" class="btn btn-default">Cancel</a>
                        <button type="submit" name="update_course" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
            
            <!-- Course Materials Tab -->
            <div class="tab-pane" id="course-materials">
                <h3>Course Materials</h3>
                
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Add New Material</h3>
                    </div>
                    <div class="panel-body">
                        <form action="edit_course.php?id=<?php echo $course_id; ?>" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="material_title">Title:</label>
                                <input type="text" class="form-control" id="material_title" name="material_title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="material_type">Type:</label>
                                <select class="form-control" id="material_type" name="material_type" onchange="showMaterialForm()" required>
                                    <option value="">Select Type</option>
                                    <option value="video">Video</option>
                                    <option value="note">Note/Text</option>
                                </select>
                            </div>
                            
                            <div id="video-form" style="display: none;">
                                <div class="form-group">
                                    <label for="video_file">Upload Video File:</label>
                                    <input type="file" class="form-control" id="video_file" name="video_file" accept="video/mp4,video/webm,video/ogg">
                                    <small class="text-muted">Supported formats: MP4, WebM, OGG</small>
                                </div>
                            </div>
                            
                            <div id="note-form" style="display: none;">
                                <div class="form-group">
                                    <label for="note_content">Note Content:</label>
                                    <textarea class="form-control" id="note_content" name="note_content" rows="5"></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_material" class="btn btn-success">Add Material</button>
                        </form>
                    </div>
                </div>
                
                <div class="materials-list">
                    <h4>Existing Materials</h4>
                    
                    <?php if($materials->num_rows > 0): ?>
                        <?php while($material = $materials->fetch_assoc()): ?>
                            <div class="material-item">
                                <div class="row">
                                    <div class="col-md-9">
                                        <span class="material-type <?php echo $material['type']; ?>"><?php echo ucfirst($material['type']); ?></span>
                                        <span class="material-date">Added on <?php echo date('M d, Y', strtotime($material['upload_date'])); ?></span>
                                        <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                    </div>
                                    <div class="col-md-3 text-right">
                                        <div class="material-actions">
                                            <a href="<?php echo $material['file_path']; ?>" class="btn btn-info btn-sm" target="_blank">
                                                <?php echo $material['type'] == 'video' ? 'Watch Video' : 'View Note'; ?>
                                            </a>
                                            <form action="edit_course.php?id=<?php echo $course_id; ?>" method="post" style="display: inline;">
                                                <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                                                <button type="submit" name="delete_material" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this material?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No materials have been added to this course yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function showMaterialForm() {
            var materialType = document.getElementById('material_type').value;
            
            // Hide all forms first
            document.getElementById('video-form').style.display = 'none';
            document.getElementById('note-form').style.display = 'none';
            
            // Show the selected form
            if (materialType === 'video') {
                document.getElementById('video-form').style.display = 'block';
            } else if (materialType === 'note') {
                document.getElementById('note-form').style.display = 'block';
            }
        }
    </script>
</body>
</html>
