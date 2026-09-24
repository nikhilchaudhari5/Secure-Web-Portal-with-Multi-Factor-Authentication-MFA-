<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    !$data ||
    empty(trim($data["name"] ?? "")) ||
    empty(trim($data["email"] ?? "")) ||
    empty($data["password"] ?? "")
) {
    echo json_encode(array(
        "success" => false,
        "message" => "All fields are required"
    ));
    exit;
}

$full_name = trim($data["name"]);
$email = trim($data["email"]);
$password = $data["password"];

if (strlen($password) < 8) {
    echo json_encode(array(
        "success" => false,
        "message" => "Password must be at least 8 characters long"
    ));
    exit;
}

/* Check if user with this email already exists in company 1 */
$company_id = 1;
$check_query = "SELECT id FROM users WHERE email = $1 AND company_id = $2";
$check_result = pg_query_params($conn, $check_query, array($email, $company_id));

if ($check_result && pg_num_rows($check_result) > 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "A user with this email already exists"
    ));
    exit;
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);

/* Insert user */
$insert_query = "INSERT INTO users (company_id, email, password_hash, full_name, role, is_locked, failed_attempts)
                 VALUES ($1, $2, $3, $4, 'user', FALSE, 0)
                 RETURNING id";

$insert_result = pg_query_params($conn, $insert_query, array(
    $company_id,
    $email,
    $password_hash,
    $full_name
));

if (!$insert_result) {
    echo json_encode(array(
        "success" => false,
        "message" => "Failed to create account. Please try again."
    ));
    exit;
}

echo json_encode(array(
    "success" => true,
    "message" => "Account created successfully! Redirecting to login..."
));

?>
