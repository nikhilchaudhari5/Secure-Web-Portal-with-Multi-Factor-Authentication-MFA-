<?php

$host = "localhost";
$port = "5432";
$dbname = "secureauth_db";
$user = "nikhil";
$password = "123";

$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    $conn = pg_connect("host=$host port=$port dbname=secureauth user=$user password=$password");
}

if (!$conn) {
    die("Database connection failed");
}

@pg_query($conn, "CREATE TABLE IF NOT EXISTS otp_verifications (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
)");

?>
