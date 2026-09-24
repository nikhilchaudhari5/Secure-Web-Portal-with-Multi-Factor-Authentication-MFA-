<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data["email"] ?? "");

if (empty($email)) {
    echo json_encode(array(
        "success" => false,
        "message" => "Email address is required to resend verification code"
    ));
    exit;
}

$config = @include __DIR__ . "/mail_config.php";
$validity_minutes = isset($config["otp_validity_minutes"]) ? (int)$config["otp_validity_minutes"] : 5;
$otp_code = str_pad((string)random_int(100000, 999999), 6, "0", STR_PAD_LEFT);

// Invalidate older unused OTPs for this email
@pg_query_params($conn, "UPDATE otp_verifications SET is_used = TRUE WHERE email = $1 AND is_used = FALSE", array($email));

// Insert new OTP with 5-minute validity
@pg_query_params(
    $conn,
    "INSERT INTO otp_verifications (email, otp_code, expires_at, is_used) VALUES ($1, $2, NOW() + INTERVAL '5 minutes', FALSE)",
    array($email, $otp_code)
);

// Send OTP email via SMTP
$mailResult = send_otp_email($email, $otp_code, $validity_minutes);

// Find company if any for audit log
$company_id = 1;
$u_res = @pg_query_params($conn, "SELECT company_id FROM users WHERE email = $1", array($email));
if ($u_res && pg_num_rows($u_res) > 0) {
    $u_row = pg_fetch_assoc($u_res);
    $company_id = $u_row["company_id"] ?: 1;
}

$ip_address = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
$auditDetails = $mailResult["success"]
    ? "Resent OTP via SMTP to $email (Valid for {$validity_minutes}m)"
    : "Resent OTP generated for $email (SMTP notice: " . substr($mailResult["message"], 0, 80) . ")";

@pg_query_params($conn,
    "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
     VALUES ($1, $2, $3, 'MFA_CHALLENGE', 'PASSWORD+EMAIL_OTP', 'CHALLENGE', $4)",
    array($company_id, $email, $ip_address, $auditDetails)
);

echo json_encode(array(
    "success" => true,
    "message" => "A new verification code has been sent to " . htmlspecialchars($email),
    "mail_sent" => $mailResult["success"]
));
