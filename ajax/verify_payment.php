<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$payment_id = $conn->real_escape_string($data['payment_id']);
$action = $conn->real_escape_string($data['action']);

if ($action === 'verify') {
    $status = 'verified';
} else if ($action === 'reject') {
    $status = 'rejected';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Update payment status
$sql = "UPDATE payments SET 
        status = '$status', 
        verified_by = {$_SESSION['user_id']}, 
        updated_at = NOW() 
        WHERE id = $payment_id";

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 