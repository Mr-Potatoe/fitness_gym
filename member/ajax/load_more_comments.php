<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = $conn->real_escape_string($_POST['announcement_id']);
    $offset = $conn->real_escape_string($_POST['offset']);
    
    $comments_query = "SELECT c.*, u.full_name, u.username 
                      FROM announcement_comments c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.announcement_id = $announcement_id 
                      ORDER BY c.created_at DESC 
                      LIMIT 5 OFFSET $offset";
    $comments = $conn->query($comments_query);
    
    $comments_html = '';
    while ($comment = $comments->fetch_assoc()) {
        $comments_html .= '<div class="comment-item" id="comment-' . $comment['id'] . '">';
        $comments_html .= '<div class="comment-header">';
        $comments_html .= '<strong>' . htmlspecialchars($comment['full_name']) . '</strong>';
        $comments_html .= '<small class="grey-text">' . date('M d, Y h:i A', strtotime($comment['created_at'])) . '</small>';
        
        if ($comment['user_id'] == $_SESSION['user_id']) {
            $comments_html .= '<div class="comment-actions right">';
            $comments_html .= '<button class="btn-flat blue-text" onclick="editComment(' . $comment['id'] . ', \'' . addslashes($comment['comment']) . '\')">';
            $comments_html .= '<i class="material-icons tiny">edit</i></button>';
            $comments_html .= '<button class="btn-flat red-text" onclick="deleteComment(' . $comment['id'] . ')">';
            $comments_html .= '<i class="material-icons tiny">delete</i></button>';
            $comments_html .= '</div>';
        }
        
        $comments_html .= '</div>';
        $comments_html .= '<p class="comment-text">' . nl2br(htmlspecialchars($comment['comment'])) . '</p>';
        $comments_html .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $comments_html,
        'has_more' => $comments->num_rows === 5
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 