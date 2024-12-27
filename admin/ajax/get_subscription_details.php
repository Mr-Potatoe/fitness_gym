<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $subscription_id = $conn->real_escape_string($_GET['id']);
    
    // Get subscription details with related information
    $sql = "SELECT s.*, 
            u.username, u.full_name, u.email,
            p.name as plan_name, p.duration, p.description as plan_description,
            pm.payment_method, pm.status as payment_status, pm.created_at as payment_date
            FROM subscriptions s 
            JOIN users u ON s.user_id = u.id 
            JOIN plans p ON s.plan_id = p.id 
            LEFT JOIN payments pm ON s.id = pm.subscription_id
            WHERE s.id = $subscription_id";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $subscription = $result->fetch_assoc();
        
        // Format dates
        $subscription['start_date_formatted'] = date('F d, Y', strtotime($subscription['start_date']));
        $subscription['end_date_formatted'] = date('F d, Y', strtotime($subscription['end_date']));
        $subscription['payment_date_formatted'] = $subscription['payment_date'] 
            ? date('F d, Y h:i A', strtotime($subscription['payment_date'])) 
            : 'N/A';
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $subscription['id'],
                'member_name' => $subscription['full_name'],
                'member_email' => $subscription['email'],
                'username' => $subscription['username'],
                'plan_name' => $subscription['plan_name'],
                'plan_description' => $subscription['plan_description'],
                'duration' => $subscription['duration'],
                'amount' => $subscription['amount'],
                'start_date' => $subscription['start_date_formatted'],
                'end_date' => $subscription['end_date_formatted'],
                'status' => $subscription['status'],
                'payment_method' => $subscription['payment_method'] ?? 'N/A',
                'payment_status' => $subscription['payment_status'] ?? 'pending',
                'payment_date' => $subscription['payment_date_formatted']
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