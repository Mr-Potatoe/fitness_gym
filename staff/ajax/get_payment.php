<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit;
}

try {
    $payment_id = $conn->real_escape_string($_GET['id']);
    
    $query = "SELECT p.*, 
              u.username, u.full_name,
              s.start_date, s.end_date,
              pl.name as plan_name, pl.duration_months,
              v.username as verifier_name
              FROM payments p
              JOIN subscriptions s ON p.subscription_id = s.id
              JOIN users u ON p.user_id = u.id
              JOIN plans pl ON s.plan_id = pl.id
              LEFT JOIN users v ON p.verified_by = v.id
              WHERE p.id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $payment = $result->fetch_assoc();
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
