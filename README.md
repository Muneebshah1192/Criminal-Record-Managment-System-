
PROJECT DOCUMENTATION
Criminal Record Management System (CRMS)
Version: 1.0 (Beta) Date: December 28, 2025 Department: Computer Science / Information Technology

1. Executive Summary
The Criminal Record Management System (CRMS) is a web-based enterprise application designed to modernize and digitize the record-keeping operations of law enforcement agencies. Traditional police record-keeping often relies on physical files, which are prone to damage, loss, and slow retrieval speeds. CRMS addresses these challenges by providing a centralized, secure digital database where authorized personnel can manage criminal profiles efficiently.

The system currently supports two primary roles: Administrators (Chief Commanders) and Officers. It features a robust security layer including role-based access control (RBAC), password encryption, and a "Gatekeeper" approval system for new accounts. Future iterations of the project will expand to include a public-facing News Portal and a Newscaster role to facilitate community safety alerts.

2. Mission & Vision
2.1 Mission Statement
"To empower law enforcement with a secure, reliable, and efficient digital infrastructure that streamlines the documentation of criminal activities, ensures the integrity of sensitive data, and fosters transparency between the police force and the community."

2.2 Project Vision
To become the standard for digital policing, creating a unified platform where internal investigations and public safety announcements coexist seamlessly.

3. System Features & Functional Modules
The application is divided into three core modules: Authentication, Administration, and Operations.

3.1 Authentication & Security Module
Secure Login Portal: A unified entry point that authenticates users based on their username and password.

Registration with Approval Gate: New users can register for an account, but they are assigned a pending status by default. They cannot access the system until an Admin explicitly approves them.

Session Management: The system uses PHP sessions (session_start()) to maintain user state. Unauthorized attempts to access internal pages (like the dashboard) redirect the user back to the login screen.

Logout Protocol: Securely destroys the user session to prevent unauthorized access on shared devices.

3.2 Administrator Module ("Chief Commander")
The Administrator holds the highest level of authority in the system.

Dashboard Analytics: Provides a real-time overview of the department's status, including:

Total Criminal Records.

Number of Active Officers.

Pending Approvals Alert: A specific counter visible only to Admins.

Officer Management: A dedicated interface (?page=officers) to view all registered users who are currently pending.

One-Click Approval: Admins can activate an officer's account by clicking "Approve," which updates the user's status in the database from pending to active.

3.3 Police Officer Module
This module is designed for daily operational use by field officers.

Add Criminal Profile: A comprehensive form allows officers to input:

Personal Details: Name, Age.

Crime Classification: Dropdown selection (Theft, Assault, Fraud, Homicide, etc.).

Case Description: Detailed text area for case notes.

Evidence Management: Functionality to upload and store digital mugshots of the suspect.

Search & Retrieval: A robust search engine allows officers to filter the criminal database by Name or Crime Type using SQL pattern matching.

Visual Database: The record view displays the suspect's mugshot alongside their crime details for quick visual identification.

4. Technical Architecture
4.1 Frontend (User Interface)
HTML5: Provides the semantic structure of the application.

Tailwind CSS: A utility-first CSS framework used for styling.

Glassmorphism: The login interface features a modern, translucent design (glass-effect class) for a professional aesthetic.

Responsiveness: The layout adapts to different screen sizes, with a collapsible sidebar and grid-based dashboard.

FontAwesome (v6.4.0): Integration of vector icons (Handcuffs, Shields, User Gear) to enhance User Experience (UX).

4.2 Backend (Server Logic)
Language: PHP (Vanilla).

Core Logic:

Routing: A single-page application structure where the ?page= parameter determines which content to load.

File Handling: Utilizes move_uploaded_file() to securely transfer images from the user's computer to the server's uploads/ directory.

4.3 Database (Storage)
Engine: MySQL / MariaDB.

Structure: Relational Database with normalized tables to ensure data consistency and reduce redundancy.

5. Database Design & Schema
5.1 Entity Relationship Diagram (ERD)
The database consists of two primary tables linked by a Foreign Key relationship.

[INSERT SCREENSHOT OF ERD OR PHPMYADMIN HERE]

5.2 Table Definitions
Table 1: users Handles authentication and authorization.

user_id (INT, Primary Key): Unique identifier.

username (VARCHAR): Unique login credential.

password (VARCHAR): Hashed security string.

role (ENUM): 'admin' or 'officer'.

status (ENUM): 'active' or 'pending'. Critical for the approval system.

Table 2: criminals Stores criminal case data.

criminal_id (INT, Primary Key): Unique case number.

full_name (VARCHAR): Name of the suspect.

crime_type (VARCHAR): Category of offense.

mugshot (VARCHAR): File path to the stored image.

added_by (INT, Foreign Key): Links to users.user_id. This creates an Audit Trail, allowing Admins to track which officer filed a specific record.

6. Security Architecture
Security is a cornerstone of the CRMS project. The following measures have been implemented to protect sensitive police data.

6.1 Password Hashing (Bcrypt)
The system does not store passwords in plain text.

Implementation: During registration, password_hash() converts the password into a secure hash. During login, password_verify() checks the input against this hash. This ensures that even if the database is stolen, user passwords remain secure.

6.2 SQL Injection Prevention
Threat: Attackers inserting malicious code into input fields.

Defense: The system applies $conn->real_escape_string() to all user inputs ($_POST data) before interacting with the database. This neutralizes special characters that could alter SQL queries.

6.3 The "Pending" Trap (Access Control)
Feature: Valid credentials alone are insufficient for access.

Logic: The login script checks if ($row['status'] == 'active'). If a user is pending, they are denied entry with an error message ("Account pending Admin approval"). This prevents unauthorized access immediately after registration.

6.4 File Upload Security
Renaming Strategy: Uploaded images are renamed using the current timestamp (time()) to prevent file overwriting and obscure the original filename.

7. Future Work: The News & Public Information Module
To fully realize the project vision, the following features are planned for Version 2.0.

7.1 The Newscaster Role
A new user role (role = 'newscaster') will be introduced.

Responsibilities: Newscasters will access a restricted Content Management System (CMS) to publish news. They will have zero access to the criminal records database.

7.2 Public Homepage (home.php)
A publicly accessible landing page (no login required).

Internal News Feed: Displays articles and safety alerts posted by the Newscasters.

External News API: Integration with a third-party API (e.g., NewsAPI.org) to fetch and display a live ticker of global "Crime & Safety" news.

Most Wanted List: A filtered, read-only view of the criminals table showing only "High Severity" fugitives to enlist public help.

8. Conclusion
The Criminal Record Management System (v1.0) successfully demonstrates a secure, functional backend for police administration. By leveraging PHP and MySQL, the system allows for the seamless creation, retrieval, and management of criminal data. The implementation of strict role-based access control and the "Admin Approval" workflow ensures that the system maintains a high standard of security. The planned addition of the News Module will further enhance the system's value by integrating community engagement.

9. References
Codebase:

index.php: Main application logic (Authentication, Dashboard, CRUD operations).

database.sql: Database schema definition.

Documentation & Manuals:

PHP Manual: Session Handling, Password Hashing (password_hash), MySQLi Extensions. https://www.php.net/docs.php

MySQL Reference: AUTO_INCREMENT, Foreign Keys, ENUM Data Types. https://dev.mysql.com/doc/

Frontend Libraries:

Tailwind CSS: Utility-first CSS framework for design and layout. https://tailwindcss.com/

FontAwesome: Icon toolkit (v6.4.0). https://fontawesome.com/

Security Standards:

OWASP: Guidelines on SQL Injection Prevention and Password Storage. https://owasp.org/
