# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

**CNP APP** is a modern student and staff management system for Chainat Pitthayakom School, built as a **monolithic PHP backend with JavaScript frontend** communicating via REST APIs.

**Key Technologies:**
- **Backend:** PHP 8.0+, PDO with MySQL/MariaDB
- **Frontend:** Vanilla JavaScript (ES6+), Bootstrap 5, responsive design
- **Database:** MySQL/MariaDB (InnoDB, utf8mb4)
- **Server:** XAMPP / Apache
- **CSS Architecture:** CSS Variables + custom components (no framework)

---

## How to Run the Application

### Prerequisites
- **XAMPP** installed (PHP 8.0+, MySQL/MariaDB)
- **MySQL** running on `localhost:3306`
- Database named `admin_cnpapp` (configured in `.env`)

### Local Development Setup

1. **Configure environment:**
   - Copy `.env.example` → `.env` (already configured for local XAMPP at `/c/xampp/htdocs/cnpapp`)
   - Ensure `DB_HOST=127.0.0.1`, `DB_NAME=admin_cnpapp`, `DB_USER=root`, `DB_PASS=` (empty)

2. **Start services:**
   ```bash
   # Start Apache & MySQL via XAMPP Control Panel
   # Verify: http://localhost/cnpapp → should show login page
   ```

3. **Database:**
   - Import schema from `/sql/` directory using phpMyAdmin or CLI:
     ```bash
     mysql -u root admin_cnpapp < sql/create_calendar_mysql.sql
     ```

4. **Access the application:**
   - Open browser to `http://localhost/cnpapp`
   - Login: Check users table in database for admin credentials
   - Student: `student_id` / `cnp12345` (default)
   - Teacher: `username` / configured password

### Session & Authentication
- **Session handling:** PHP sessions via `$_SESSION` (see `config.php` for cookie config)
- **Password storage:** `password_hash()` with automatic legacy plaintext → bcrypt upgrade (in `api/login.php`)
- **Remember-me:** Optional 30-day persistent login via `cnp_auth` cookie (two-part token: selector + validator)
- **Rate limiting:** 5 login attempts per 15 minutes per IP+username (file-based in `/tmp/cnp_ratelimit/`)
- **CSRF protection:** Token-based per session (checked in `cnp_csrf_verify()`)
- **Origin check:** POST requests verified to match request origin (prevents CSRF)

---

## Architecture & Code Organization

### High-Level Flow
```
Browser (login page @ index.php)
    ↓
[Role Selection] → Submits to api/login.php
    ↓
[Authentication Layer] cnp_require_auth() + role validation
    ↓
[Frontend Routing] Client-side (no page reloads, dynamic view swapping via JS)
    ↓
[API Calls] Fetch to api/{student|teacher|admin|cron}/*.php
    ↓
[Security Layer] inc/security.php (CSRF, origin, rate limit, session check)
    ↓
[Database] PDO prepared statements → MySQL InnoDB
```

### Directory Structure

```
cnpapp/
├── index.php                      # Login page (HTML + embedded JS)
├── config.php                     # .env loader, DB connection (PDO), session config
├── .env                           # Environment variables (DB credentials, etc.)
├── .env.example                   # Template
├── inc/
│   ├── security.php              # Auth helpers, CSRF, rate limit, token rotation
│   ├── notifications.php         # Notification queue handlers
│   └── classroom_codes.php       # Classroom code variants (supports multiple formats)
├── api/
│   ├── login.php                 # Authentication (session + persistent token)
│   ├── logout.php                # Session cleanup
│   ├── me.php                    # Get current user profile (auto-login if token valid)
│   ├── csrf.php                  # Endpoint to fetch CSRF token
│   ├── settings.php              # App-wide settings (holidays, etc.)
│   ├── admin/                    # Admin-only endpoints
│   │   ├── update_student.php    # Create/update student (whitelist validation)
│   │   ├── update_teacher.php    # Create/update teacher
│   │   ├── students.php          # List/filter students
│   │   ├── teachers.php          # List/filter teachers
│   │   ├── dashboard_stats.php   # Admin stats cards (counts by subject, gender, house)
│   │   ├── upload-students.php   # Bulk student CSV/Excel import
│   │   ├── download-template.php # Template download for bulk upload
│   │   └── credit_score_*.php    # Behavior credit management
│   ├── student/                  # Student-only endpoints
│   │   ├── profile.php           # Student's own profile
│   │   └── dashboard_stats.php   # Student personal stats
│   ├── teacher/                  # Teacher-only endpoints
│   │   ├── dashboard-stats.php   # Teacher stats
│   │   ├── my-classes.php        # Teacher's assigned classes (daily/weekly view)
│   │   └── my-advisory.php       # Advisory room students
│   ├── cron/                     # Background jobs (require CRON_TOKEN)
│   │   └── attendance-sync.php   # Auto-sync attendance from timetable
│   ├── get-address.php           # Province/district/subdistrict cascade dropdowns
│   ├── get-work-positions.php    # Job position enum for teachers
│   ├── timetable.php             # Timetable queries (by class, teacher, room)
│   ├── notifications.php         # Get user notifications
│   ├── public_service.php        # Volunteerism record queries
│   └── calendar.php              # Academic calendar
├── views/
│   ├── admin_dashboard.html      # Admin home (stats cards, quick actions)
│   ├── admin_students.html       # Student management (list, filter, edit modal)
│   ├── admin_teachers.html       # Teacher management
│   ├── student_dashboard.html    # Student home (personal stats, quick links)
│   ├── student_profile.html      # Student profile (editable, completeness tracker)
│   ├── teacher_dashboard.html    # Teacher home (my classes, supervision)
│   ├── teacher_profile.html      # Teacher profile (with digital signature canvas)
│   ├── timetable.html            # Timetable view (daily/weekly)
│   ├── attendance_*.html         # Attendance reporting
│   ├── credit_*.html             # Behavior credit system
│   ├── admin_public_service.html # Volunteerism records
│   └── *.html                    # ~50 more views (all routing done client-side)
├── public/
│   ├── css/
│   │   ├── style.css            # Main styles (imports tokens.css + components.css)
│   │   ├── tokens.css           # Design tokens (colors, spacing, shadows via CSS vars)
│   │   └── components.css       # Reusable card, button, form styles
│   ├── js/
│   │   ├── main.js              # Client-side router, common helpers, API wrapper
│   │   └── *.js                 # Page-specific logic (loaded conditionally)
│   ├── img/                     # Logos, icons
│   └── uploads/
│       ├── students/            # Student photos (auto-generated filenames)
│       ├── teachers/            # Teacher photos
│       └── import/              # Bulk photo import staging
├── sql/
│   ├── create_calendar_mysql.sql # Schema (users, students, teachers, attendance, etc.)
│   ├── migrations/              # Migration scripts
│   └── triggers.sql             # DB triggers (auto-update timestamps, cascades)
├── docs/
│   ├── ARCHITECTURE.md          # Detailed system design & data flow
│   ├── DATABASE.md              # Schema + field mapping (130+ student fields)
│   ├── SYSTEM_REVIEW.md         # Comprehensive review & best practices
│   └── SESSION_LOG_*.md         # Recent session summaries
└── scratch_check_*.php            # One-off debug scripts (not deployed)
```

---

## Key Architectural Patterns

### Authentication & Authorization
- **Entry point:** `api/login.php` validates username+password+role against `users` table
- **Authorization:** Every API endpoint calls `cnp_require_auth($allowed_roles)` to check session and role
- **Roles:** `admin`, `teacher`, `student` (mapped to role_id in users table)
- **Session variables:** `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['user']`, `$_SESSION['csrf_token']`
- **Persistent login:** If "Remember Me" checked → `cnp_auth_token_issue()` creates two-part token in DB, sets `cnp_auth` cookie. Token auto-rotates on each use.

### API Design
- **All endpoints return JSON** with `{ "error": "msg" }` on failure or `{ "success": true, "data": ... }` on success
- **HTTP methods:** POST/DELETE require CSRF token in `X-CSRF-Token` header (checked in `cnp_csrf_verify()`)
- **Role-based access:** Endpoints in `/api/admin/`, `/api/teacher/`, `/api/student/` directories are gated accordingly
- **Data mutations:** All use PDO prepared statements (100% SQL injection protection)

### Frontend Routing
- **No server-side templating:** All views are static `.html` files in `/views/`
- **Client-side router:** `main.js` has simple SPA router that swaps divs based on URL hash (#admin_students)
- **Global header/sidebar:** Dynamically injected (not in each HTML file)
- **API fetching:** Wrapper functions handle session persistence, CSRF token injection, error display
- **No build step:** Raw JavaScript (ES6+) with no transpilation required

### Database Design
- **Core tables:**
  - `users`: Credentials + role mapping
  - `students`: 130+ fields across 6 groups (personal, address, family, health, etc.)
  - `teachers`: Credentials, position, department, house color
  - `attendance`: Daily attendance records
  - `timetable`: Class schedule with teacher assignments
  - `auth_tokens`: Persistent login tokens (selector + hashed validator)
  - `roles`: Lookup table (admin, teacher, student)
- **Relationships:** Foreign keys enforce referential integrity
- **Charset:** utf8mb4 for Thai language support

### House Color System
- **Field:** `students.house` / `teachers.faculty` (ENUM: ขุนสรรค์, เจ้ายี่, ขุนศรี, ธรรมจักร)
- **CSS Integration:** JavaScript sets `--house-color` CSS variable → all themed elements update dynamically
- **Backend:** Used for filtering, sorting, and dashboard statistics

### Profile Completeness
- **Essential fields:** Defined per role (phone, email, id_card, birth_date, house, etc.)
- **Calculation:** `(filled_fields / total_essential) * 100` displayed as progress bar
- **Missing list:** Shows which fields are empty
- **Logic:** In `student_profile.html` and `teacher_profile.html` (client-side, loaded from API)

---

## Common Development Tasks

### Adding a New Student Field
1. Add column to `students` table (via SQL or migration)
2. Add to `$allowedFields` whitelist in `api/admin/update_student.php` (line 58)
3. Add `<input>` to form in `admin_students.html` and `student_profile.html`
4. Add to `essentialFields` array in profile completeness logic if required
5. Update `docs/DATABASE.md` if it's a new category

### Adding a New API Endpoint
1. Create file in appropriate subdirectory: `api/{admin|teacher|student|cron}/your_endpoint.php`
2. Header & security boilerplate:
   ```php
   <?php
   header('Content-Type: application/json');
   require_once '../../config.php';
   require_once '../../inc/security.php';
   session_start();
   
   $user = cnp_require_auth(['admin']); // specify allowed roles
   cnp_csrf_verify();  // if POST/DELETE
   ```
3. Parse input: `$data = json_decode(file_get_contents('php://input'), true);`
4. Return JSON: `echo json_encode(['success' => true, 'data' => $result]);`

### Creating a New Frontend Page
1. Create `.html` file in `views/` (e.g., `views/admin_reports.html`)
2. Use the global sidebar & header (injected by `main.js`)
3. Fetch data via `fetch('api/...').then(r => r.json()).then(data => { ... })`
4. Add route handler in `main.js` router
5. Link from navigation (sidebar or header buttons)

### Debugging Authentication Issues
- Check `$_SESSION` via `api/me.php` (returns current user + csrf_token)
- Login flow: POST to `api/login.php` with `{ "username", "password", "role", "remember" }`
- Session storage: `PHPSESSID` cookie (httponly, samesite=Lax)
- Persistent token: `cnp_auth` cookie (two-part: selector.validator)
- Rate limiting: Check `/tmp/cnp_ratelimit/` for blocked IPs

### Testing File Uploads
- Student/teacher photo upload: `api/admin/update_student.php` / `api/admin/update_teacher.php` with multipart/form-data
- Bulk CSV import: `api/admin/upload-students.php` (supports dry-run mode)
- Photos saved to: `public/uploads/students/` or `public/uploads/teachers/`

### Running Cron Jobs
- Endpoints in `api/cron/` require `CRON_TOKEN` query parameter (set in `.env`)
- Example: `curl "http://localhost/cnpapp/api/cron/attendance-sync.php?token=YOUR_CRON_TOKEN"`
- Used for: Auto-sync attendance, cleanup old sessions, etc.

---

## Common Gotchas

### Session Persistence & "Remember Me"
- If "Remember Me" is checked, both `cnp_auth` (persistent token) and `PHPSESSID` get 30-day expiry
- `config.php` reads `cnp_remember` marker **before** `session_start()` to set cookie lifetime
- Token auto-rotates: old token deleted, new one issued on each authenticated request
- If you modify session code, ensure `session_regenerate_id(true)` is called after login (prevents fixation)

### Classroom Code Variants
- Students can have `class_name` in multiple formats (e.g., "6/1", "M.6/1", "ม.6/1")
- Helper: `cnp_classroom_code_variants($room)` in `inc/classroom_codes.php` generates all variants for filtering
- Teachers limited to editing students in their advisory room (checked via `class_name` IN variants)

### House Color Filtering
- Admin dashboard filters by `house` (student) or `faculty` (teacher) ENUM values
- Frontend sets `--house-color` CSS var when a student is selected
- All themed UI elements (cards, badges, borders) reference this variable

### Email Domain Restriction
- Students/teachers must use `@chainatpit.ac.th` email (enforced in `api/admin/update_student.php` line 62)
- If you allow other domains, update the validation check

### Photo Imports
- Bulk photo sync: Users place image files in `public/uploads/import/` with filenames matching student_id (e.g., `1532.jpg`)
- Admin runs sync tool (UI in admin tools menu) which matches files to students by ID
- Photos are copied/moved to `public/uploads/students/` with auto-generated names

### CSRF Token Handling
- Fresh token generated on each login
- Frontend must include `X-CSRF-Token` header in POST/DELETE/PATCH requests
- Token fetched via `api/csrf.php` on page load (or from `api/me.php` response)
- Bypass: GET/HEAD/OPTIONS requests are exempt

### Database Charset
- Ensure all tables use `utf8mb4_unicode_ci` for proper Thai text handling
- When restoring SQL dumps, use `--default-character-set=utf8mb4`
- Check `sql/create_calendar_mysql.sql` for full schema

---

## Useful Commands

### PHP Syntax Check
```bash
/c/xampp/php/php.exe -l api/login.php
/c/xampp/php/php.exe -l config.php
```

### Database Queries (via CLI)
```bash
# List all tables
mysql -u root admin_cnpapp -e "SHOW TABLES;"

# Check schema of students table
mysql -u root admin_cnpapp -e "DESCRIBE students;"

# Count records
mysql -u root admin_cnpapp -e "SELECT COUNT(*) FROM students;"
```

### Test Authentication Flow
```bash
# Login
curl -sS -c cookies.txt -X POST http://localhost/cnpapp/api/login.php \
  -H "Content-Type: application/json" \
  -H "Origin: http://localhost" \
  -d "{\"username\":\"admin\",\"password\":\"admin123\",\"role\":\"admin\",\"remember\":true}"

# Check session
curl -sS -b cookies.txt http://localhost/cnpapp/api/me.php

# Logout
curl -sS -b cookies.txt -X POST http://localhost/cnpapp/api/logout.php
```

### Check Rate Limiting
```bash
# View rate limit files
ls -la /tmp/cnp_ratelimit/

# Clear rate limit (if blocked)
rm -rf /tmp/cnp_ratelimit/
```

---

## File Modifications to Avoid

These should not be modified without careful consideration:
- **`.env`** — Never commit to version control; only for local dev
- **`config.php`** — Core security & DB setup; changes here affect the entire app
- **`sql/create_calendar_mysql.sql`** — Schema backup; use migrations for changes
- **`public/uploads/`** — Generated dynamically; not source-controlled

---

## Documentation References

For deeper dives, consult:
- **`docs/ARCHITECTURE.md`** — Full system design, routing logic, UI architecture
- **`docs/DATABASE.md`** — Complete schema, field descriptions, relationships
- **`docs/SYSTEM_REVIEW.md`** — Comprehensive review of all components
- **`README.md`** — Feature overview, installation, general usage
- **Session logs** (`docs/SESSION_LOG_*.md`) — Recent updates and fixes

---

## Quick Facts

- **Language:** Thai + English (bilingual UI)
- **Mobile-friendly:** Responsive Bootstrap 5 layout
- **PWA-ready:** Has `manifest.json` and offline icon assets
- **No external API calls:** All data is local to the database
- **Color scheme:** Navy blue (#1e3c72) primary, with house color theming
- **Thai date format:** All dates displayed as Thai Buddhist Era (e.g., "16 พฤษภาคม 2569")
Database Error (Local): SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: YES)
