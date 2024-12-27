<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle GET request to fetch announcement details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $announcement_id = $conn->real_escape_string($_GET['id']);
    $sql = "SELECT * FROM announcements WHERE id = $announcement_id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'data' => $result->fetch_assoc()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Announcement not found'
        ]);
    }
    exit;
}

// Handle POST request to update announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = $conn->real_escape_string($_POST['announcement_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    
    // Update announcement
    $sql = "UPDATE announcements 
            SET title = '$title', 
                content = '$content', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = $announcement_id";
    
    if ($conn->query($sql)) {
        // Update notifications for active subscribers
        $update_notif = "UPDATE notifications 
                        SET title = '$title', 
                            message = '$content' 
                        WHERE type = 'announcement' 
                        AND EXISTS (
                            SELECT 1 FROM announcements 
                            WHERE id = $announcement_id
                        )";
        $conn->query($update_notif);
        
        echo json_encode([
            'success' => true,
            'message' => 'Announcement updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating announcement'
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 