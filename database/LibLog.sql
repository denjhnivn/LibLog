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
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (pc_id) REFERENCES computers(pc_id)
);

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
