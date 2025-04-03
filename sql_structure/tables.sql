-- Create LMS database
CREATE DATABASE IF NOT EXISTS LMS;
USE LMS;

-- Create Users table
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL CHECK (email LIKE '%@%.%'),
    password VARCHAR(255) NOT NULL CHECK (LENGTH(password) >= 8),
    role VARCHAR(20) NOT NULL CHECK (role IN ('Admin', 'Instructor', 'Student')),
    profile_image VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	mfa_secret VARCHAR(255) NULL,
    last_login TIMESTAMP NULL
);

-- Create Courses table
CREATE TABLE Courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(255),
    skill_level TEXT NOT NULL CHECK (skill_level IN ('Beginner', 'Intermediate', 'Advanced')),
    prereq_course_id INT NULL,
    start_date DATE,
    end_date DATE,
    last_accessed DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (prereq_course_id) REFERENCES Courses(course_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS CourseMaterials (
    material_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create Assignments table
CREATE TABLE Assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    topic VARCHAR(255),
    grading_criteria TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    course_completed_date DATE NULL,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create Quizzes table
CREATE TABLE Quizzes (
    quiz_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    is_automated_grading BOOLEAN DEFAULT TRUE,
    topic VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create Enrollments table
CREATE TABLE Enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    status TEXT NOT NULL CHECK (status IN ('Enrolled', 'Completed', 'Dropped')),
    FOREIGN KEY (student_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create Submissions table
CREATE TABLE Submissions (
    submission_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    assignment_id INT NOT NULL,
    date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    submission_url VARCHAR(500) NOT NULL, -- Link to uploaded file
    grade DECIMAL(5,2) NULL, -- Null until graded
    ai_feedback TEXT NULL, -- AI-generated feedback
    course_completed_date DATE NULL,
    FOREIGN KEY (student_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES Assignments(assignment_id) ON DELETE CASCADE
);

-- Create Attendance table
CREATE TABLE Attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('Present', 'Absent', 'Late')),
    FOREIGN KEY (student_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create Notifications table
CREATE TABLE Notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('Grade Update', 'Deadline Reminder', 'Course Update')),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Create Questions table
CREATE TABLE Questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    text VARCHAR(255),
    question_format TEXT NOT NULL CHECK (question_format IN ('MCQ', 'Short Answer', 'Standardized')),
    answer VARCHAR(30),
    FOREIGN KEY (quiz_id) REFERENCES Quizzes(quiz_id) ON DELETE CASCADE
);

-- Create StudentFeedback table
CREATE TABLE StudentFeedback(
    feedback_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    topics_covered TEXT NOT NULL,
    resources_recommended TEXT NOT NULL,
    ai_feedback TEXT NOT NULL,
    date_feedback TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);

-- Create System User for LMS
CREATE USER 'lms_system'@'localhost' IDENTIFIED BY '7F2U5oH#';
GRANT ALL PRIVILEGES ON LMS.* TO 'lms_system'@'localhost';
FLUSH PRIVILEGES;

-- Create table to store student quiz attempts and results
CREATE TABLE IF NOT EXISTS StudentQuizzes (
    student_quiz_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES Quizzes(quiz_id) ON DELETE CASCADE
);

-- Create table to store individual student answers to quiz questions
CREATE TABLE IF NOT EXISTS StudentQuizAnswers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    student_quiz_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer VARCHAR(255) NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_quiz_id) REFERENCES StudentQuizzes(student_quiz_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES Questions(question_id) ON DELETE CASCADE
);

-- Modify the Questions table to only support objective type questions
-- Note: We're keeping compatibility with your existing structure 
-- but adding an enumeration constraint to question_format
ALTER TABLE Questions 
MODIFY COLUMN question_format ENUM('MCQ', 'True/False') NOT NULL;

ALTER TABLE Quizzes 
ADD COLUMN is_published BOOLEAN DEFAULT 0;

ALTER TABLE Questions 
MODIFY COLUMN question_format ENUM('MCQ', 'True/False', 'Essay') NOT NULL;

-- Add a column for essay answers which can be longer than the regular answer field
ALTER TABLE Questions
ADD COLUMN essay_answer_template TEXT NULL;

-- Update the StudentQuizAnswers table to accommodate essay answers
ALTER TABLE StudentQuizAnswers
MODIFY COLUMN selected_answer TEXT NOT NULL;

-- Add a column for manual grading of essay questions
ALTER TABLE StudentQuizAnswers
ADD COLUMN instructor_feedback TEXT NULL,
ADD COLUMN is_graded BOOLEAN DEFAULT FALSE;

-- Update Quizzes table to indicate if manual grading is required
ALTER TABLE Quizzes
ADD COLUMN requires_manual_grading BOOLEAN DEFAULT FALSE;

-- Create a trigger to update the requires_manual_grading flag when essay questions are added
DELIMITER //
CREATE TRIGGER update_quiz_grading_flag AFTER INSERT ON Questions
FOR EACH ROW
BEGIN
    IF NEW.question_format = 'Essay' THEN
        UPDATE Quizzes SET requires_manual_grading = TRUE WHERE quiz_id = NEW.quiz_id;
    END IF;
END //
DELIMITER ;

ALTER TABLE Questions 
MODIFY COLUMN question_format ENUM('MCQ', 'True/False', 'Essay') NOT NULL;

-- This will show the constraint names
SHOW CREATE TABLE Questions;

-- Then drop the conflicting constraint (replace 'constraint_name' with the actual name)
ALTER TABLE Questions DROP CONSTRAINT questions_chk_1;

-- And if needed, add a new constraint that matches your ENUM
ALTER TABLE Questions 
ADD CONSTRAINT questions_format_check 
CHECK (question_format IN ('MCQ', 'True/False', 'Essay'));
ALTER TABLE StudentQuizAnswers 
MODIFY COLUMN is_correct DECIMAL(5,2) DEFAULT 0.00;
ALTER TABLE StudentQuizzes 
MODIFY COLUMN score DECIMAL(5,2) DEFAULT 0.00;
-- Add a flag column to track if feedback needs regeneration
ALTER TABLE StudentFeedback
ADD COLUMN needs_regeneration BOOLEAN DEFAULT FALSE;

ALTER TABLE Courses 
ADD COLUMN instructor_id INT NULL;

-- Step 2: Update existing records
-- Assign a default instructor ID to existing courses
-- Example: Assign to the first instructor in the system
UPDATE Courses SET instructor_id = (
    SELECT user_id FROM Users 
    WHERE role = 'Instructor' 
    LIMIT 1
);

-- Step 3: Once data is populated, make the column NOT NULL
ALTER TABLE Courses 
MODIFY COLUMN instructor_id INT NOT NULL;

-- Step 4: Now add the foreign key constraint
ALTER TABLE Courses
ADD CONSTRAINT fk_course_instructor
FOREIGN KEY (instructor_id) REFERENCES Users(user_id);

-- Step 5: Create an index for better performance
CREATE INDEX idx_instructor_courses ON Courses(instructor_id);

-- Run this SQL to alter the StudentFeedback table
ALTER TABLE StudentFeedback
MODIFY COLUMN course_id INT NULL;
