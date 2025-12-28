PROJECT DOCUMENTATION
National Crime Bureau (NCB) Integrated System
Public Intelligence Portal & Secure Record Management
Project Name: NCB Portal (CRMS v19.0) Type: Enterprise Web Application (Dual Interface) Date: December 2025 Department: Computer Science / Cyber Security

1. Executive Summary
The National Crime Bureau (NCB) Portal is a sophisticated, dual-layer web application designed to bridge the gap between law enforcement operations and public safety awareness. Unlike traditional record-keeping systems, the NCB Portal features two distinct interfaces:

Public Intelligence Dashboard (index.php): A transparency-focused portal for citizens to view real-time crime statistics, "Most Wanted" lists, and official news updates.

Secure Internal Network (login.php): An encrypted backend for Officers, Newscasters, and Commanders to manage case files, publish news, and track personnel.

The system introduces advanced features such as Biometric Data Fields, Math-Challenge Security, Audit Logging, and a News Desk module, ensuring a modern approach to digital policing.

2. System Architecture
The system operates on a Role-Based Access Control (RBAC) architecture with strict separation between public and private data.

2.1 Public Front-End (index.php)
Purpose: Information dissemination and community engagement.

Key Components:

Live Analytics: Visualizes data using Chart.js to show crime distribution (Wanted vs. Solved).

News Ticker: Displays breaking news and alerts fetched from the news_feed table.

Crime Mapping: Integration of Leaflet.js for geospatial visualization of crime hotspots.

Smart Image Loading: Uses DiceBear API to generate avatars for records missing mugshots, ensuring a consistent UI.

2.2 Secure Back-End (login.php)
Purpose: Operational management and data entry.

Key Components:

Authentication Gate: Requires Username, Password, and a Math Captcha.

Dashboard: Provides officers with immediate operational awareness (e.g., "Active Warrants", "Weather Widget").

News Desk: A dedicated CMS for Newscasters to write and publish articles.

3. User Roles & Capabilities
The system now supports three distinct internal roles defined in the users database table.

3.1 Administrator ("Chief Commander")
Access Level: Supreme.

Exclusive Rights:

Staff Management: Can approve pending accounts, suspend officers, and view active personnel units.

Full Oversight: Access to all records, news, and audit logs.

Dashboard: Sees the "Pending Approvals" counter to manage recruitment.

3.2 Police Officer
Access Level: Operational.

Capabilities:

Add/Edit Records: Can create detailed criminal profiles including physical attributes (scars, tattoos), biometric references (fingerprint ID), and evidence lists.

Search Database: Can query the criminals table to find suspects by name or alias.

Restrictions: Cannot access the "News Desk" or "Staff Management".

3.3 Newscaster (Media/Press)
Access Level: Information Only.

Capabilities:

News Desk: Access to a specialized form to publish Headlines, Alerts, or Public Notices.

Media Upload: Can upload news-related images to the public feed.

Security Restriction: Strictly blocked from viewing the Criminal Database. Attempts to access records result in an "ACCESS RESTRICTED" security warning.

4. Database Design & Schema
The database (crms_db1) has been expanded to support complex operations.

4.1 Core Tables
Table: users | Column | Type | Purpose | | :--- | :--- | :--- | | role | ENUM | Defines permission level ('admin', 'officer', 'newscaster'). | | status | ENUM | Controls login access ('active', 'pending', 'suspended'). | | face_image | LONGTEXT | Stores biometric facial data for future recognition features. |

Table: criminals (Enhanced) | Column | Type | Purpose | | :--- | :--- | :--- | | risk_level | VARCHAR | Color-coded threat assessment (Low/Medium/High). | | fingerprint_id | VARCHAR | Link to biometric fingerprint databases. | | gang_affiliation | VARCHAR | Tracks organized crime connections. |

Table: news_feed (New) | Column | Type | Purpose | | :--- | :--- | :--- | | type | VARCHAR | Categorizes posts (News, Alert, Notice) to determine UI color (Red for Alerts). | | is_public | TINYINT | Toggle visibility on the main portal. |

Table: audit_logs (Security) | Column | Type | Purpose | | :--- | :--- | :--- | | action | VARCHAR | Records what happened (e.g., "Login Successful", "Published News"). | | ip_address | VARCHAR | Tracks the IP origin of the user for forensic analysis. |

5. Key Functional Modules
5.1 The "News Desk" Module
Logic: A Content Management System (CMS) embedded within the secure portal.

Workflow:

Newscaster logs in.

Navigates to ?page=news_desk.

Selects type (e.g., "Emergency Alert").

Submits content.

The system saves it to news_feed, and index.php immediately renders it on the Public Portal under the "Breaking News" ticker.

5.2 The "Pending Approval" Workflow
Logic: Security by default.

Process:

User registers via login.php?page=register.

Database inserts row with status = 'pending'.

User sees "Application Sent" message.

Admin logs in, sees "Yellow Card" notification for pending users.

Admin clicks "Approve", updating status to active.

6. Security Architecture
The system implements defense-in-depth strategies.

6.1 Anti-Bot Verification (Math Captcha)
Mechanism: The login session generates two random numbers ($_SESSION['c_n1'], $_SESSION['c_n2']).

Validation: The user must calculate the sum. If (int)$_POST['captcha'] does not match the session sum, the login is rejected with "Security Check Failed".

6.2 Session Hijacking Protection & IP Logging
Mechanism: session_regenerate_id(true) is called upon successful login to prevent session fixation attacks.

Audit Trail: Every critical action (Login, Logout, Publish News) triggers the logAction() function, which records the user ID, Action, and IP Address into the audit_logs table.

6.3 Input Sanitization
Defense: All inputs are processed through a custom sanitize() function which applies htmlspecialchars() and $conn->real_escape_string(). This neutralizes Cross-Site Scripting (XSS) and SQL Injection attacks.

7. Technical Stack
Frontend Framework: Tailwind CSS (configured with darkMode: 'class' for night-ops aesthetic).

Visualization: Chart.js (Donut charts for case status), Leaflet.js (Map interface).

Backend: PHP 8.0+ (Vanilla, Session-based).

Database: MySQL / MariaDB (Relational).

Assets: FontAwesome v6.4 (Icons), Unsplash API (Backgrounds), DiceBear API (Dynamic Avatars).

8. Conclusion
The NCB Portal v19.0 represents a significant leap forward from standard CRUD applications. By integrating a public-facing news engine with a high-security internal record system, it serves the dual purpose of keeping the public informed while giving officers the tools they need to solve crimes. The addition of the Newscaster role and Audit Logging ensures the system is enterprise-ready and compliant with modern security standards.

9. References
System Files: index.php (Public Portal), login.php (Secure Portal), crms_db.sql (Database Schema).

Libraries:

Tailwind CSS: https://tailwindcss.com/

Chart.js: https://www.chartjs.org/

Leaflet Maps: https://leafletjs.com/

DiceBear Avatars: https://dicebear.com/

Security Standards: OWASP Session Management Guidelines.
