# NBSC Anonymous Student Feedback System

A web-based anonymous feedback system for Northern Bukidnon State College (NBSC) built with PHP and MySQL. Students can submit encrypted feedback anonymously, while managers request access through an admin-controlled review system.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Database Setup](#database-setup)
- [Installation](#installation)
- [Default Accounts](#default-accounts)
- [Changelog](#changelog)

---

## Features

- **Anonymous Feedback Submission** — Student identity is never linked to feedback content
- **AES-256-CBC Encryption** — All feedback messages are encrypted at rest
- **SHA-256 Integrity Hashing** — Tamper detection on every feedback message
- **Role-Based Access Control** — Three roles: `student`, `manager`, `admin`
- **Review Request Workflow** — Managers must submit a purpose-stated request; admin approves/rejects before any feedback content is revealed
- **Off-Hours Restriction** — Feedback reviews are only accessible every day, 8:00 AM – 5:00 PM (Philippine Time)
- **Activity Logging** — All logins, review requests, and feedback access are logged with IP address
- **Notifications** — In-app notifications for review request approvals/rejections

---

## Tech Stack

- **Backend:** PHP 8.x
- **Database:** MySQL 8.x
- **Frontend:** Vanilla HTML/CSS/JS (custom stylesheet)
- **Encryption:** OpenSSL AES-256-CBC (PHP) + MySQL AES_ENCRYPT for seed data

---

## Project Structure

```
NBSC-s-Anonymous-Student-Feedback-System/
<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
requireRole('staff');
header("Location: " . BASE_URL . "/app/manager/dashboard.php");
exit;
```

**README structure to update** — your actual structure differs from the README in these ways:

- `auth/` has `login.php` + `logout.php` (not 4 separate files)
- No `includes/` folder (header/sidebar/footer no longer used)
- `db/` folder instead of root-level `working_schema.sql`
- `media/` folder for `logoweb.svg`
- Root `index.php` exists

Update your `README.md` to reflect the actual structure:
```
NBSC-Anonymous-Student-Feedback-System/
├── app/
│   ├── admin/
│   │   ├── activity-logs.php
│   │   ├── dashboard.php
│   │   ├── feedback.php
│   │   ├── notifications.php
│   │   ├── review-requests.php
│   │   └── users.php
│   ├── auth/
│   │   ├── login.php
│   │   └── logout.php
│   ├── manager/
│   │   ├── dashboard.php
│   │   ├── feedback.php
│   │   ├── notifications.php
│   │   └── view-feedback.php
│   └── user/
│       └── index.php
├── assets/
│   └── css/
│       └── style.css
├── config/
│   ├── config.php
│   └── function.php
├── db/
│   └── working_schema.sql
├── media/
│   └── logoweb.svg
└── index.php
```

---

## Database Setup

1. Open **phpMyAdmin** or your preferred MySQL client
2. Import the provided `working_schema.sql` file
3. The script will:
   - Create the `working_schema` database
   - Create all required tables
   - Insert default users and sample feedback

### Tables

| Table | Description |
|---|---|
| `users` | All system users (students, managers, admins) |
| `feedback` | Encrypted feedback submissions |
| `review_requests` | Manager requests to access specific feedback |
| `feedback_reviews` | Log of manager reviews with notes |
| `activity_logs` | Full audit trail of system actions |
| `notifications` | In-app notification records |

> **Note:** The `comments`, `user_warnings`, and feedback `status` column have been removed from the system as they are no longer in use.

---

## Installation

1. Clone or download the repository into your web server root:
   ```
   htdocs/NBSC-s-Anonymous-Student-Feedback-System/
   ```

2. Import `working_schema.sql` into MySQL

3. Update `config/config.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'working_schema');
   define('BASE_URL', 'http://localhost/Anonymous_Student_Feedback_System');
   ```

4. Make sure the `ENCRYPT_KEY` in `config/function.php` matches the key used in the SQL seed:
   ```php
   define('ENCRYPT_KEY', 'nbsc_secret_key_2024');
   ```

5. Start Apache and MySQL (via XAMPP or similar), then visit:
   ```
   http://localhost/Anonymous_Student_Feedback_System
   ```

---

## Default Accounts

All accounts use the password: **`password`**

| Role | Name | Email |
|---|---|---|
| Admin | Admin NBSC | 20231671@nbsc.edu.ph |
| Manager | Erick Rubin | r.villanueva@nbsc.edu.ph|
| Student | Rhics Geonzon | 20231317@nbsc.edu.ph |
| Student | Troy Rojo | t.rojo@nbsc.edu.ph |
| Student | Francis Idul | 20231685@nbsc.edu.ph |

> Admin and Manager login: `/app/auth/admin-login.php`
> Student login: `/app/auth/student-login.php`

---

## Changelog

### Latest Changes

- **Pagination** added across all major pages — Admin Feedback, Admin Notifications, Admin Review Requests, Admin Activity Logs, Admin Users, Manager Dashboard, Manager Notifications, Student Submissions -displays 10 items per page (5 for student submissions), with centered controls, ellipsis for large page counts, and responsive wrapping
- **Inline filtering** added to Admin Feedback page — Priority and Category dropdowns filter the table instantly without page reload; pagination resets to page 1 on each filter change
- **Inline filtering** added to Manager Dashboard — Time (Recent/Previous), Category, and Priority dropdowns filter feedback with only one active at a time; pagination integrated with filter results
- **Inline filtering** added to Admin Dashboard Recent Feedback — Priority and Category dropdowns with live pagination
- **Feedback Addressed** indicator added to Student Submissions — when a manager has a pending or approved review request on a feedback, the Edit and Delete buttons are replaced with a ✅ Feedback Addressed label, preventing modification
- **Decrypted by Manager indicator** added to Admin Feedback page — Content column shows which manager was approved to read each feedback, displayed as ✅ Decrypted by [Name] (Manager) instead of the encrypted tag

#### Removed Features
- **Status field** removed from the `feedback` table and all related PHP files — feedback no longer tracks `pending`, `reviewed`, or `resolved` states
- **Comments** system removed entirely — `comments` table and all admin/manager comment pages have been deleted
- **User Warnings** system removed entirely — `user_warnings` table and the admin warnings page have been deleted
- **Status column** removed from all feedback tables across admin dashboard, manager dashboard, admin feedback, manager feedback, review requests, view-feedback, and student submissions
- **Activate/Deactivate** toggle removed from the Users management page
- **Status filter** dropdown removed from admin and manager feedback filter forms
- **Status stat cards** (Pending, Reviewed, Resolved) removed from admin and manager dashboards

- **Filter and Reset buttons** removed from Admin Feedback page — replaced by instant dropdown-triggered filtering
- **Activity Logs query updated** — LIMIT 200 removed so all logs are fetched and paginated

#### Modified Features
- **Off-hours restriction** updated — feedback reviews are now permitted **every day** (including weekends), 8:00 AM – 5:00 PM Philippine Time (previously Monday–Friday only)
- **Manager role** corrected in schema — role value changed from `staff` to `manager` to match PHP authentication logic

- **Hamburger dropdown changed** from position: absolute to position: fixed across all pages — dropdown now stays correctly anchored to the navbar when scrolling
- **Staff/Admin accounts** made non-deletable in the Users management page — Delete button is hidden for users with staff or admin role
- **Message character** limit increased from 200 to 1000 characters for both submission and edit forms across student and manager pages
- **Admin Feedback** filtering changed from server-side GET form submission to client-side JS — Filter and Reset buttons removed; dropdowns trigger instant filtering
- **AES-256-CBC encryption fixed** — encryptMessage and decryptMessage now use str_pad(ENCRYPT_KEY, 32, "\0") to ensure the key is always exactly 32 bytes, resolving the OpenSSL IV padding warning
- **Admin Feedback content column updated** — shows 🔒 Encrypted tag by default; replaces it with manager name indicator when a review has been approved

