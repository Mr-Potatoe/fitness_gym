<?php
require_once '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        // Check if user is logged in and is a member
        if (!isLoggedIn() || !hasRole('member')) {
            throw new Exception('Unauthorized access');
        }

        $user_id = $_SESSION['user_id'];
        $plan_id = $_POST['plan_id'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_notes = isset($_POST['payment_notes']) ? $conn->real_escape_string($_POST['payment_notes']) : '';

        // Validate inputs
        if (empty($plan_id) || empty($payment_method)) {
            throw new Exception('Missing required fields');
        }

        // Check if plan exists
        $plan_query = "SELECT * FROM plans WHERE id = ? AND deleted_at IS NULL";
        $plan_stmt = $conn->prepare($plan_query);
        $plan_stmt->bind_param('i', $plan_id);
        $plan_stmt->execute();
        $plan = $plan_stmt->get_result()->fetch_assoc();

        if (!$plan) {
            throw new Exception('Invalid plan selected');
        }

        // Check for active subscription
        $active_sub_query = "SELECT * FROM subscriptions 
                            WHERE user_id = $user_id 
                            AND status = 'active'
                            AND end_date >= CURRENT_DATE()";
        $active_sub_result = $conn->query($active_sub_query);
        
        if ($active_sub_result->num_rows > 0) {
            throw new Exception('You already have an active subscription');
        }

        // Start transaction
        $conn->begin_transaction();

        // Create subscription
        $subscription_query = "INSERT INTO subscriptions (user_id, plan_id, status, created_at, updated_at) 
                             VALUES (?, ?, ?, NOW(), NOW())";
        $subscription_stmt = $conn->prepare($subscription_query);
        $initial_status = ($payment_method === 'cash') ? 'pending' : 'pending_verification';
        $subscription_stmt->bind_param('iis', $user_id, $plan_id, $initial_status);
        $subscription_stmt->execute();
        $subscription_id = $conn->insert_id;

        // Handle payment proof for online payments
        $payment_proof = null;
        if ($payment_method === 'online') {
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Payment proof is required for online payments');
            }

            $file = $_FILES['payment_proof'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF files are allowed');
            }

            if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception('File is too large. Maximum size is 5MB');
            }

            // Generate unique filename
            $filename = uniqid('payment_') . '.' . $file_ext;
            $upload_dir = '../../uploads/payments/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                throw new Exception('Failed to upload payment proof');
            }

            $payment_proof = $filename;
        }

        // Create payment record
        $payment_query = "INSERT INTO payments (subscription_id, user_id, amount, payment_method, payment_proof, payment_notes, status, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())";
        $payment_stmt = $conn->prepare($payment_query);
        $payment_stmt->bind_param('iidssss', $subscription_id, $user_id, $plan['price'], $payment_method, $payment_proof, $payment_notes);
        $payment_stmt->execute();

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Subscription request submitted successfully! ' . 
                        ($payment_method === 'cash' ? 'Please proceed to the front desk for payment.' : 'Please wait for payment verification.')
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}