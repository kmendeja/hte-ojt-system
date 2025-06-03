<?php
// This script should be run via cron job once per day
// Example cron entry (run at midnight every day):
// 0 0 * * * php /path/to/cron_cleanup_notifications.php

require_once 'db_connect.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Delete notifications older than 30 days
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Query execute failed: " . $stmt->error);
    }

    $deleted_count = $stmt->affected_rows;
    
    // Log the cleanup
    $log_message = date('Y-m-d H:i:s') . " - Deleted $deleted_count notifications older than 30 days\n";
    file_put_contents(__DIR__ . '/notification_cleanup.log', $log_message, FILE_APPEND);

    echo "Successfully cleaned up old notifications\n";
    echo "Deleted $deleted_count notifications";

} catch (Exception $e) {
    $error_message = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/notification_cleanup.log', $error_message, FILE_APPEND);
    echo "Failed to clean up notifications: " . $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?> 