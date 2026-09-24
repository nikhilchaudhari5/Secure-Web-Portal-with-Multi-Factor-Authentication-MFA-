<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data["email"]) || !isset($data["password"]) || trim($data["email"]) === "" || $data["password"] === "") {
    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

$email = trim($data["email"]);
$password = $data["password"];
$ip_address = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";

/* Find user by email */
$query = "SELECT id, company_id, email, password_hash, full_name, role, is_locked, failed_attempts
          FROM users
          WHERE email = $1";

$result = pg_query_params($conn, $query, array($email));

if (!$result) {
    echo json_encode(array(
        "success" => false,
        "message" => "Database query failed"
    ));
    exit;
}

$user = pg_fetch_assoc($result);

/* User not found */
if (!$user) {
    @pg_query_params($conn,
        "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
         VALUES (NULL, $1, $2, 'LOGIN_ATTEMPT', 'PASSWORD', 'FAILED', 'Unknown user email attempt')",
        array($email, $ip_address)
    );

    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

$company_id = $user["company_id"] ?: 1;

/* Check account lock */
if ($user["is_locked"] === "t" || $user["is_locked"] === true || $user["is_locked"] === "1") {
    @pg_query_params($conn,
        "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
         VALUES ($1, $2, $3, 'LOCKOUT', 'PASSWORD', 'LOCKED', 'Attempt on locked account')",
        array($company_id, $email, $ip_address)
    );

    echo json_encode(array(
        "success" => false,
        "message" => "Account is locked"
    ));
    exit;
}

require_once __DIR__ . "/mailer.php";

/* Verify password */
if (!password_verify($password, $user["password_hash"])) {
    @pg_query_params($conn,
        "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
         VALUES ($1, $2, $3, 'LOGIN_ATTEMPT', 'PASSWORD', 'FAILED', 'Incorrect password attempt')",
        array($company_id, $email, $ip_address)
    );

    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

/* Generate 6-digit numeric OTP */
$config = @include __DIR__ . "/mail_config.php";
$validity_minutes = isset($config["otp_validity_minutes"]) ? (int)$config["otp_validity_minutes"] : 5;
$otp_code = str_pad((string)random_int(100000, 999999), 6, "0", STR_PAD_LEFT);

/* Invalidate any older unused OTP for this email */
@pg_query_params($conn, "UPDATE otp_verifications SET is_used = TRUE WHERE email = $1 AND is_used = FALSE", array($email));

/* Insert fresh OTP with 5-minute validity */
@pg_query_params(
    $conn,
    "INSERT INTO otp_verifications (email, otp_code, expires_at, is_used) VALUES ($1, $2, NOW() + INTERVAL '5 minutes', FALSE)",
    array($email, $otp_code)
);

/* Send OTP email via SMTP */
$mailResult = send_otp_email($email, $otp_code, $validity_minutes);

/* Log MFA Challenge */
$auditDetails = $mailResult["success"]
    ? "OTP sent via SMTP to $email (Valid for {$validity_minutes}m)"
    : "OTP generated for $email (SMTP notice: " . substr($mailResult["message"], 0, 80) . ")";

@pg_query_params($conn,
    "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
     VALUES ($1, $2, $3, 'MFA_CHALLENGE', 'PASSWORD', 'CHALLENGE', $4)",
    array($company_id, $email, $ip_address, $auditDetails)
);

/* Login successful & OTP dispatched */
echo json_encode(array(
    "success" => true,
    "message" => "Password verified. Verification code has been sent to your email.",
    "email" => $user["email"],
    "mail_sent" => $mailResult["success"],
    "user" => array(
        "id" => $user["id"],
        "email" => $user["email"],
        "full_name" => $user["full_name"],
        "role" => $user["role"]
    )
));

?>