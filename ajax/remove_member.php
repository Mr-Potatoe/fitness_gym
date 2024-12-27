<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = $conn->real_escape_string($_POST['member_id']);
    
    // First check if member exists and is not an admin
    $check_sql = "SELECT role FROM users WHERE id = $member_id";
    $result = $conn->query($check_sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot remove admin users']);
            exit;
        }
        
        // Soft delete the member by setting deleted_at timestamp
        $sql = "UPDATE users SET 
                status = 'inactive', 
                deleted_at = CURRENT_TIMESTAMP 
                WHERE id = $member_id AND role = 'member'";
                
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Member removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing member']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 