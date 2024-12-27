<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Subscription ID is required']);
    exit;
}

try {
    $subscription_id = $conn->real_escape_string($_GET['id']);
    
    $query = "SELECT s.*, 
              u.username, u.full_name, 
              p.name as plan_name, p.duration_months, p.price as amount,
              py.id as payment_id, py.status as payment_status, py.payment_method,
              py.payment_proof, py.verified_by, py.verified_at,
              v.username as verifier_name
              FROM subscriptions s 
              JOIN users u ON s.user_id = u.id 
              JOIN plans p ON s.plan_id = p.id 
              LEFT JOIN payments py ON s.id = py.subscription_id
              LEFT JOIN users v ON py.verified_by = v.id
              WHERE s.id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $subscription_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $subscription = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'subscription' => $subscription
        ]);
    } else {
        throw new Exception('Subscription not found');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
