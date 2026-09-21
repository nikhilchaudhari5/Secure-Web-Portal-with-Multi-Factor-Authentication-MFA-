-- 1. Tenant Companies Table (B2B Auth-as-a-Service)
CREATE TABLE IF NOT EXISTS companies (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    admin_email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    client_id VARCHAR(64) NOT NULL UNIQUE,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    subscription_plan VARCHAR(30) NOT NULL DEFAULT 'starter', -- 'starter', 'pro', 'enterprise'
    max_failed_attempts INT NOT NULL DEFAULT 3,
    allowed_redirect_uri TEXT DEFAULT 'http://localhost:8000/demo_client_app.php',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Platform Super Administrators (Platform Owners)
CREATE TABLE IF NOT EXISTS super_admins (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Users Table (Multi-Tenant End-Users with Email OTP)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    company_id INT REFERENCES companies(id) ON DELETE SET NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user', -- 'user', 'admin'
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    failed_attempts INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_user_per_company UNIQUE (email, company_id)
);

-- 4. Active Sessions & Trusted Devices (30-day Remember Device Token)
CREATE TABLE IF NOT EXISTS active_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    device_name VARCHAR(100),
    ip_address VARCHAR(45) NOT NULL,
    location_city VARCHAR(100),
    user_agent TEXT,
    is_trusted_device BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Integration Authorization Codes (Single-Use Token for 3rd-Party Redirects)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    company_id INT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    auth_code VARCHAR(64) NOT NULL UNIQUE,
    redirect_uri TEXT NOT NULL,
    is_exchanged BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 6. Enterprise Security Audit Trail & Telemetry
CREATE TABLE IF NOT EXISTS security_audit_logs (
    id SERIAL PRIMARY KEY,
    company_id INT REFERENCES companies(id) ON DELETE SET NULL,
    user_email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    location VARCHAR(100) DEFAULT 'Local Network',
    action VARCHAR(50) NOT NULL, -- 'LOGIN_ATTEMPT', 'MFA_CHALLENGE', 'OTP_VERIFIED', 'LOCKOUT', 'ADMIN_UNLOCK', 'API_EXCHANGE'
    auth_method VARCHAR(50) DEFAULT 'PASSWORD+EMAIL_OTP',
    status VARCHAR(20) NOT NULL, -- 'SUCCESS', 'FAILED', 'LOCKED', 'CHALLENGE'
    details TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. Indexes for High-Throughput CIAM Telemetry
CREATE INDEX IF NOT EXISTS idx_companies_client_id ON companies(client_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_company ON users(company_id);
CREATE INDEX IF NOT EXISTS idx_sessions_token ON active_sessions(session_token);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_code ON auth_tokens(auth_code);
CREATE INDEX IF NOT EXISTS idx_audit_created ON security_audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_status ON security_audit_logs(status);

-- 8. PL/pgSQL Trigger: Automated Intrusion Lockout after 3 Failed Attempts
CREATE OR REPLACE FUNCTION trigger_auto_lock_account()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.failed_attempts >= 3 THEN
        NEW.is_locked = TRUE;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_check_failed_attempts ON users;
CREATE TRIGGER trg_check_failed_attempts
BEFORE UPDATE OF failed_attempts ON users
FOR EACH ROW
EXECUTE FUNCTION trigger_auto_lock_account();

-- 9. Default  Data
INSERT INTO super_admins (email, password_hash, full_name)
VALUES ('superadmin@secureauth.io', '$2y$12$rCh59vo93hz9K56hC6XP1OkwJB0oH99TKH2ATqf9ZcS7tlv3Tf9KS', 'Platform Super Admin')


--  Default Companies Data
INSERT INTO companies (id, company_name, admin_email, password_hash, client_id, api_key, subscription_plan, max_failed_attempts, allowed_redirect_uri)
VALUES 
(1, 'Acme Enterprise HR', 'admin@acme.com', '$2y$12$rCh59vo93hz9K56hC6XP1OkwJB0oH99TKH2ATqf9ZcS7tlv3Tf9KS', 'client_acme_demo_123', 'sk_acme_live_secret_456789', 'enterprise', 3, 'http://localhost:8000/demo_client_app.php'),
(2, 'TechNova Cloud Solutions', 'dev@technova.io', '$2y$12$rCh59vo93hz9K56hC6XP1OkwJB0oH99TKH2ATqf9ZcS7tlv3Tf9KS', 'client_technova_demo_789', 'sk_technova_live_secret_123456', 'pro', 3, 'http://localhost:8000/demo_client_app.php')




--  End-Users Default Data
INSERT INTO users (company_id, email, password_hash, full_name, role, is_locked, failed_attempts, mfa_enabled)
VALUES 
(1, 'testuser@secureauth.com', '$2y$12$GmmLSFR45aKgYoYE1RPGu.vpOJN1NExbqeXfvLg3VB1XcRzOqSh7G', 'Test User', 'user', FALSE, 0, TRUE),
(1, 'admin@secureauth.com', '$2y$12$rCh59vo93hz9K56hC6XP1OkwJB0oH99TKH2ATqf9ZcS7tlv3Tf9KS', 'Security Admin', 'admin', FALSE, 0, TRUE),
(1, 'intruder.target@corp.io', '$2y$12$GmmLSFR45aKgYoYE1RPGu.vpOJN1NExbqeXfvLg3VB1XcRzOqSh7G', 'Intruder Target', 'user', TRUE, 3, TRUE)

