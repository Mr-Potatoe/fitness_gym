<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $payment_id = (int)$_GET['id'];
        if ($payment_id <= 0) {
            throw new Exception('Invalid payment ID');
        }
        
        $stmt = $conn->prepare("
            SELECT p.*, 
                   u.full_name as member_name,
                   u.email as member_email,
                   s.start_date,
                   s.end_date,
                   s.status as subscription_status,
                   pl.name as plan_name,
                   pl.duration_months,
                   pl.price as amount,
                   CONCAT(admin.full_name) as verified_by_name
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN plans pl ON s.plan_id = pl.id
            LEFT JOIN users admin ON p.verified_by = admin.id
            WHERE p.id = ?
        ");
        
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $payment = $result->fetch_assoc()) {
            // Format dates
            $payment['payment_date'] = $payment['payment_date'] ? 
                date('F j, Y g:i A', strtotime($payment['payment_date'])) : 'N/A';
            $payment['verified_at'] = $payment['verified_at'] ? 
                date('F j, Y g:i A', strtotime($payment['verified_at'])) : null;
            
            echo json_encode([
                'success' => true,
                'payment' => $payment
            ]);
        } else {
            throw new Exception('Payment not found');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}