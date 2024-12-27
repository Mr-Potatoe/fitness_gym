<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $conn->begin_transaction();
        
        $payment_id = (int)$data['payment_id'];
        if ($payment_id <= 0) {
            throw new Exception('Invalid payment ID');
        }
        
        // Get payment and subscription details
        $stmt = $conn->prepare("
            SELECT p.*, s.id as subscription_id, s.status as subscription_status,
                   s.plan_id, pl.duration_months, pl.price
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            JOIN plans pl ON s.plan_id = pl.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        switch ($data['action']) {
            case 'verify':
                if ($payment['status'] !== 'pending') {
                    throw new Exception('Can only verify pending payments');
                }
                
                // Update payment status
                $stmt = $conn->prepare("
                    UPDATE payments 
                    SET status = 'verified',
                        verified_at = NOW(),
                        verified_by = ?
                    WHERE id = ?
                ");
                
                $user_id = $_SESSION['user_id'];
                $stmt->bind_param("ii", $user_id, $payment_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to verify payment');
                }
                
                // Update subscription status if payment is verified
                if ($payment['subscription_status'] === 'pending') {
                    $stmt = $conn->prepare("
                        UPDATE subscriptions 
                        SET status = 'active',
                            start_date = CURDATE(),
                            end_date = DATE_ADD(CURDATE(), INTERVAL ? MONTH)
                        WHERE id = ?
                    ");
                    
                    $stmt->bind_param("ii", $payment['duration_months'], $payment['subscription_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to update subscription status');
                    }
                }
                
                $message = 'Payment verified and subscription activated successfully';
                break;
                
            case 'reject':
                if ($payment['status'] !== 'pending') {
                    throw new Exception('Can only reject pending payments');
                }
                
                // Update payment status
                $stmt = $conn->prepare("
                    UPDATE payments 
                    SET status = 'rejected',
                        verified_at = NOW(),
                        verified_by = ?,
                        rejection_reason = ?
                    WHERE id = ?
                ");
                
                $user_id = $_SESSION['user_id'];
                $rejection_reason = $data['rejection_reason'] ?? 'Payment rejected by admin';
                
                $stmt->bind_param("isi", $user_id, $rejection_reason, $payment_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to reject payment');
                }
                
                // Update subscription status if payment is rejected
                if ($payment['subscription_status'] === 'pending') {
                    $stmt = $conn->prepare("
                        UPDATE subscriptions 
                        SET status = 'canceled'
                        WHERE id = ?
                    ");
                    
                    $stmt->bind_param("i", $payment['subscription_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to update subscription status');
                    }
                }
                
                $message = 'Payment rejected and subscription canceled successfully';
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
