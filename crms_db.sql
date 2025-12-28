-- Create the Database
CREATE DATABASE IF NOT EXISTS crms_db1;
USE crms_db1;

-- 1. Users Table (Stores Admins, Officers, Newscasters)
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'officer', 'newscaster') DEFAULT 'officer',
    unit VARCHAR(50) DEFAULT 'General',
    status ENUM('active', 'pending', 'suspended') DEFAULT 'pending',
    last_login DATETIME,
    face_image LONGTEXT, -- For Biometric Features
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Criminals Table (The Core Record)
CREATE TABLE IF NOT EXISTS criminals (
    criminal_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    alias VARCHAR(100),
    age INT,
    gender VARCHAR(20) DEFAULT 'Male',
    height VARCHAR(20),
    weight VARCHAR(20),
    eye_color VARCHAR(20),
    hair_color VARCHAR(20),
    scars_marks TEXT,
    nationality VARCHAR(50),
    crime_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'Wanted', -- Wanted, In Custody, Solved/Closed
    risk_level VARCHAR(20) DEFAULT 'Low',
    gang_affiliation VARCHAR(100),
    fingerprint_id VARCHAR(50),
    bail_status VARCHAR(50) DEFAULT 'Not Set',
    evidence_list TEXT,
    description TEXT,
    resolution_notes TEXT, -- New: For "Case Solved" details
    resolution_date DATETIME, -- New: When was it solved?
    mugshot VARCHAR(255) DEFAULT 'default.png',
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 3. News Feed Table (For Public Portal)
CREATE TABLE IF NOT EXISTS news_feed (
    news_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    type VARCHAR(50), -- News, Alert, Notice
    media VARCHAR(255),
    author_id INT,
    is_public TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Complaints Table (Citizen Reporting)
CREATE TABLE IF NOT EXISTS complaints (
    comp_id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_name VARCHAR(100),
    contact_info VARCHAR(100),
    subject VARCHAR(255),
    details TEXT,
    status ENUM('Received', 'Investigating', 'Closed') DEFAULT 'Received',
    assigned_officer INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Internal Messages (Secure Comms)
CREATE TABLE IF NOT EXISTS messages (
    msg_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    recipient_role ENUM('all', 'admin', 'officer', 'newscaster'),
    message TEXT,
    priority ENUM('Normal', 'Urgent') DEFAULT 'Normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Audit Logs (Security Tracking)
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    ip_address VARCHAR(45),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin Account (Pass: admin123)
INSERT INTO users (full_name, username, password, role, status, unit) 
VALUES ('Chief Commander', 'admin', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'admin', 'active', 'Command');

-- Sample Data (Officers)
INSERT INTO users (full_name, username, password, role, status, unit) VALUES 
('Officer John Smith', 'john', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'officer', 'active', 'Patrol'),
('Det. Sarah Connor', 'sarah', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'officer', 'active', 'Cyber');

-- Sample Data (Criminals)
INSERT INTO criminals (full_name, alias, age, gender, crime_type, status, risk_level, description, added_by, created_at) VALUES 
('The Night Stalker', 'Shadow', 34, 'Male', 'Theft', 'Wanted', 'High', 'Breaking into jewelry stores at midnight.', 1, NOW()),
('Victor Vance', 'Viper', 29, 'Male', 'Drug Trafficking', 'In Custody', 'Extreme', 'Apprehended at docks.', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Maria Cruz', 'Widow', 41, 'Female', 'Homicide', 'Wanted', 'Extreme', 'Wanted for poisoning associates.', 1, DATE_SUB(NOW(), INTERVAL 5 DAY));