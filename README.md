# Secure Web Portal with Multi-Factor Authentication (MFA)
### Field Project – II (24CsBCAU5001) | TYBCA Science, Semester V
### "Two-Door" Architecture — Public Portal + Isolated Admin Dashboard

---

## 1. Introduction and Objectives

### Introduction
Most  login systems rely on a single password, which can be stolen
through phishing, weak passwords, or data breaches. This project implements
**Multi-Factor Authentication (MFA)** — after a correct password, the user must also
enter a One-Time Password (OTP) sent to their email before gaining access.

The system is split into two physically separated areas: a **Public Portal** for
normal users (register, login, MFA, dashboard) and a completely isolated **Admin
Directory** for security monitoring (login attempts, locked accounts, active
sessions, alerts). This "Two-Door" separation ensures the admin panel is never
exposed through the public login flow, and admin privilege is never grantable
through the application itself — only via a deliberate, manual database operation.

### Objectives
1. Study vulnerabilities of single-factor (password-only) authentication systems.
2. Design and implement a two-step login process: password verification + OTP verification.
3. Implement secure session management using PHP `$_SESSION`.
4. Implement automatic account lockout after 3 failed login attempts, enforced at the database level.
5. Physically separate admin functionality from the public-facing application, with no in-app path to admin privilege escalation.
6. Build a database-driven Admin Dashboard with 5 security reports powered by PostgreSQL views.
7. Implement automatic account unlock after 15 minutes, checked at every login attempt.
8. Log all login activity (success/failure) with IP address for auditability.

---

## 2. Study of Existing System

### Existing System (Manual / Typical Student Projects)
- Most basic login systems use single-factor authentication (username + password only).
- Admin and user login are often on the same page/form, differentiated only by a
  role check after login — meaning the admin login endpoint is still publicly reachable.
- Admin accounts are sometimes creatable through an in-app "add admin" form, which
  itself becomes an attack target.
- No systematic lockout after repeated failed attempts.
- No structured audit trail of login activity.

### Drawbacks of Existing System
- A single leaked/guessed password grants full account access.
- No secondary verification step.
- Admin login being reachable from the same public entry point increases attack surface.
- An in-app admin-creation path is itself a privilege-escalation risk if not perfectly secured.
- No automatic protection against brute-force attempts.
- No visibility for the system owner into suspicious login patterns.
##-- No way to recover a locked account without direct database access.

### Scope of the Proposed System
- User registration with regex-validated email, password strength, and phone number.
- Two-step login: password verification, then session-based OTP verification via email.
- Physically separate Admin directory — not linked from or reachable via the public login form.
- Admin privilege granted only via manual database `UPDATE` — no in-app escalation path.
- Account lockout after 3 failed attempts, handled by a PostgreSQL trigger.
- **Auto-unlock after 15 minutes** — checked via `check_and_auto_unlock()` at the start of every login attempt. This is the primary unlock mechanism for this submission.
##- Admin Dashboard (single-page, AJAX-driven) showing 5 live security reports.
- Out of scope for this version: SMS OTP, app-based TOTP, live deployment (local-first, deploy later if time allows). Manual admin "unlock now" button is a stretch goal — build it only after everything else is complete (see Section 10).

---

## 3. Requirement Gathering

### End Users of the System
- **Standard User** — registers, logs in with password + OTP, lands on `user_dashboard.php`.
- **Admin** — logs in only via `/admin/login.php` (promoted manually at DB level), views/manages security data on `/admin/dashboard.php`.

### Input Data to the System
- Registration: username, email, phone number, password.
- Login: username/email, password, OTP (entered after email delivery).
- Admin login: admin username, password (role must equal `'admin'`).
##- Admin action: unlock account (target user_id).

### Output from the System
- OTP delivered to registered email via PHPMailer.
- Login success/failure feedback.
- Account lock notification after 3 failed attempts.
- Admin Dashboard reports: locked accounts, login summary, active sessions, recent alerts, daily login activity.
##- Confirmation feedback after an admin unlocks an account.

### Functional Requirements
| ID | Requirement |
|----|-------------|
| FR1 | System shall allow a new user to register with validated username, email, phone, and password |
| FR2 | System shall verify password using `password_verify()` before proceeding |
| FR3 | System shall generate a random OTP via PHP `rand()`, store it in `$_SESSION`, and email it via PHPMailer |
| FR4 | System shall verify the OTP entered by the user against the session value before granting access |
| FR5 | System shall create a PHP session and a corresponding `user_sessions` row on successful login |
| FR6 | System shall log every login attempt (success/failure) with IP address into `login_logs` |
| FR7 | System shall auto-lock an account after 3 failed attempts via a database trigger |
| FR8 | System shall automatically unlock an account after 15 minutes ##--, checked via `check_and_auto_unlock()` at the start of every login attempt |
| FR9 | Admin login shall only be accessible via `/admin/login.php`, never linked from the public `index.php` |
| FR10 | Admin role shall only be assignable via manual database `UPDATE`, never through any in-app form |
| FR11 | Admin Dashboard shall display 5 live reports sourced from PostgreSQL views |
##--| FR12 | System shall destroy the session and deactivate the `user_sessions` row on logout |
##--| FR13 *(stretch, build last)* | Admin shall be able to manually unlock a locked account from the dashboard, ahead of the 15-minute auto-unlock |

---

## 4. UML Diagrams (Minimum 5 — hand-drawn in your workbook)

### i. Class Diagram
- **User** (userId, username, email, phone, passwordHash, role, failedAttempts, isLocked)
- **SessionManager** (sessionId, userId, sessionToken, createdAt, expiresAt, isActive)
- **LoginLog** (logId, userId, status, ipAddress, attemptedAt)
- **OTPHandler** (not a DB table — represents PHP-side logic: generateOTP(), verifyOTP(), stored in $_SESSION)
- **AdminAction** (represents admin-only operations: viewReports(), unlockAccount(userId))

Relationships: User "1 --- many" LoginLog; User "1 --- many" SessionManager; Admin "1 --- many" AdminAction.

### ii. Activity Diagram
Start → User submits username/password → System verifies password → If correct →
Generate OTP → Store in session → Email via PHPMailer → User enters OTP → Compare
against session value → If correct → Create session row → Redirect to
`user_dashboard.php` (or `/admin/dashboard.php` if role = admin) → End.
(If password/OTP incorrect → Insert failed row into `login_logs` → Trigger checks
count → Lock account if ≥ 3 → End. Separately, at the start of every login attempt:
System calls `check_and_auto_unlock()` → if 15 minutes have passed since lock →
resets `is_locked`/`failed_attempts` automatically → login proceeds normally.)

### iii. Sequence Diagram
Objects: **User → Browser → index.php (Public) → PDO/PostgreSQL → PHPMailer → mfa_verify.php**
Sequence: Browser submits login form → `index.php` verifies password via PDO →
generates OTP, stores in `$_SESSION` → PHPMailer sends OTP email → Browser submits
OTP via `mfa_verify.php` → compares against session → on success, inserts into
`user_sessions` via PDO → redirects to dashboard.

### iv. Use Case Diagram
Actor: **User** — Register, Login (Step 1), Verify OTP (Step 2), View Dashboard, Logout.
Actor: **Admin** — Admin Login, View Locked Accounts, View Login Summary, View
Active Sessions, View Alerts, View Daily Activity. *(Manual Unlock Account is a
stretch use case — add to this diagram only if you build FR13.)*

### v. Deployment Diagram
Nodes: **Client Browser** → (HTTP, localhost) → **XAMPP/Apache + PHP 8** → (PDO/pgsql
driver) → **PostgreSQL (Postgres.app, local)**; Apache also connects out to **Gmail
SMTP Server** for OTP delivery. Draw as four boxes with labeled connections.

---

## 5. Two-Door Folder Structure

```
project-root/
│
├── index.php                 # Public login + registration forms ONLY
├── mfa_verify.php            # OTP entry screen
├── user_dashboard.php        # Standard user welcome screen
├── logout.php                # Destroys session, deactivates user_sessions row
│
├── /includes/
│   ├── db_connect.php        # PDO connection (pgsql), ERRMODE_EXCEPTION
│   ├── validators.php        # Regex functions: email, password, phone
│   └── mailer.php            # PHPMailer wrapper: sendOTP($email, $otp)
│
├── /admin/
│   ├── login.php             # Admin-only login, checks role == 'admin'
│   ├── dashboard.php         # SPA-style dashboard, AJAX-loaded reports
│   ├── unlock_account.php    # STRETCH GOAL — build only after everything else works
│   └── logout.php            # Admin session destroy
│
├── /assets/
│   ├── css/
│   └── js/                   # AJAX calls, form validation scripts
│
└── /vendor/                  # Composer-installed PHPMailer (auto-generated)
```

**Rule enforced by this structure:** nothing in `/admin/` is linked from anything in
the public root. An admin must know the direct URL, and no form anywhere grants the
`admin` role — that only happens via a manual SQL `UPDATE`.

---

## 6. Database Schema (Summary — full SQL in `mfa_portal_schema_v2.sql`)

**Tables:** `users` (with `role` column), `login_logs` (IP as `INET`), `user_sessions`, `failed_alerts`
**No `otp_verification` table** — OTP lives only in `$_SESSION`, generated via PHP `rand()`.

**Functions:** `remaining_attempts(user_id)`, `is_admin(user_id)`, `check_and_auto_unlock(user_id)` *(primary unlock mechanism — call before every password check)*
**Triggers:** auto-update `updated_at`; auto-lock account + log alert after 3 failed attempts
**Views (feed the 5 Admin reports):** `locked_accounts_view`, `login_summary_view`,
`active_sessions_view`, `recent_alerts_view`, `daily_login_activity_view`
**Cursor:** `generate_login_report()` — row-by-row report generator (academic requirement)

*(Run `mfa_portal_schema_v2.sql` in pgAdmin4 before writing any PHP.)*

### Admin Bootstrapping (no seed script — manual, deliberate)
```sql
-- Step 1: register normally through index.php like any other user
-- Step 2: promote that user to admin, once, via pgAdmin4:
UPDATE users SET role = 'admin' WHERE username = 'your_admin_username';
```

---

## 7. Backend Logic Reference

**Database connection (PDO, PostgreSQL):**
```php
$pdo = new PDO("pgsql:host=localhost;dbname=mfa_portal", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

**Password hashing:** `password_hash()` at registration, `password_verify()` at login.

**Validation (server-side, in `/includes/validators.php`):**
- Email: `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Password: `/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/`
- Phone: `/^[6-9]\d{9}$/` (Indian 10-digit format)

**OTP generation (session-based, no DB table):**
```php
$otp = rand(100000, 999999);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expires'] = time() + 300; // 5 minutes
// send via PHPMailer, then on mfa_verify.php:
// compare submitted OTP to $_SESSION['otp'] AND check time() < $_SESSION['otp_expires']
```

**PHPMailer (Gmail SMTP):** configured in `/includes/mailer.php`, called as
`sendOTP($userEmail, $otp)` from `index.php` after password verification succeeds.

**Login logging (drives the auto-lock trigger):**
```php
$stmt = $pdo->prepare("INSERT INTO login_logs (user_id, status, ip_address) VALUES (:uid, :status, :ip)");
$stmt->execute(['uid' => $userId, 'status' => $status, 'ip' => $_SERVER['REMOTE_ADDR']]);
```

**Auto-unlock check (call this FIRST, before password verification, in `index.php`):**
```php
$stmt = $pdo->prepare("SELECT check_and_auto_unlock(:uid) AS still_locked");
$stmt->execute(['uid' => $userId]);
$stillLocked = $stmt->fetch(PDO::FETCH_ASSOC)['still_locked'];

if ($stillLocked) {
    // show "Account locked. Try again in a few minutes." and stop here
}
// otherwise continue to password_verify() as normal
```

**Admin unlock account — STRETCH GOAL, build last (in `/admin/unlock_account.php`, admin-session-protected):**
```php
$stmt = $pdo->prepare("UPDATE users SET is_locked = FALSE, failed_attempts = 0, locked_at = NULL WHERE user_id = :uid");
$stmt->execute(['uid' => $targetUserId]);
```

---

## 8. Technology Stack

- **Frontend:** HTML, CSS, Vanilla JS (AJAX for dynamic loads, no page reloads on login/OTP/dashboard)
- **Backend:** PHP 8+, strictly PDO (no `pg_connect`)
- **Database:** PostgreSQL, running locally via Postgres.app
- **Web Server:** XAMPP (Apache)
- **Mailer:** PHPMailer via Composer, Gmail SMTP + App Password
- **Tools:** VS Code (PHP Intelephense), pgAdmin4, Git/GitHub

---

## 9. Reports (Admin Dashboard — 5 required, sourced from views)

1. **Locked Accounts Report** — `locked_accounts_view` (accounts auto-clear after 15 min; manual Unlock button optional/stretch)
2. **User Login Summary Report** — `login_summary_view`
3. **Active Sessions Report** — `active_sessions_view`
4. **Recent Security Alerts Report** — `recent_alerts_view`
5. **Daily Login Activity Report** — `daily_login_activity_view`

*(Take color screenshots of each rendered report for your printed submission.)*

---

## 10. Future Enhancement (build in this order, only after core flow works)
1. **Manual admin "Unlock Now" button** — lets an admin clear a lock before the 15-minute auto-unlock completes; small AJAX call + PDO update, isolated and low-risk to add last.
2. Move OTP from session-based to database-backed with expiry, if scaling beyond a single-server demo.
3. Add app-based TOTP (Google Authenticator, via `pyotp` or a PHP equivalent).
4. Add SMS OTP as a secondary channel.
5. Deploy online (cheap PHP+PostgreSQL hosting) once local version is fully stable.
6. Add charts to Admin Dashboard (ties into Data Analytics coursework).

## 11. Limitations and Drawbacks
- OTP validity is tied to the browser session — switching browsers/devices mid-verification breaks the flow (acceptable for MVP/demo).
- Locked accounts can only be unlocked by waiting 15 minutes in the base version — no way to shortcut this unless the manual admin unlock (Section 10) is built.
- Single Gmail account used for all outgoing OTP mail — subject to Gmail's sending limits.
- No live deployment in the base submission — local demo only, by design.
- Admin promotion requires direct database access — intentional for security, but means a second admin can't be added without DB access (acceptable for a single-admin college project).

## 12. Bibliography
- PHP Official Documentation — https://www.php.net/
- PostgreSQL Official Documentation — https://www.postgresql.org/docs/
- PHPMailer GitHub Documentation — https://github.com/PHPMailer/PHPMailer
- OWASP Authentication Cheat Sheet — https://cheatsheetseries.owasp.org/
- Tools used: VS Code, XAMPP, Postgres.app, pgAdmin4, PHP 8, PostgreSQL, PHPMailer, Composer, HTML, CSS, JavaScript, AJAX

---

## 13. Build Order (follow top to bottom, don't skip ahead)

1. Run `mfa_portal_schema_v2.sql` in pgAdmin4 — creates all tables/views/triggers
2. Build `/includes/db_connect.php` — confirm PDO connects (echo test)
3. Build `index.php` registration form + `/includes/validators.php` — test regex validation
4. Register your own admin account through the normal form, then promote it via the SQL `UPDATE` shown in Section 6
5. Build `index.php` login: call `check_and_auto_unlock()` first, then password check — confirm session starts, `login_logs` insert works, trigger locks after 3 fails, and account auto-unlocks after 15 min
6. Install Composer + PHPMailer, build `/includes/mailer.php` — test sending yourself one email
7. Build OTP generation + `mfa_verify.php` — full password+OTP flow working end to end
8. Build `user_dashboard.php` + `logout.php`
9. Build `/admin/login.php` (role check) + `/admin/dashboard.php` with AJAX-loaded views (5 reports)
10. Convert key steps (login, OTP submit) to AJAX so there's no full page reload
11. Polish UI, take screenshots for reports section
12. Write up Introduction/Requirement Gathering/UML sections in your physical workbook, hand-drawn, per the timeline below
13. **Only if time remains after all deadlines are met:** build `/admin/unlock_account.php` (Section 10, stretch goal)

---
