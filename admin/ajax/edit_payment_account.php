<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty($_POST['id']) || empty($_POST['account_type']) || empty($_POST['account_name']) || empty($_POST['account_number'])) {
            throw new Exception('All fields are required');
        }

        // Prepare statement
        $stmt = $conn->prepare("UPDATE payment_accounts SET account_type = ?, account_name = ?, account_number = ? WHERE id = ?");
        $stmt->bind_param("sssi", $_POST['account_type'], $_POST['account_name'], $_POST['account_number'], $_POST['id']);
        
        // Execute
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment account updated successfully'
            ]);
        } else {
            throw new Exception('Error updating payment account: ' . $conn->error);
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