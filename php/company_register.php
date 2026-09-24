<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    !$data ||
    empty(trim($data["company_name"] ?? "")) ||
    empty(trim($data["admin_email"] ?? "")) ||
    empty($data["password"] ?? "")
) {
    echo json_encode(array(
        "success" => false,
        "message" => "All fields are required"
    ));
    exit;
}

$company_name = trim($data["company_name"]);
$admin_email = trim($data["admin_email"]);
$password = $data["password"];

if (strlen($password) < 8) {
    echo json_encode(array(
        "success" => false,
        "message" => "Password must be at least 8 characters long"
    ));
    exit;
}

/* Check if company email already exists */
$check_query = "SELECT id FROM companies WHERE admin_email = $1";
$check_result = pg_query_params($conn, $check_query, array($admin_email));

if ($check_result && pg_num_rows($check_result) > 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "Organization with this email already exists"
    ));
    exit;
}

/* Generate client credentials */
$client_id = "client_" . substr(md5(uniqid(mt_rand(), true)), 0, 16);
$api_key = "sk_" . bin2hex(random_bytes(16));
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$subscription_plan = "starter";
$allowed_redirect_uri = "http://localhost:8000/Demo%20client/demo_client_app.html";

/* Insert new company */
$insert_query = "INSERT INTO companies (
                    company_name, admin_email, password_hash, client_id,
                    api_key, subscription_plan, max_failed_attempts, allowed_redirect_uri
                 ) VALUES ($1, $2, $3, $4, $5, $6, 3, $7)
                 RETURNING id";

$insert_result = pg_query_params($conn, $insert_query, array(
    $company_name,
    $admin_email,
    $password_hash,
    $client_id,
    $api_key,
    $subscription_plan,
    $allowed_redirect_uri
));

if (!$insert_result) {
    echo json_encode(array(
        "success" => false,
        "message" => "Registration failed. Please try again."
    ));
    exit;
}

echo json_encode(array(
    "success" => true,
    "message" => "Organization registered successfully! Redirecting to login...",
    "client_id" => $client_id
));

?>
