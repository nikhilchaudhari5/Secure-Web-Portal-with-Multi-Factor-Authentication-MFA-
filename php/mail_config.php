<?php

/**
 * SecureAuth SMTP Configuration
 * 
 * You can set your SMTP credentials directly here or using environment variables.
 * For Gmail:
 * - smtp_host: smtp.gmail.com
 * - smtp_port: 587
 * - smtp_secure: tls
 * - smtp_user: your.email@gmail.com
 * - smtp_pass: your 16-character Google App Password (NOT regular Gmail password)
 */

return array(
    "smtp_host"            => getenv("SMTP_HOST") ?: "smtp.gmail.com",
    "smtp_port"            => (int)(getenv("SMTP_PORT") ?: 587),
    "smtp_secure"          => getenv("SMTP_SECURE") ?: "tls", // "tls" or "ssl"
    "smtp_auth"            => true,
    "smtp_user"            => getenv("SMTP_USER") ?: "your_email@gmail.com",
    "smtp_pass"            => getenv("SMTP_PASS") ?: "your_app_password_here",
    "from_email"           => getenv("SMTP_FROM") ?: "no-reply@secureauth.com",
    "from_name"            => "SecureAuth",
    "otp_validity_minutes" => 5
);
