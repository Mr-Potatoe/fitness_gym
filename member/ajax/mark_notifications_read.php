<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = $user_id 
            AND is_read = 0";
            
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating notifications']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 