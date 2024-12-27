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
        
        $subscription_id = (int)$data['subscription_id'];
        if ($subscription_id <= 0) {
            throw new Exception('Invalid subscription ID');
        }
        
        // Get subscription details
        $stmt = $conn->prepare("
            SELECT s.*, p.duration_months, p.price
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $subscription_id);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();
        
        if (!$subscription) {
            throw new Exception('Subscription not found');
        }
        
        switch ($data['action']) {
            case 'verify':
                if ($subscription['status'] !== 'pending') {
                    throw new Exception('Can only verify pending subscriptions');
                }
                
                // Update subscription status
                $stmt = $conn->prepare("
                    UPDATE subscriptions 
                    SET status = 'active',
                        verified_at = NOW(),
                        verified_by = ?
                    WHERE id = ?
                ");
                
                $user_id = $_SESSION['user_id'];
                $stmt->bind_param("ii", $user_id, $subscription_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to verify subscription');
                }
                
                // Create a payment record
                $stmt = $conn->prepare("
                    INSERT INTO payments (
                        subscription_id, user_id, amount,
                        payment_method, reference_number,
                        status, payment_date, verified_by,
                        verified_at
                    ) VALUES (?, ?, ?, 'admin', ?, 'verified', NOW(), ?, NOW())
                ");
                
                $reference = 'ADM-' . time() . '-' . $subscription_id;
                
                $stmt->bind_param(
                    "iidsi",
                    $subscription_id,
                    $subscription['user_id'],
                    $subscription['price'],
                    $reference,
                    $user_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create payment record');
                }
                
                $message = 'Subscription verified successfully';
                break;
                
            case 'delete':
                if ($subscription['status'] === 'active') {
                    throw new Exception('Cannot delete active subscriptions');
                }
                
                // Delete related payments first
                $stmt = $conn->prepare("DELETE FROM payments WHERE subscription_id = ?");
                $stmt->bind_param("i", $subscription_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete payment records');
                }
                
                // Delete subscription
                $stmt = $conn->prepare("DELETE FROM subscriptions WHERE id = ?");
                $stmt->bind_param("i", $subscription_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete subscription');
                }
                
                $message = 'Subscription deleted successfully';
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
