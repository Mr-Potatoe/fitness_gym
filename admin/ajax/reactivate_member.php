<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['member_id'])) {
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        exit;
    }
    
    $member_id = $conn->real_escape_string($data['member_id']);
    
    try {
        $conn->begin_transaction();
        
        // Reactivate the user
        $update_user = "UPDATE users 
                       SET status = 'active',
                           deleted_at = NULL 
                       WHERE id = $member_id AND role = 'member'";
        
        if (!$conn->query($update_user)) {
            throw new Exception('Error reactivating member');
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Member reactivated successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 