CREATE DATABASE IF NOT EXISTS LibLog;
USE LibLog;

CREATE TABLE IF NOT EXISTS students (
    student_id VARCHAR(20) PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100),
    course VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS computers (
    pc_id INT AUTO_INCREMENT PRIMARY KEY,
    pc_number VARCHAR(10) UNIQUE,
    status VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS usage_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20),
    pc_id INT,
    date DATE,
    time_in TIME,
    time_out TIME,
    time_in_dt DATETIME NULL,
    time_out_dt DATETIME NULL,
    duration_minutes INT NULL,
    purpose VARCHAR(50) NULL,
    ended_by ENUM('student','admin','auto') NULL,
    INDEX idx_usage_sessions_student_id (student_id),
    INDEX idx_usage_sessions_pc_id (pc_id),
    INDEX idx_usage_sessions_date (date),
    INDEX idx_usage_sessions_time_out_dt (time_out_dt),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (pc_id) REFERENCES computers(pc_id)
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('library_name', 'Library'),
    ('opening_time', '08:00'),
    ('closing_time', '20:00'),
    ('max_session_minutes', '180'),
    ('logs_per_page', '25');
CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100),
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255)
);

INSERT IGNORE INTO computers (pc_number, status)
VALUES
    ('PC-1', 'Available'),
    ('PC-2', 'Available'),
    ('PC-3', 'Available'),
    ('PC-4', 'Available'),
    ('PC-5', 'Available');

INSERT IGNORE INTO students (student_id, first_name, last_name, email, course)
VALUES ('2410679-1', 'John Ivan', 'Denden', 'denjhnivn@gmail.com', 'BSInfoTech');

-- This bcrypt hash is for the password: password.
-- Re-running the script also updates the existing admin account.
INSERT INTO staff (first_name, last_name, email, username, password)
VALUES ('Jefferson', 'Itaok', 'jeff123@gmail.com', 'admin', '$2y$12$UsZ8N47iq/bfL1zzwmIJAueJcb.jsH0XTPVMYUpCT7ZvDG.v.B0Ge')
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    email = VALUES(email),
    password = VALUES(password);
