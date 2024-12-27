<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $subscription_id = $conn->real_escape_string($_GET['id']);
    $member_id = $_SESSION['user_id'];
    
    // Get subscription details with related information
    $sql = "SELECT s.*, 
            p.name as plan_name, p.duration, p.price,
            pay.status as payment_status, 
            pay.payment_method,
            pay.payment_proof,
            pay.payment_notes,
            pay.created_at as payment_date
            FROM subscriptions s 
            JOIN plans p ON s.plan_id = p.id 
            LEFT JOIN payments pay ON s.id = pay.subscription_id
            WHERE s.id = $subscription_id 
            AND s.user_id = $member_id";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $subscription = $result->fetch_assoc();
        
        // Format dates
        $subscription['start_date'] = date('F d, Y', strtotime($subscription['start_date']));
        $subscription['end_date'] = date('F d, Y', strtotime($subscription['end_date']));
        $subscription['payment_date'] = $subscription['payment_date'] 
            ? date('F d, Y h:i A', strtotime($subscription['payment_date'])) 
            : null;
        
        // Add payment proof URL if exists
        if ($subscription['payment_proof']) {
            $subscription['payment_proof_url'] = SITE_URL . '/uploads/payments/' . $subscription['payment_proof'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $subscription['id'],
                'plan_name' => $subscription['plan_name'],
                'duration' => $subscription['duration'],
                'amount' => $subscription['price'],
                'start_date' => $subscription['start_date'],
                'end_date' => $subscription['end_date'],
                'status' => ucfirst($subscription['status']),
                'payment_method' => $subscription['payment_method'],
                'payment_status' => $subscription['payment_status'],
                'payment_date' => $subscription['payment_date'],
                'payment_proof' => $subscription['payment_proof'],
                'payment_proof_url' => $subscription['payment_proof_url'] ?? null,
                'payment_notes' => $subscription['payment_notes']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Subscription not found'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 