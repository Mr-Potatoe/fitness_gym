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
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['account_id']) || !isset($data['is_active'])) {
            throw new Exception('Missing required parameters');
        }
        
        $account_id = $conn->real_escape_string($data['account_id']);
        $is_active = $data['is_active'] ? 1 : 0;
        $user_id = $_SESSION['user_id'];
        
        // Update account status
        $sql = "UPDATE payment_accounts 
                SET is_active = $is_active,
                    updated_at = NOW()
                WHERE id = $account_id";
        
        if ($conn->query($sql)) {
            echo json_encode([
                'success' => true,
                'message' => $is_active ? 'Account activated successfully' : 'Account deactivated successfully'
            ]);
        } else {
            throw new Exception('Error updating account status: ' . $conn->error);
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