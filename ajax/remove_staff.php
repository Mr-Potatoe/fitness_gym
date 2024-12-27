<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_id'])) {
    $staff_id = $conn->real_escape_string($_POST['staff_id']);
    
    // First check if staff exists
    $check_sql = "SELECT id FROM users WHERE id = $staff_id AND role = 'staff'";
    $result = $conn->query($check_sql);
    
    if ($result && $result->num_rows > 0) {
        // Soft delete the staff member
        $sql = "UPDATE users SET 
                status = 'inactive', 
                deleted_at = CURRENT_TIMESTAMP 
                WHERE id = $staff_id AND role = 'staff'";
                
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Staff removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing staff']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 