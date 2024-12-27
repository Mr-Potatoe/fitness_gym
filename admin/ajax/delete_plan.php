<?php
require_once '../../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $plan_id = $conn->real_escape_string($data['plan_id']);
    
    // Check if plan has active subscriptions
    $check_sql = "SELECT COUNT(*) as count FROM subscriptions 
                  WHERE plan_id = $plan_id AND status = 'active'";
    $result = $conn->query($check_sql);
    $active_subs = $result->fetch_assoc()['count'];
    
    if ($active_subs > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete plan with active subscriptions'
        ]);
        exit;
    }
    
    // Soft delete the plan
    $sql = "UPDATE plans SET deleted_at = CURRENT_TIMESTAMP WHERE id = $plan_id";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Plan deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 