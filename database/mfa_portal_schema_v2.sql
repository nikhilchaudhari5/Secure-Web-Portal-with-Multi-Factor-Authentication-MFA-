-- ============================================================
-- Secure Web Portal with MFA — PostgreSQL Schema (v2)
-- Two-Door Architecture: Public + Admin, session-based OTP
-- Run this in pgAdmin4 / psql against your mfa_portal database
-- ============================================================

-- ============================================================
-- 1. TABLES
-- ============================================================

CREATE TABLE users (
    user_id         SERIAL PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(100) NOT NULL UNIQUE,
    phone_number    VARCHAR(15),
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'user', -- 'user' or 'admin'
    failed_attempts INT          NOT NULL DEFAULT 0,
    is_locked       BOOLEAN      NOT NULL DEFAULT FALSE,
    locked_at       TIMESTAMP,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- NOTE: otp_verification table intentionally removed.
-- OTPs are generated via PHP rand() and held in $_SESSION only.

CREATE TABLE login_logs (
    log_id        SERIAL PRIMARY KEY,
    user_id       INT REFERENCES users(user_id) ON DELETE CASCADE,
    status        VARCHAR(30) NOT NULL, -- Success / Failed Password / Failed OTP / Locked Attempt
    ip_address    INET,                  -- native PostgreSQL IP type
    attempted_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_sessions (
    session_id     SERIAL PRIMARY KEY,
    user_id        INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    session_token  VARCHAR(255) NOT NULL,
    ip_address     INET,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at     TIMESTAMP    NOT NULL,
    is_active      BOOLEAN      NOT NULL DEFAULT TRUE
);

-- Optional: table for tracking suspicious/locked-account alerts on Admin Dashboard
CREATE TABLE failed_alerts (
    alert_id      SERIAL PRIMARY KEY,
    user_id       INT REFERENCES users(user_id) ON DELETE CASCADE,
    ip_address    INET,
    reason        VARCHAR(100), -- e.g. 'Account locked after 3 failed attempts'
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. FUNCTIONS
-- ============================================================

-- Function 1: Remaining login attempts before lockout
CREATE OR REPLACE FUNCTION remaining_attempts(p_user_id INT, p_max_attempts INT DEFAULT 3)
RETURNS INT AS $$
DECLARE
    v_failed INT;
BEGIN
    SELECT failed_attempts INTO v_failed FROM users WHERE user_id = p_user_id;
    RETURN GREATEST(p_max_attempts - v_failed, 0);
END;
$$ LANGUAGE plpgsql;

-- Usage: SELECT remaining_attempts(3);


-- Function 2: Check if a given user currently holds admin role
CREATE OR REPLACE FUNCTION is_admin(p_user_id INT)
RETURNS BOOLEAN AS $$
DECLARE
    v_role VARCHAR(20);
BEGIN
    SELECT role INTO v_role FROM users WHERE user_id = p_user_id;
    RETURN v_role = 'admin';
END;
$$ LANGUAGE plpgsql;

-- Usage: SELECT is_admin(1);


-- Function 3: Auto-unlock check — PRIMARY unlock mechanism for this project.
-- Call this from PHP as the very first step of every login attempt, BEFORE
-- checking password. If the account has been locked for 15+ minutes, it
-- resets is_locked and failed_attempts automatically and returns FALSE
-- (not locked). Otherwise returns the current lock status.
CREATE OR REPLACE FUNCTION check_and_auto_unlock(p_user_id INT)
RETURNS BOOLEAN AS $$
DECLARE
    v_is_locked BOOLEAN;
    v_locked_at TIMESTAMP;
BEGIN
    SELECT is_locked, locked_at INTO v_is_locked, v_locked_at
    FROM users WHERE user_id = p_user_id;

    IF v_is_locked AND v_locked_at IS NOT NULL
       AND v_locked_at <= CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN

        UPDATE users
        SET is_locked = FALSE, failed_attempts = 0, locked_at = NULL
        WHERE user_id = p_user_id;

        RETURN FALSE; -- account is now unlocked
    END IF;

    RETURN v_is_locked; -- still locked (or was never locked)
END;
$$ LANGUAGE plpgsql;

-- Usage in PHP, before password check:
-- SELECT check_and_auto_unlock(:user_id);
-- if it returns TRUE, show "Account locked, try again later" and stop.


-- ============================================================
-- 3. TRIGGERS
-- ============================================================

-- Trigger 1: Auto-update "updated_at" whenever a user row changes
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_timestamp
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();


-- Trigger 2: Auto-lock account after 3 failed attempts + log alert for Admin Dashboard
CREATE OR REPLACE FUNCTION handle_failed_login()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status IN ('Failed Password', 'Failed OTP') THEN
        UPDATE users
        SET failed_attempts = failed_attempts + 1
        WHERE user_id = NEW.user_id;

        UPDATE users
        SET is_locked = TRUE, locked_at = CURRENT_TIMESTAMP
        WHERE user_id = NEW.user_id AND failed_attempts >= 3 AND is_locked = FALSE;

        -- log an alert row if this attempt caused a lockout
        INSERT INTO failed_alerts (user_id, ip_address, reason)
        SELECT NEW.user_id, NEW.ip_address, 'Account locked after 3 failed attempts'
        WHERE (SELECT failed_attempts FROM users WHERE user_id = NEW.user_id) >= 3
          AND (SELECT is_locked FROM users WHERE user_id = NEW.user_id) = TRUE;

    ELSIF NEW.status = 'Success' THEN
        UPDATE users
        SET failed_attempts = 0
        WHERE user_id = NEW.user_id;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_handle_failed_login
AFTER INSERT ON login_logs
FOR EACH ROW
EXECUTE FUNCTION handle_failed_login();


-- ============================================================
-- 4. VIEWS  (feed the 5 Admin Dashboard reports)
-- ============================================================

-- View 1: Currently locked accounts
CREATE OR REPLACE VIEW locked_accounts_view AS
SELECT user_id, username, email, failed_attempts, locked_at
FROM users
WHERE is_locked = TRUE;

-- View 2: Login activity summary per user
CREATE OR REPLACE VIEW login_summary_view AS
SELECT
    u.user_id,
    u.username,
    COUNT(*) FILTER (WHERE l.status = 'Success')    AS successful_logins,
    COUNT(*) FILTER (WHERE l.status LIKE 'Failed%')  AS failed_logins,
    MAX(l.attempted_at)                              AS last_attempt
FROM users u
JOIN login_logs l ON u.user_id = l.user_id
GROUP BY u.user_id, u.username;

-- View 3: Active sessions (for Admin "Active Sessions" report)
CREATE OR REPLACE VIEW active_sessions_view AS
SELECT s.session_id, u.username, s.ip_address, s.created_at, s.expires_at
FROM user_sessions s
JOIN users u ON s.user_id = u.user_id
WHERE s.is_active = TRUE;

-- View 4: Recent failed alerts (for Admin monitoring)
CREATE OR REPLACE VIEW recent_alerts_view AS
SELECT a.alert_id, u.username, a.ip_address, a.reason, a.created_at
FROM failed_alerts a
JOIN users u ON a.user_id = u.user_id
ORDER BY a.created_at DESC;

-- View 5: Date-wise login activity
CREATE OR REPLACE VIEW daily_login_activity_view AS
SELECT
    DATE(attempted_at) AS login_date,
    COUNT(*) FILTER (WHERE status = 'Success')   AS successes,
    COUNT(*) FILTER (WHERE status LIKE 'Failed%') AS failures
FROM login_logs
GROUP BY DATE(attempted_at)
ORDER BY login_date DESC;

-- ============================================================
-- 5. CURSOR (kept for academic requirement)
-- ============================================================

CREATE OR REPLACE FUNCTION generate_login_report()
RETURNS TEXT AS $$
DECLARE
    report_line TEXT := '';
    rec RECORD;
    user_cursor CURSOR FOR
        SELECT username, successful_logins, failed_logins
        FROM login_summary_view
        ORDER BY username;
BEGIN
    OPEN user_cursor;
    LOOP
        FETCH user_cursor INTO rec;
        EXIT WHEN NOT FOUND;

        report_line := report_line || rec.username || ': ' ||
                        rec.successful_logins || ' success, ' ||
                        rec.failed_logins || ' failed' || E'\n';
    END LOOP;
    CLOSE user_cursor;

    RETURN report_line;
END;
$$ LANGUAGE plpgsql;

-- Usage: SELECT generate_login_report();


-- ============================================================
-- 6. SAMPLE ADMIN USER (update password_hash via PHP password_hash() output)
-- ============================================================

-- INSERT INTO users (username, email, password_hash, role)
-- VALUES ('admin', 'admin@example.com', 'REPLACE_WITH_HASHED_PASSWORD', 'admin');
