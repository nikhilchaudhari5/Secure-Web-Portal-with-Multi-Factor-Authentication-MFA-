<?php

/**
 * SecureAuth Mailer Helper
 * Sends OTP verification emails via SMTP using PHPMailer.
 */

require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_otp_email($recipient_email, $otp_code, $validity_minutes = 5) {
    $config = require __DIR__ . "/mail_config.php";

    $mail = new PHPMailer(true);

    try {
        // SMTP Server configuration
        $mail->isSMTP();
        $mail->Host       = $config["smtp_host"];
        $mail->SMTPAuth   = $config["smtp_auth"];
        $mail->Username   = $config["smtp_user"];
        $mail->Password   = $config["smtp_pass"];
        
        if (strtolower($config["smtp_secure"]) === "ssl") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = $config["smtp_port"];

        // Sender & Recipient
        // If from_email is placeholder but smtp_user has an email, use smtp_user
        $fromEmail = (!empty($config["smtp_user"]) && strpos($config["smtp_user"], "@") !== false && $config["from_email"] === "no-reply@secureauth.com")
            ? $config["smtp_user"]
            : $config["from_email"];

        $mail->setFrom($fromEmail, $config["from_name"]);
        $mail->addAddress($recipient_email);

        // Message Content - simple, non-fancy, OTP in bold form, valid time, from SecureAuth
        $mail->isHTML(true);
        $mail->Subject = "Your SecureAuth Verification Code";
        
        $safeOtp = htmlspecialchars($otp_code, ENT_QUOTES, "UTF-8");
        $safeValidity = (int)$validity_minutes;

        $mail->Body = "<div style=\"font-family: Arial, sans-serif; font-size: 15px; color: #111827; line-height: 1.6;\">"
            . "<p>Hello,</p>"
            . "<p>Your SecureAuth verification code is: <b>" . $safeOtp . "</b></p>"
            . "<p>This code is valid for " . $safeValidity . " minutes.</p>"
            . "<p>If you did not request this verification code, please ignore this email.</p>"
            . "<p>From,<br>SecureAuth</p>"
            . "</div>";

        $mail->AltBody = "Hello,\n\n"
            . "Your SecureAuth verification code is: " . $otp_code . "\n\n"
            . "This code is valid for " . $safeValidity . " minutes.\n\n"
            . "If you did not request this verification code, please ignore this email.\n\n"
            . "From,\n"
            . "SecureAuth\n";

        $mail->send();
        return array(
            "success" => true,
            "message" => "OTP email sent successfully"
        );
    } catch (Exception $e) {
        return array(
            "success" => false,
            "message" => "Mailer Error: " . $mail->ErrorInfo
        );
    }
}
