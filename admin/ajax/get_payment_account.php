<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $account_id = $conn->real_escape_string($_GET['id']);
        
        // Get account details
        $sql = "SELECT * FROM payment_accounts WHERE id = $account_id AND deleted_at IS NULL";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        if ($result->num_rows === 0) {
            throw new Exception('Account not found');
        }
        
        $account = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'account' => [
                'id' => (int)$account['id'],
                'account_type' => $account['account_type'],
                'account_name' => $account['account_name'],
                'account_number' => $account['account_number'],
                'is_active' => (bool)$account['is_active']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}