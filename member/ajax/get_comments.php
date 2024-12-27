<?php
require_once '../../config/config.php';

if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['announcement_id'])) {
    $announcement_id = $conn->real_escape_string($_GET['announcement_id']);
    
    $query = "SELECT c.*, u.full_name 
              FROM announcement_comments c
              JOIN users u ON c.user_id = u.id
              WHERE c.announcement_id = $announcement_id
              ORDER BY c.created_at DESC";
    $comments = $conn->query($query);
    
    $html = '';
    while ($comment = $comments->fetch_assoc()) {
        $html .= '<div class="comment-item">';
        $html .= '<div class="comment-meta">';
        $html .= '<strong>' . htmlspecialchars($comment['full_name']) . '</strong> • ';
        $html .= '<span class="grey-text">' . date('M j, Y g:i A', strtotime($comment['created_at'])) . '</span>';
        $html .= '</div>';
        $html .= '<div class="comment-content">' . nl2br(htmlspecialchars($comment['comment'])) . '</div>';
        $html .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html ?: '<p class="center-align grey-text">No comments yet.</p>'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 