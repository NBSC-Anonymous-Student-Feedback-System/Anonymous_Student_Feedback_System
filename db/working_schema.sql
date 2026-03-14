START TRANSACTION;

CREATE DATABASE IF NOT EXISTS working_schema;
USE working_schema;

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,  
    school_id VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    department VARCHAR(50) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP);

-- Sample Data for Users
INSERT INTO users (school_id, first_name, last_name, email, password, role, department, status) VALUES
('ADM-001', 'Admin', 'NBSC', '20231671@nbsc.edu.ph', ' $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration', 'active'), 
('S-0008', 'Rosa', 'Villanueva', 'r.villanueva@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'staff', 'SAS', 'active'),
('2024-00102','Rhics', 'Geonzon', '20231317@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'student', 'IT', 'active'),
('2023-00045','Troy', 'Rojo', 't.rojo@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'student', 'Business', 'inactive');
('2023-00121','Francis', 'Idul', '20231685@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'student', 'IT', 'active');


-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR ACTIVITY LOGS
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'LOGIN', 'Admin logged into the system', '192.168.1.10'),
(2, 'REVIEW_FEEDBACK', 'Admin reviewed feedback NBSC-D3E4F and added notes', '192.168.1.10'),
(3, 'STATUS_CHANGED', 'Feedback NBSC-J7K8L marked as resolved', '192.168.1.10'),
(4, 'USER_CREATED', 'Admin created user account for Maria Santos', '192.168.1.10');

CREATE TABLE feedback (
    feedback_id INT PRIMARY KEY AUTO_INCREMENT,
    category ENUM('general', 'academic', 'facilities', 'services', 'faculty', 'administration', 'suggestion', 'complaint', 'other') DEFAULT 'general',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Low',
    message VARCHAR(200) NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    submitted_at   DATETIME DEFAULT CURRENT_TIMESTAMP
); 

-- SAMPLE DATA FOR FEEDBACK
INSERT INTO feedback (category, priority, message, status) VALUES
('academic', 'High',   'The grading system for our major subjects lacks transparency. A clear, published rubric would greatly help.', 'pending'),
('services', 'Urgent', 'The restrooms near the Engineering building have been broken for over two weeks.', 'reviewed'),
('facilities', 'High',   'One of our professors consistently starts class 20-30 minutes late without covering required topics.', 'pending'),
('administration', 'Medium', 'The registration portal was extremely slow and kept timing out during enrollment.', 'resolved');

-- Comments Table
CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    encrypted_user_id TEXT NOT NULL,
    anonymous_id VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('active', 'deleted', 'flagged') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id)
);

-- SAMPLE DATA FOR COMMENTS
INSERT INTO comments (feedback_id, encrypted_user_id, anonymous_id, content, status, created_at) VALUES
(1, 'encrypted_3_xyz789def', 'anon_c1d2e3f4g5h6', 'I completely agree! We need better transparency in grading. Students should know exactly how they are being evaluated.', 'active', '2024-01-15 10:30:00'),
(1, 'encrypted_2_abc123ghi', 'anon_i7j8k9l0m1n2', 'Has anyone tried talking to the department head about this? Maybe we should organize a petition.', 'active', '2024-01-15 14:45:00'),
(2, 'encrypted_4_jkl456mno', 'anon_o3p4q5r6s7t8', 'This has been an issue for months! Thank you for finally reporting it. The facilities team needs to prioritize this.', 'active', '2024-01-16 09:20:00'),
(2, 'encrypted_3_pqr789stu', 'anon_u9v0w1x2y3z4', 'Update: I just checked and the restrooms are still not fixed. This is really unacceptable for a university facility.', 'active', '2024-01-17 16:15:00');


-- User warnings table (track violations)
CREATE TABLE user_warnings (
    user_warnings_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR USER WARNINGS
INSERT INTO user_warnings (user_id, reason, content) VALUES
(3, 'Offensive language', 'Feedback contained inappropriate words directed at a faculty member.'),
(3, 'Spam submission', 'User repeatedly submitted the same feedback message multiple times.'),
(4, 'Harassment', 'Feedback included personal attacks toward another student.'),
(4, 'Irrelevant feedback', 'Submitted feedback not related to NBSC services or academics.');

CREATE TABLE feedback_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT,
    reviewed_by INT NOT NULL,
    review_notes TEXT,
    status_changed ENUM('pending', 'reviewed', 'resolved'),
    reviewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR FEEDBACK REVIEWS
INSERT INTO feedback_reviews (feedback_id, reviewed_by, review_notes, status_changed) VALUES
(1, 1, 'Forwarded to the facilities management team. Awaiting repair schedule.', 'reviewed'),
(2, 1, 'IT department addressed the server load issue. New infrastructure deployed.', 'resolved'),
(3, 1, 'Safety officer notified. Temporary mats installed.', 'reviewed'),
(4, 1, 'Electrical team replaced bulbs and repaired wiring. All lights operational.', 'resolved');

-- Notifications Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR NOTIFICATIONS
INSERT INTO notifications (user_id, title, message, is_read) VALUES
(1, 'New Urgent Feedback', 'A new Urgent feedback was submitted under Safety.', 0),
(2, 'New Feedback Submitted', 'A new High priority feedback submitted under Academic.', 0),
(3, 'Feedback Resolved', 'Feedback NBSC-J7K8L has been marked as resolved.', 1);


-- Query to Retrieve Feedback with Admin Review Notes
SELECT
    f.feedback_id,
    f.category,
    f.priority,
    f.status,
    r.review_notes AS admin_notes,
    CONCAT(u.first_name, ' ', u.last_name) AS reviewed_by,
    r.reviewed_at
FROM feedback f
INNER JOIN feedback_reviews r ON f.feedback_id = r.feedback_id
INNER JOIN users u ON r.reviewed_by = u.user_id
ORDER BY r.reviewed_at DESC;

-- Query to Retrieve Activity Logs with User Information
SELECT
    a.log_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    u.role,
    a.action,
    a.description,
    a.ip_address,
    a.created_at
FROM activity_logs a
INNER JOIN users u 
ON a.user_id = u.user_id
ORDER BY a.created_at DESC;

-- Query to Retrieve Comments with Feedback Information
SELECT
    c.comment_id,
    f.category,
    f.message AS feedback_message,
    c.anonymous_id,
    c.content AS comment,
    c.status,
    c.created_at
FROM comments c
INNER JOIN feedback f 
ON c.feedback_id = f.feedback_id
ORDER BY c.created_at DESC;

-- Query to Retrieve User Warnings with User Information
SELECT
    w.user_warnings_id,
    CONCAT(u.first_name, ' ', u.last_name) AS warned_user,
    u.role,
    w.reason,
    w.content,
    w.created_at
FROM user_warnings w
INNER JOIN users u
ON w.user_id = u.user_id
ORDER BY w.created_at DESC;

-- Query to Retrieve All Users
SELECT
    user_id,
    school_id,
    CONCAT(first_name, ' ', last_name) AS full_name,
    email,
    role,
    department,
    status,
    created_at
FROM users
ORDER BY role, last_name ASC;

-- Query to Retrieve Feedback with Admin Review Notes and Reviewer Information
SELECT
    f.feedback_id,
    f.category,
    f.priority,
    f.message,
    f.status,
    CONCAT(u.first_name,' ',u.last_name) AS reviewed_by,
    r.review_notes,
    r.reviewed_at
FROM feedback f
INNER JOIN feedback_reviews r 
ON f.feedback_id = r.feedback_id
INNER JOIN users u 
ON r.reviewed_by = u.user_id;

COMMIT;