<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $payment_id = $conn->real_escape_string($data['payment_id']);
    $action = $conn->real_escape_string($data['action']); // 'verify' or 'reject'
    
    try {
        $conn->begin_transaction();
        
        // Get payment and subscription details
        $query = "SELECT p.*, s.user_id, s.plan_id, s.id as subscription_id 
                 FROM payments p 
                 JOIN subscriptions s ON p.subscription_id = s.id 
                 WHERE p.id = $payment_id";
        $result = $conn->query($query);
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Payment not found');
        }
        
        $payment = $result->fetch_assoc();
        
        if ($payment['payment_method'] !== 'cash' && empty($payment['payment_proof'])) {
            throw new Exception('Cannot verify online payment without payment proof');
        }
        
        if ($payment['verified']) {
            throw new Exception('Payment has already been verified');
        }
        
        if ($action === 'verify') {
            // Update payment status
            $update_payment = "UPDATE payments 
                             SET status = 'paid',
                                 verified = 1,
                                 verified_at = CURRENT_TIMESTAMP,
                                 verified_by = {$_SESSION['user_id']}
                             WHERE id = $payment_id";
            
            if (!$conn->query($update_payment)) {
                throw new Exception('Error updating payment');
            }
            
            // Update subscription status
            $update_sub = "UPDATE subscriptions 
                          SET status = 'active' 
                          WHERE id = {$payment['subscription_id']}";
            
            if (!$conn->query($update_sub)) {
                throw new Exception('Error updating subscription');
            }
            
            // Create notification for member
            $notify_sql = "INSERT INTO notifications (user_id, title, message, type) 
                          VALUES ({$payment['user_id']}, 
                                 'Payment Verified', 
                                 'Your payment has been verified. Your subscription is now active.', 
                                 'payment_verified')";
            
            if (!$conn->query($notify_sql)) {
                throw new Exception('Error creating notification');
            }
            
            $message = 'Payment verified successfully';
        } else {
            // Reject payment
            $update_payment = "UPDATE payments 
                             SET status = 'rejected',
                                 verified = 0,
                                 verified_at = CURRENT_TIMESTAMP,
                                 verified_by = {$_SESSION['user_id']}
                             WHERE id = $payment_id";
            
            if (!$conn->query($update_payment)) {
                throw new Exception('Error updating payment');
            }
            
            // Update subscription status
            $update_sub = "UPDATE subscriptions 
                          SET status = 'pending' 
                          WHERE id = {$payment['subscription_id']}";
            
            if (!$conn->query($update_sub)) {
                throw new Exception('Error updating subscription');
            }
            
            // Create notification for member
            $notify_sql = "INSERT INTO notifications (user_id, title, message, type) 
                          VALUES ({$payment['user_id']}, 
                                 'Payment Rejected', 
                                 'Your payment proof was rejected. Please submit a valid payment proof.', 
                                 'payment_rejected')";
            
            if (!$conn->query($notify_sql)) {
                throw new Exception('Error creating notification');
            }
            
            $message = 'Payment rejected successfully';
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => $message
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