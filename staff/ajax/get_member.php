<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $member_id = $conn->real_escape_string($_GET['id']);
    
    $query = "SELECT id, full_name, email, username 
              FROM users 
              WHERE id = $member_id AND role = 'member'";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $member = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'member' => $member
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Member not found'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 