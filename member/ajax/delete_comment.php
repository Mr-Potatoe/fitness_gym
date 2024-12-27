<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $comment_id = $conn->real_escape_string($data['comment_id']);
    $user_id = $_SESSION['user_id'];
    
    // Check if comment belongs to user
    $check_sql = "SELECT id FROM announcement_comments 
                  WHERE id = $comment_id AND user_id = $user_id";
    if ($conn->query($check_sql)->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to delete this comment']);
        exit;
    }
    
    $sql = "DELETE FROM announcement_comments WHERE id = $comment_id";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting comment'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 