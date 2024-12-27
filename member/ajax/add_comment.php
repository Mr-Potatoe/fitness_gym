<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if member has active subscription
$member_id = $_SESSION['user_id'];
$subscription_check = "SELECT COUNT(*) as count 
                      FROM subscriptions 
                      WHERE user_id = $member_id 
                      AND status = 'active'";
$result = $conn->query($subscription_check);
if (!$result || $result->fetch_assoc()['count'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Active subscription required to comment']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = $conn->real_escape_string($_POST['announcement_id']);
    $comment = $conn->real_escape_string($_POST['comment']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert comment
        $sql = "INSERT INTO announcement_comments (announcement_id, user_id, comment) 
                VALUES ($announcement_id, $member_id, '$comment')";
        
        if (!$conn->query($sql)) {
            throw new Exception('Error adding comment');
        }
        
        // Get the new comment with user details
        $comment_id = $conn->insert_id;
        $query = "SELECT c.*, u.full_name, u.username 
                 FROM announcement_comments c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.id = $comment_id";
        $result = $conn->query($query);
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Error retrieving comment');
        }
        
        $comment = $result->fetch_assoc();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment' => [
                'id' => $comment['id'],
                'full_name' => $comment['full_name'],
                'comment' => nl2br(htmlspecialchars($comment['comment'])),
                'created_at' => date('M d, Y h:i A', strtotime($comment['created_at']))
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 