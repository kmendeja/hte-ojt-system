<?php
require_once 'db_connect.php';

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

    echo "Successfully cleaned up old notifications\n";
    echo "Deleted " . $stmt->affected_rows . " notifications";

} catch (Exception $e) {
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