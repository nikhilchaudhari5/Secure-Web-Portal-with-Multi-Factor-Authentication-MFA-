<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    !$data ||
    empty(trim($data["email"] ?? "")) ||
    empty($data["password"] ?? "")
) {
    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

$email = trim($data["email"]);
$password = $data["password"];
$ip_address = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";

/* Find company by admin_email */
$query = "SELECT id, company_name, admin_email, password_hash, client_id, api_key, subscription_plan
          FROM companies
          WHERE admin_email = $1";

$result = pg_query_params($conn, $query, array($email));

if (!$result) {
    echo json_encode(array(
        "success" => false,
        "message" => "Database query failed"
    ));
    exit;
}

$company = pg_fetch_assoc($result);

if (!$company) {
    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

/* Verify password */
if (!password_verify($password, $company["password_hash"])) {
    @pg_query_params($conn, 
        "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
         VALUES ($1, $2, $3, 'COMPANY_LOGIN', 'PASSWORD', 'FAILED', 'Invalid password attempt')",
        array($company["id"], $email, $ip_address)
    );

    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

/* Log successful login to security_audit_logs */
@pg_query_params($conn, 
    "INSERT INTO security_audit_logs (company_id, user_email, ip_address, action, auth_method, status, details)
     VALUES ($1, $2, $3, 'COMPANY_LOGIN', 'PASSWORD', 'SUCCESS', 'Company administrator logged in successfully')",
    array($company["id"], $email, $ip_address)
);

/* Login successful */
echo json_encode(array(
    "success" => true,
    "message" => "Login successful",
    "company" => array(
        "id" => $company["id"],
        "company_name" => $company["company_name"],
        "admin_email" => $company["admin_email"],
        "client_id" => $company["client_id"],
        "api_key" => $company["api_key"],
        "subscription_plan" => $company["subscription_plan"]
    )
));

?>
