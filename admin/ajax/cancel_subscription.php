<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $subscription_id = $conn->real_escape_string($data['subscription_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get subscription details
        $sub_query = "SELECT * FROM subscriptions WHERE id = $subscription_id";
        $sub_result = $conn->query($sub_query);
        
        if (!$sub_result || $sub_result->num_rows === 0) {
            throw new Exception('Subscription not found');
        }
        
        // Update subscription status
        $update_sub = "UPDATE subscriptions SET 
                      status = 'cancelled',
                      updated_at = CURRENT_TIMESTAMP 
                      WHERE id = $subscription_id";
        
        if (!$conn->query($update_sub)) {
            throw new Exception('Error updating subscription');
        }
        
        // Update payment status
        $update_payment = "UPDATE payments SET 
                          status = 'cancelled',
                          updated_at = CURRENT_TIMESTAMP 
                          WHERE subscription_id = $subscription_id";
        
        if (!$conn->query($update_payment)) {
            throw new Exception('Error updating payment');
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription cancelled successfully'
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