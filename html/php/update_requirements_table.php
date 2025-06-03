<?php
require_once 'db_connect.php';

try {
    // Start transaction
    $conn->begin_transaction();

    // 1. Add position column
    $sql = "ALTER TABLE requirements ADD COLUMN position INT AFTER requirement_type";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to add position column: " . $conn->error);
    }

    // 2. Update position values based on current display_order
    $sql = "UPDATE requirements r1 
            JOIN (
                SELECT id, requirement_type, 
                       ROW_NUMBER() OVER (PARTITION BY requirement_type ORDER BY display_order, id) as new_position
                FROM requirements
            ) r2 ON r1.id = r2.id
            SET r1.position = r2.new_position";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to update positions: " . $conn->error);
    }

    // 3. Make position column NOT NULL
    $sql = "ALTER TABLE requirements MODIFY position INT NOT NULL";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to make position NOT NULL: " . $conn->error);
    }

    // 4. Drop display_order column
    $sql = "ALTER TABLE requirements DROP COLUMN display_order";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to drop display_order column: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();
    echo "Requirements table updated successfully!";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error updating requirements table: " . $e->getMessage();
} finally {
    $conn->close();
}
?> 