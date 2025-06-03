<?php
session_start();
require_once 'db_connect.php';
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get email from session
$email = $_SESSION['reset_email'] ?? null;
$user_id = $_SESSION['reset_user_id'] ?? null;

if (!$email || !$user_id) {
    $_SESSION['error'] = "Session expired. Please start over.";
    header("Location: ../main_resetpassword.html");
    exit();
}

// Generate new OTP
$otp = rand(100000, 999999);
$expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

// Store new OTP
$sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $otp, $expires_at);
$stmt->execute();

// Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ojt.monitoring.center@gmail.com';
    $mail->Password = 'yopi eyyt trgh zbvi';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->setFrom('ojt.monitoring.center@gmail.com', 'OJT Monitoring Center');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset OTP (Resent)';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #064a25;'>Password Reset Request</h2>
            <p>You requested a new OTP. Use the following code:</p>
            <div style='background-color: #f5f5f5; padding: 15px; text-align: center; margin: 20px 0;'>
                <h1 style='color: #1f7d35; margin: 0;'>{$otp}</h1>
            </div>
            <p style='color: #666;'>This OTP will expire in 5 minutes.</p>
            <hr style='border: 1px solid #eee; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>This is an automated message, please do not reply.</p>
        </div>";
    $mail->send();

    $_SESSION['success'] = "A new OTP has been sent to your email.";
    header("Location: ../main_otp.html");
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to resend OTP. Please try again.";
    header("Location: ../main_otp.html");
    exit();
}
?>
