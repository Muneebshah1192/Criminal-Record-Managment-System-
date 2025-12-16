-- Create the Database
CREATE DATABASE IF NOT EXISTS crms_db;
USE crms_db;

-- 1. Users Table (Stores Admins and Officers)
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'officer') DEFAULT 'officer',
    status ENUM('active', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Criminals Table (Enhanced with Gender and Status)
CREATE TABLE IF NOT EXISTS criminals (
    criminal_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(20) DEFAULT 'Male', -- New Field
    crime_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'Wanted', -- New Field: Wanted, In Custody, Released
    description TEXT,
    mugshot VARCHAR(255) DEFAULT 'default.png',
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 3. Default Admin Account 
-- Login: admin / Password: admin123
-- Note: The hash below is specifically for 'admin123'
INSERT INTO users (full_name, username, password, role, status) 
VALUES ('Chief Commander', 'admin', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'admin', 'active');

-- 4. Sample Officers (For testing "Manage Officers")
-- Login: john / Password: admin123
INSERT INTO users (full_name, username, password, role, status) VALUES 
('Officer John Smith', 'john', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'officer', 'active'),
('Officer Sarah Connor', 'sarah', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'officer', 'active'),
('Rookie Mike', 'mike', '$2y$10$SfhYIDtn.iSyCo8Rlc31..Kclk9RHH.pwI91.w4q.qXp.j/0.m.C6', 'officer', 'pending');

-- 5. Sample Criminal Data (To populate Dashboard Charts)
INSERT INTO criminals (full_name, age, gender, crime_type, status, description, added_by, created_at) VALUES 
('The Night Stalker', 34, 'Male', 'Theft', 'Wanted', 'Suspect is known for breaking into jewelry stores at midnight. Highly elusive.', 1, NOW()),
('Victor "Viper" Vance', 29, 'Male', 'Drug Trafficking', 'In Custody', 'Apprehended at the docks with 50kg of illicit substances.', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Maria "Black Widow" Cruz', 41, 'Female', 'Homicide', 'Wanted', 'Wanted for the poisoning of three associates. Extremely dangerous.', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('Cyber Ghost', 22, 'Other', 'Cyber Crime', 'Wanted', 'Hacked into the city municipal database. Identity unknown, goes by alias.', 2, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('Tommy Vercetti', 35, 'Male', 'Assault', 'Released', 'Served time for bar brawl. Currently on parole.', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
('Eddie Low', 28, 'Male', 'Theft', 'In Custody', 'Caught stealing car radios in downtown district.', 2, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Frank Tenpenny', 50, 'Male', 'Fraud', 'Wanted', 'Corrupt activities and money laundering suspects.', 1, DATE_SUB(NOW(), INTERVAL 15 DAY));