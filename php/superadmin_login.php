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

/* Query super_admins table */
$query = "SELECT id, email, password_hash, full_name
          FROM super_admins
          WHERE email = $1";

$result = pg_query_params($conn, $query, array($email));

if (!$result) {
    echo json_encode(array(
        "success" => false,
        "message" => "Database query failed"
    ));
    exit;
}

$admin = pg_fetch_assoc($result);

if (!$admin) {
    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

/* Verify password */
if (!password_verify($password, $admin["password_hash"])) {
    echo json_encode(array(
        "success" => false,
        "message" => "Invalid email or password"
    ));
    exit;
}

/* Success */
echo json_encode(array(
    "success" => true,
    "message" => "Super Admin authenticated",
    "user" => array(
        "id" => $admin["id"],
        "email" => $admin["email"],
        "full_name" => $admin["full_name"]
    )
));

?>
