<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['payment_id'], $data['action'])) {
        throw new Exception('Payment ID and action are required');
    }

    $payment_id = $conn->real_escape_string($data['payment_id']);
    $action = $conn->real_escape_string($data['action']);
    $verification_notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }

    // Start transaction
    $conn->begin_transaction();

    // Get payment and subscription details
    $query = "SELECT p.*, s.id as subscription_id, s.status as subscription_status,
              pl.duration_months
              FROM payments p
              JOIN subscriptions s ON p.subscription_id = s.id
              JOIN plans pl ON s.plan_id = pl.id
              WHERE p.id = ? AND p.status = 'pending'";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        throw new Exception('Payment not found or already processed');
    }

    $payment = $result->fetch_assoc();

    // Set new statuses
    $payment_status = $action === 'approve' ? 'paid' : 'rejected';
    $subscription_status = $action === 'approve' ? 'active' : 'cancelled';

    // Update payment
    $update_payment = $conn->prepare("
        UPDATE payments 
        SET status = ?,
            verified = 1,
            verified_by = ?,
            verified_at = NOW(),
            verification_notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_payment->bind_param('sisi', $payment_status, $_SESSION['user_id'], $verification_notes, $payment_id);

    if (!$update_payment->execute()) {
        throw new Exception('Failed to update payment status');
    }

    // Update subscription
    $update_subscription = $conn->prepare("
        UPDATE subscriptions 
        SET status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_subscription->bind_param('si', $subscription_status, $payment['subscription_id']);

    if (!$update_subscription->execute()) {
        throw new Exception('Failed to update subscription status');
    }

    // If approved, update subscription dates
    if ($action === 'approve') {
        $duration = (int)$payment['duration_months'];
        $update_dates = $conn->prepare("
            UPDATE subscriptions 
            SET start_date = CURRENT_DATE(),
                end_date = DATE_ADD(CURRENT_DATE(), INTERVAL ? MONTH)
            WHERE id = ?
        ");
        $update_dates->bind_param('ii', $duration, $payment['subscription_id']);

        if (!$update_dates->execute()) {
            throw new Exception('Failed to update subscription dates');
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 
            'Payment verified and subscription activated successfully' : 
            'Payment rejected and subscription cancelled'
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}