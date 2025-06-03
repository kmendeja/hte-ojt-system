<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Handle login POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        header("Location: ../index.html?error=invalid");
        exit();
    }

    // Prepare and execute user lookup
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // Username doesn't exist
        header("Location: ../index.html?error=notfound");
        exit();
    }

    $user = $result->fetch_assoc();

    // Check if account is locked
    if ($user['account_locked_until'] !== null) {
        header("Location: ../index.html?error=locked");
        exit();
    }

    // First try password_verify for hashed passwords
    if (password_verify($password, $user['password'])) {
        // Reset failed login attempts on successful login
        $resetStmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = ?");
        $resetStmt->bind_param("i", $user['user_id']);
        $resetStmt->execute();
        
        // Continue with successful login...
    }
    // Then try plain text comparison and migrate if matches
    else if ($password === $user['password']) {
        // Reset failed login attempts on successful login
        $resetStmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = ?");
        $resetStmt->bind_param("i", $user['user_id']);
        $resetStmt->execute();
        
        // Plain text password matched - migrate to hashed
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $user['user_id']);
        $updateStmt->execute();
    }
    else {
        // Invalid password - increment failed attempts
        $newAttempts = ($user['failed_login_attempts'] + 1);
        
        if ($newAttempts >= 4) {
            // Lock account (no time limit - must reset password to unlock)
            $updateStmt = $conn->prepare("UPDATE users SET failed_login_attempts = ?, account_locked_until = CURRENT_TIMESTAMP WHERE user_id = ?");
            $updateStmt->bind_param("ii", $newAttempts, $user['user_id']);
            $updateStmt->execute();
            
            header("Location: ../index.html?error=locked");
            exit();
        } else {
            // Just increment failed attempts
            $updateStmt = $conn->prepare("UPDATE users SET failed_login_attempts = ? WHERE user_id = ?");
            $updateStmt->bind_param("ii", $newAttempts, $user['user_id']);
            $updateStmt->execute();
            
            header("Location: ../index.html?error=invalid&attempts=" . (4 - $newAttempts));
            exit();
        }
    }

    // Optional: Check if account is disabled (if such column exists)
    if (isset($user['status']) && strtolower($user['status']) === 'disabled') {
        header("Location: ../index.html?error=disabled");
        exit();
    }

    // ✅ Successful login
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_id'] = $user['user_id'];

    // Load trainee-specific info
    if ($user['role'] === 'trainee') {
        $traineeStmt = $conn->prepare("SELECT * FROM trainees WHERE user_id = ?");
        $traineeStmt->bind_param("i", $user['user_id']);
        $traineeStmt->execute();
        $traineeResult = $traineeStmt->get_result();

        if ($traineeResult->num_rows === 1) {
            $trainee = $traineeResult->fetch_assoc();
            $_SESSION['trainee_id'] = $trainee['trainee_id'];
            $_SESSION['full_name'] = $trainee['full_name'];
            $_SESSION['course_section'] = $trainee['course_section'] ?? 'Default Section';
            $_SESSION['position'] = $trainee['position'] ?? 'Default Position';
            $_SESSION['organization'] = $trainee['organization'] ?? 'Default Organization';
        }
    }

    // Load coordinator-specific info
    if ($user['role'] === 'coordinator') {
        $coordStmt = $conn->prepare("SELECT coordinator_id, archived FROM coordinators WHERE user_id = ?");
        $coordStmt->bind_param("i", $user['user_id']);
        $coordStmt->execute();
        $coordResult = $coordStmt->get_result();
        if ($coordResult->num_rows === 1) {
            $coordinator = $coordResult->fetch_assoc();
            if ($coordinator['archived'] == 1) {
                header("Location: ../index.html?error=archived");
                exit();
            }
            $_SESSION['coordinator_id'] = $coordinator['coordinator_id'];
        }
    }

    // Redirect based on role
    switch ($user['role']) {
        case 'admin':
            header("Location: ../admin_1menu.html");
            break;
        case 'coordinator':
            header("Location: ../coordinator_1menu.html");
            break;
        case 'trainee':
            header("Location: ../trainee_1menu.html");
            break;
        default:
            header("Location: ../index.html?error=server");
            break;
    }
    exit();
} else {
    // If not a POST request
    header("Location: ../index.html?error=invalid");
    exit();
}
?>
