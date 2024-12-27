<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $role = $conn->real_escape_string($_POST['role']);
    
    // Update user as permanently deleted
    $sql = "UPDATE users SET 
            permanently_deleted = 1,
            deleted_at = CURRENT_TIMESTAMP 
            WHERE id = $user_id AND role = '$role'";
            
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Account has been permanently deleted'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error permanently deleting account'
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 