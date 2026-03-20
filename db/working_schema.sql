START TRANSACTION;

CREATE DATABASE IF NOT EXISTS working_schema;
USE working_schema;

-- Drop tables in reverse order (to respect foreign key constraints)
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS feedback_reviews;
DROP TABLE IF EXISTS review_requests;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS users;

-- USERS
CREATE TABLE users (
    user_id    INT PRIMARY KEY AUTO_INCREMENT,
    school_id  VARCHAR(20)  NOT NULL,
    first_name VARCHAR(50)  NOT NULL,
    last_name  VARCHAR(50)  NOT NULL,
    email      VARCHAR(255) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    department VARCHAR(50)  NOT NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (school_id, first_name, last_name, email, password, role, department, status) VALUES
('ADM-001',    'Admin',       'NBSC',    '20231671@nbsc.edu.ph',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   'Administration', 'active'),
('S-0008',     'Erick James', 'Rubin',   'r.villanueva@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',   'SAS',            'active'),
('2024-00102', 'Rhics',       'Geonzon', '20231317@nbsc.edu.ph',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'IT',             'active'),
('2023-00045', 'Troy',        'Rojo',    't.rojo@nbsc.edu.ph',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Business',       'inactive'),
('2023-00121', 'Francis',     'Idul',    '20231685@nbsc.edu.ph',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'IT',             'active');

-- FEEDBACK
CREATE TABLE feedback (
    feedback_id  INT PRIMARY KEY AUTO_INCREMENT,
    category     ENUM('general','academic','facilities','services','faculty','administration','suggestion','complaint','other') DEFAULT 'general',
    priority     ENUM('Low','Medium','High','Urgent') DEFAULT 'Low',
    message_enc  BLOB        NOT NULL COMMENT 'AES-256-CBC encrypted message',
    message_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for integrity check',
    status       ENUM('pending','reviewed','resolved') DEFAULT 'pending',
    submitted_by INT  NULL COMMENT 'student user_id who submitted',
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL
);

INSERT INTO feedback (category, priority, message_enc, message_hash, status) VALUES
('academic',       'High',   AES_ENCRYPT('The grading system for our major subjects lacks transparency. A clear published rubric would greatly help.', 'nbsc_secret_key_2024'), SHA2('The grading system for our major subjects lacks transparency. A clear published rubric would greatly help.', 256), 'pending'),
('services',       'Urgent', AES_ENCRYPT('The restrooms near the Engineering building have been broken for over two weeks.', 'nbsc_secret_key_2024'), SHA2('The restrooms near the Engineering building have been broken for over two weeks.', 256), 'reviewed'),
('facilities',     'High',   AES_ENCRYPT('One of our professors consistently starts class 20-30 minutes late without covering required topics.', 'nbsc_secret_key_2024'), SHA2('One of our professors consistently starts class 20-30 minutes late without covering required topics.', 256), 'pending'),
('administration', 'Medium', AES_ENCRYPT('The registration portal was extremely slow and kept timing out during enrollment.', 'nbsc_secret_key_2024'), SHA2('The registration portal was extremely slow and kept timing out during enrollment.', 256), 'resolved');

-- REVIEW REQUESTS
CREATE TABLE review_requests (
    request_id   INT PRIMARY KEY AUTO_INCREMENT,
    feedback_id  INT  NOT NULL,
    requested_by INT  NOT NULL,
    purpose      TEXT NOT NULL,
    status       ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by  INT  NULL,
    admin_notes  TEXT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at  DATETIME NULL,
    FOREIGN KEY (feedback_id)  REFERENCES feedback(feedback_id),
    FOREIGN KEY (requested_by) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by)  REFERENCES users(user_id)
);

-- FEEDBACK REVIEWS
CREATE TABLE feedback_reviews (
    review_id      INT PRIMARY KEY AUTO_INCREMENT,
    feedback_id    INT  NOT NULL,
    reviewed_by    INT  NOT NULL,
    request_id     INT  NOT NULL,
    review_notes   TEXT,
    status_changed ENUM('pending','reviewed','resolved'),
    reviewed_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id),
    FOREIGN KEY (request_id)  REFERENCES review_requests(request_id)
);

-- ACTIVITY LOGS
CREATE TABLE activity_logs (
    log_id     INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT  NULL,
    action     VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'LOGIN',          'Admin logged into the system',                     '127.0.0.1'),
(2, 'REVIEW_REQUEST', 'Manager submitted review request for feedback #1', '127.0.0.1');

-- NOTIFICATIONS
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id         INT  NOT NULL,
    title           VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

INSERT INTO notifications (user_id, title, message, is_read) VALUES
(1, 'New Review Request', 'Manager Rosa submitted a review request for Feedback #1.', 0),
(2, 'Request Approved',   'Your review request for Feedback #1 has been approved.', 0);

COMMIT;