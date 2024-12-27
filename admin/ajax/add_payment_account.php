<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token']);
        exit;
    }

    // Validate input
    if (empty($_POST['account_type']) || empty($_POST['account_name']) || empty($_POST['account_number'])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    try {
        // Prepare statement
        $stmt = $conn->prepare("INSERT INTO payment_accounts (account_type, account_name, account_number, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $_POST['account_type'], $_POST['account_name'], $_POST['account_number'], $_SESSION['user_id']);
        
        // Execute
        if ($stmt->execute()) {
            // Log the action
            $action = "Added new payment account: {$_POST['account_name']} ({$_POST['account_type']})";
            logAdminAction($_SESSION['user_id'], 'payment_account', $stmt->insert_id, $action);

            echo json_encode([
                'success' => true,
                'message' => 'Payment account added successfully'
            ]);
        } else {
            throw new Exception('Failed to add payment account');
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
        'message' => 'Invalid request method'
    ]);
}