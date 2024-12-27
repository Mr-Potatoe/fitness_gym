<?php
require_once '../../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    if (!isset($_POST['plan_id'], $_POST['payment_method'])) {
        throw new Exception('Missing required fields');
    }

    $conn->begin_transaction();

    // Validate plan exists and is active
    $plan_stmt = $conn->prepare("
        SELECT * FROM plans 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $plan_stmt->bind_param('i', $_POST['plan_id']);
    $plan_stmt->execute();
    $plan = $plan_stmt->get_result()->fetch_assoc();

    if (!$plan) {
        throw new Exception('Invalid plan selected');
    }

    // Check for existing active subscription
    $check_stmt = $conn->prepare("
        SELECT id FROM subscriptions 
        WHERE user_id = ? 
        AND status IN ('active', 'pending') 
        AND end_date >= CURRENT_DATE()
    ");
    $user_id = $_SESSION['user_id'];
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('You already have an active or pending subscription');
    }

    // Validate payment method if not cash
    if ($_POST['payment_method'] !== 'cash') {
        $payment_stmt = $conn->prepare("
            SELECT id FROM payment_accounts 
            WHERE account_type = ? 
            AND is_active = 1 
            AND deleted_at IS NULL
        ");
        $payment_stmt->bind_param('s', $_POST['payment_method']);
        $payment_stmt->execute();
        
        if ($payment_stmt->get_result()->num_rows === 0) {
            throw new Exception('Invalid payment method selected');
        }

        // Verify payment proof is uploaded
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Payment proof is required for online payments');
        }

        // Verify reference number for gcash payments
        if ($_POST['payment_method'] === 'gcash' && empty($_POST['reference_number'])) {
            throw new Exception('Reference number is required for GCash payments');
        }
    }

    // Calculate subscription dates
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));

    // Get reference number based on payment method
    $reference_number = null;
    if ($_POST['payment_method'] === 'gcash') {
        $reference_number = $_POST['reference_number'];
    } elseif ($_POST['payment_method'] === 'cash') {
        $reference_number = 'CASH-' . time() . '-' . rand(1000, 9999);
    } else {
        $reference_number = 'BANK-' . time() . '-' . rand(1000, 9999);
    }

    // Create subscription
    $sub_stmt = $conn->prepare("
        INSERT INTO subscriptions (
            user_id, plan_id, start_date, end_date, 
            status, amount, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    // Set subscription status to pending for all new subscriptions
    $subscription_status = 'pending';
    $sub_stmt->bind_param('iisssd', $user_id, $_POST['plan_id'], $start_date, $end_date, $subscription_status, $plan['price']);
    
    if (!$sub_stmt->execute()) {
        throw new Exception('Failed to create subscription: ' . $conn->error);
    }
    $subscription_id = $conn->insert_id;

    // Handle payment proof upload for online payments
    $payment_proof = null;
    if ($_POST['payment_method'] !== 'cash') {
        $file = $_FILES['payment_proof'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, PDF');
        }

        $filename = 'payment_' . $subscription_id . '_' . time() . '.' . $ext;
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
    $payment_stmt = $conn->prepare("
        INSERT INTO payments (
            user_id, subscription_id, amount, payment_method,
            payment_proof, status, reference_number, payment_date,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ");
    
    // Always set initial payment status to pending
    $payment_status = 'pending';
    
    // Ensure payment method is lowercase
    $payment_method = strtolower($_POST['payment_method']);
    
    $payment_stmt->bind_param(
        'iidssss',
        $user_id,
        $subscription_id,
        $plan['price'],
        $payment_method,
        $payment_proof,
        $payment_status,
        $reference_number
    );
    
    if (!$payment_stmt->execute()) {
        throw new Exception('Failed to create payment record: ' . $conn->error);
    }

    $conn->commit();
    
    $message = $_POST['payment_method'] === 'cash' 
        ? 'Subscription created successfully. Please proceed to the gym to make the cash payment.'
        : 'Subscription created successfully. Please wait for payment verification.';
        
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    error_log("Subscription error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
