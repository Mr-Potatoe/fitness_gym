<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $conn->begin_transaction();
        
        switch ($data['action']) {
            case 'add':
                $title = $conn->real_escape_string($data['title']);
                $content = $conn->real_escape_string($data['content']);
                $staff_id = $_SESSION['user_id'];
                
                $sql = "INSERT INTO announcements (title, content, created_by) 
                        VALUES ('$title', '$content', $staff_id)";
                        
                if (!$conn->query($sql)) {
                    throw new Exception('Error creating announcement');
                }
                
                $announcement_id = $conn->insert_id;
                
                // Notify active members
                $notify_sql = "INSERT INTO notifications (user_id, title, message, type) 
                             SELECT u.id, 'New Announcement: $title', 
                                    SUBSTRING('$content', 1, 200), 'announcement'
                             FROM users u 
                             JOIN subscriptions s ON u.id = s.user_id 
                             WHERE u.role = 'member' 
                             AND s.status = 'active'";
                             
                if (!$conn->query($notify_sql)) {
                    throw new Exception('Error sending notifications');
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
                break;
                
            case 'edit':
                $id = $conn->real_escape_string($data['id']);
                $title = $conn->real_escape_string($data['title']);
                $content = $conn->real_escape_string($data['content']);
                $staff_id = $_SESSION['user_id'];
                
                // Staff can only edit their own announcements
                $sql = "UPDATE announcements 
                        SET title = '$title', 
                            content = '$content' 
                        WHERE id = $id 
                        AND created_by = $staff_id";
                        
                if (!$conn->query($sql)) {
                    throw new Exception('Error updating announcement');
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
                break;
                
            case 'delete':
                $id = $conn->real_escape_string($data['id']);
                $staff_id = $_SESSION['user_id'];
                
                // Staff can only delete their own announcements
                $sql = "DELETE FROM announcements 
                        WHERE id = $id 
                        AND created_by = $staff_id";
                
                if (!$conn->query($sql)) {
                    throw new Exception('Error deleting announcement');
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
                break;
                
            case 'get':
                $id = $conn->real_escape_string($data['id']);
                
                $sql = "SELECT a.*, u.full_name as author 
                        FROM announcements a
                        JOIN users u ON a.created_by = u.id 
                        WHERE a.id = $id";
                
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    $announcement = $result->fetch_assoc();
                    echo json_encode(['success' => true, 'data' => $announcement]);
                } else {
                    throw new Exception('Announcement not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 