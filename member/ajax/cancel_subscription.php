<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subscription_id = isset($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : 0;
        $user_id = $_SESSION['user_id'];

        // Validate subscription
        $subscription_query = "SELECT s.*, p.status as payment_status 
                             FROM subscriptions s 
                             LEFT JOIN payments p ON s.id = p.subscription_id
                             WHERE s.id = $subscription_id 
                             AND s.user_id = $user_id 
                             AND (s.status = 'pending' OR p.status = 'pending')
                             AND s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $result = $conn->query($subscription_query);
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception('This subscription cannot be cancelled. It may be too old or already processed.');
        }

        $subscription = $result->fetch_assoc();

        // Start transaction
        $conn->begin_transaction();

        // Update subscription status
        $update_subscription = "UPDATE subscriptions 
                              SET status = 'cancelled', 
                                  cancelled_at = NOW() 
                              WHERE id = $subscription_id";
        
        if (!$conn->query($update_subscription)) {
            throw new Exception('Failed to cancel subscription');
        }

        // Update payment status if exists
        $update_payment = "UPDATE payments 
                          SET status = 'cancelled' 
                          WHERE subscription_id = $subscription_id";
        
        if (!$conn->query($update_payment)) {
            throw new Exception('Failed to update payment status');
        }

        // Delete payment proof if exists
        $payment_query = "SELECT payment_proof FROM payments WHERE subscription_id = $subscription_id";
        $payment_result = $conn->query($payment_query);
        
        if ($payment_result && $payment_result->num_rows > 0) {
            $payment = $payment_result->fetch_assoc();
            if (!empty($payment['payment_proof'])) {
                $file_path = '../../' . $payment['payment_proof'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Subscription cancelled successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction if there was an error
        if ($conn->connect_error === null) {
            $conn->rollback();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
