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
    
    // Check for active subscription
    $subscription_query = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
    $subscription_stmt = $conn->prepare($subscription_query);
    $subscription_stmt->bind_param('i', $member_id);
    $subscription_stmt->execute();
    $subscription_result = $subscription_stmt->get_result();
    
    if ($subscription_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Member cannot be verified without an active subscription.']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        $update_query = "UPDATE users 
                        SET verified = 1,
                            verified_at = CURRENT_TIMESTAMP,
                            verified_by = {$_SESSION['user_id']}
                        WHERE id = $member_id AND role = 'member'";
        
        if (!$conn->query($update_query)) {
            throw new Exception('Error verifying member');
        }
        
        $notify_sql = "INSERT INTO notifications (user_id, title, message, type) 
                      VALUES ($member_id, 
                             'Account Verified', 
                             'Your account has been verified by an administrator.', 
                             'account_verified')";
                             
        if (!$conn->query($notify_sql)) {
            throw new Exception('Error creating notification');
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Member verified successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error verifying member: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}