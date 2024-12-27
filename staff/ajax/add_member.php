<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['full_name'], $data['email'], $data['plan_id'], $data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Sanitize inputs
    $full_name = $conn->real_escape_string($data['full_name']);
    $email = $conn->real_escape_string($data['email']);
    $phone = $conn->real_escape_string($data['phone'] ?? '');
    $address = $conn->real_escape_string($data['address'] ?? '');
    $plan_id = $conn->real_escape_string($data['plan_id']);
    $payment_method = $conn->real_escape_string($data['payment_method']);
    $enable_online = $data['enable_online'] ?? false;
    $gcash_reference = $conn->real_escape_string($data['gcash_reference'] ?? '');
    $staff_notes = $conn->real_escape_string($data['staff_notes'] ?? '');
    
    // Check if email already exists
    $check_email = "SELECT id FROM users WHERE email = '$email'";
    $email_result = $conn->query($check_email);
    if ($email_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // If online access is enabled, check username and set password
    if ($enable_online) {
        $username = $conn->real_escape_string($data['username']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Check if username already exists
        $check_username = "SELECT id FROM users WHERE username = '$username'";
        $username_result = $conn->query($check_username);
        if ($username_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
    }
    
    try {
        $conn->begin_transaction();
        
        // Insert user
        $user_query = "INSERT INTO users (
            full_name, 
            email, 
            address, 
            role, 
            status, 
            subscription_status,
            created_at
        ) VALUES (
            '$full_name', 
            '$email', 
            '$address', 
            'member', 
            'active',
            'inactive',
            CURRENT_TIMESTAMP
        )";
        
        if ($enable_online) {
            $user_query .= ", username, password";
            $user_values .= ", '$username', '$password'";
        }
        
        $user_query .= ") VALUES ($user_values)";
        
        if (!$conn->query($user_query)) {
            throw new Exception('Error creating user');
        }
        
        $user_id = $conn->insert_id;
        
        // Get plan details
        $plan_query = "SELECT * FROM plans WHERE id = $plan_id";
        $plan_result = $conn->query($plan_query);
        $plan = $plan_result->fetch_assoc();
        
        // Create subscription with active status
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$plan['duration']} days"));
        
        $subscription_query = "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status) 
                              VALUES ($user_id, $plan_id, '$start_date', '$end_date', 'active')";
        
        if (!$conn->query($subscription_query)) {
            throw new Exception('Error creating subscription');
        }
        
        $subscription_id = $conn->insert_id;
        
        // Create payment record as paid
        $payment_query = "INSERT INTO payments (subscription_id, amount, payment_method, status, payment_date) 
                         VALUES ($subscription_id, {$plan['price']}, 'cash', 'paid', CURRENT_TIMESTAMP)";
        
        if (!$conn->query($payment_query)) {
            throw new Exception('Error creating payment record');
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Member added successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 