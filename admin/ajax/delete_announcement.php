<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $announcement_id = $conn->real_escape_string($data['announcement_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related notifications first
        $delete_notif = "DELETE FROM notifications 
                        WHERE type = 'announcement' 
                        AND EXISTS (
                            SELECT 1 FROM announcements 
                            WHERE id = $announcement_id
                        )";
        $conn->query($delete_notif);
        
        // Delete the announcement
        $delete_announcement = "DELETE FROM announcements WHERE id = $announcement_id";
        if (!$conn->query($delete_announcement)) {
            throw new Exception('Error deleting announcement');
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Announcement deleted successfully'
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