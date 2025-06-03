<?php
session_start();
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the OTP from the form, trimmed
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $_SESSION['error'] = "Please enter the OTP code.";
        header("Location: ../main_otp.html");
        exit();
    }

    // Get user_id from session
    $user_id = $_SESSION["reset_user_id"] ?? null;
    
    if (!$user_id) {
        $_SESSION['error'] = "Session expired. Please try again.";
        header("Location: ../main_resetpassword.html");
        exit();
    }

    // Check if OTP exists and is valid
    $sql = "SELECT * FROM password_reset_tokens 
            WHERE user_id = ? AND token = ? 
            AND expires_at > NOW() 
            AND used = 0 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: ../main_otp.html");
        exit();
    }
    $stmt->bind_param("is", $user_id, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Mark OTP as used
        $update_sql = "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND token = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("is", $user_id, $otp);
        $update_stmt->execute();

        // Set session variable to indicate OTP is verified
        $_SESSION['otp_verified'] = true;
        
        // Redirect to new password page
        header("Location: ../main_newpassword.html");
        exit();
    } else {
        $_SESSION['error'] = "Invalid or expired OTP. Please try again.";
        header("Location: ../main_otp.html");
        exit();
    }
} else {
    // If not POST request, redirect to reset password page
    header("Location: ../main_resetpassword.html");
    exit();
}
?> 