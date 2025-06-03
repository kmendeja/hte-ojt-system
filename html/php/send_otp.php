<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email.";
        header("Location: ../main_resetpassword.html");
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: ../main_resetpassword.html");
        exit();
    }

    // Check if email exists in database
    $sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        
        // Generate OTP
        $otp = rand(100000, 999999);
        
        // Store OTP in password_reset_tokens table
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $otp, $expires_at);
        
        if ($stmt->execute()) {
            $_SESSION["reset_email"] = $email;
            $_SESSION["reset_user_id"] = $user_id;

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
                $mail->Subject = 'Password Reset OTP';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #064a25;'>Password Reset Request</h2>
                        <p>You have requested to reset your password. Use the following OTP to proceed:</p>
                        <div style='background-color: #f5f5f5; padding: 15px; text-align: center; margin: 20px 0;'>
                            <h1 style='color: #1f7d35; margin: 0;'>{$otp}</h1>
                        </div>
                        <p style='color: #666;'>This OTP will expire in 2 minutes.</p>
                        <p>If you didn't request this password reset, please ignore this email.</p>
                        <hr style='border: 1px solid #eee; margin: 20px 0;'>
                        <p style='color: #999; font-size: 12px;'>This is an automated message, please do not reply.</p>
                    </div>";
                $mail->send();
                
                // Redirect to OTP page after sending
                header("Location: ../main_otp.html");
                exit();
            } catch (Exception $e) {
                $_SESSION['error'] = "Failed to send email. Please try again.";
                header("Location: ../main_resetpassword.html");
                exit();
            }
        } else {
            $_SESSION['error'] = "Failed to generate reset token. Please try again.";
            header("Location: ../main_resetpassword.html");
            exit();
        }
    } else {
        $_SESSION['error'] = "Email not found in our records.";
        header("Location: ../main_resetpassword.html");
        exit();
    }
} else {
    header("Location: ../main_resetpassword.html");
    exit();
}
?> 