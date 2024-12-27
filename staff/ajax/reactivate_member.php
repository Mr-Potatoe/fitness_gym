<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
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
        
        // Update user status to active
        $update_query = "UPDATE users 
                        SET status = 'active',
                            reactivated_at = CURRENT_TIMESTAMP,
                            reactivated_by = {$_SESSION['user_id']}
                        WHERE id = $member_id AND role = 'member'";
        
        if ($conn->query($update_query)) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Member reactivated successfully'
            ]);
        } else {
            throw new Exception('Error reactivating member');
        }
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