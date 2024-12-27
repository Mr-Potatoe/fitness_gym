<?php
require_once '../../config/config.php';

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
        exit;
    }
    
    $announcement_id = $conn->real_escape_string($data['id']);
    
    $query = "SELECT * FROM announcements WHERE id = $announcement_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $announcement = $result->fetch_assoc();
        echo json_encode(['success' => true, 'announcement' => $announcement]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}