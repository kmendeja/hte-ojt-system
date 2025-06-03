<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if OTP was verified
    if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
        $_SESSION['error'] = "Please verify your OTP first.";
        header("Location: ../main_otp.html");
        exit();
    }

    // Get the new password and confirmation
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: ../main_newpassword.html");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: ../main_newpassword.html");
        exit();
    }

    // Password strength validation
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: ../main_newpassword.html");
        exit();
    }

    // Get user_id from session
    $user_id = $_SESSION["reset_user_id"] ?? null;
    
    if (!$user_id) {
        $_SESSION['error'] = "Session expired. Please try again.";
        header("Location: ../main_resetpassword.html");
        exit();
    }

    // Hash the new password using PHP's password_hash function
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update the password in the database with the hashed version
    $sql = "UPDATE users SET password = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Clear all reset-related session variables
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['otp_verified']);
        
        // Set success message
        $_SESSION['success'] = "Password has been updated successfully. Please login with your new password.";
        
        // Redirect to login page
        header("Location: ../index.html");
        exit();
    } else {
        if ($stmt->error) {
            $_SESSION['error'] = "Failed to update password. Please try again.";
        }
        $_SESSION['error'] = "Failed to update password. Please try again.";
        header("Location: ../main_newpassword.html");
        exit();
    }
} else {
    // If not POST request, redirect to reset password page
    header("Location: ../main_resetpassword.html");
    exit();
}
?> 