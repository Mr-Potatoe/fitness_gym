<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $conn->begin_transaction();
        
        switch ($data['action']) {
            case 'add':
                // Validate inputs
                $name = trim($data['name']);
                if (empty($name)) {
                    throw new Exception('Plan name is required');
                }
                
                $duration_months = (int)$data['duration_months'];
                if ($duration_months < 1 || $duration_months > 36) {
                    throw new Exception('Duration must be between 1 and 36 months');
                }
                
                $price = (float)$data['price'];
                if ($price < 0) {
                    throw new Exception('Price cannot be negative');
                }
                
                $features = array_filter(array_map('trim', $data['features']));
                
                // Use prepared statement
                $stmt = $conn->prepare("
                    INSERT INTO plans (
                        name, duration_months, price, features, 
                        created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $features_json = json_encode($features);
                $created_by = $_SESSION['user_id'];
                
                $stmt->bind_param(
                    "sidsi",
                    $name,
                    $duration_months,
                    $price,
                    $features_json,
                    $created_by
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create plan: ' . $stmt->error);
                }
                break;
                
            case 'edit':
                // Validate inputs
                $id = (int)$data['id'];
                if ($id <= 0) {
                    throw new Exception('Invalid plan ID');
                }
                
                $name = trim($data['name']);
                if (empty($name)) {
                    throw new Exception('Plan name is required');
                }
                
                $duration_months = (int)$data['duration_months'];
                if ($duration_months < 1 || $duration_months > 36) {
                    throw new Exception('Duration must be between 1 and 36 months');
                }
                
                $price = (float)$data['price'];
                if ($price < 0) {
                    throw new Exception('Price cannot be negative');
                }
                
                $features = array_filter(array_map('trim', $data['features']));
                
                // Check if plan exists and has no active subscriptions
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM subscriptions 
                    WHERE plan_id = ? AND status = 'active'
                ");
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot modify plan: Active subscriptions exist');
                }
                
                // Use prepared statement for update
                $stmt = $conn->prepare("
                    UPDATE plans 
                    SET name = ?,
                        duration_months = ?,
                        price = ?,
                        features = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $features_json = json_encode($features);
                
                $stmt->bind_param(
                    "sidsi",
                    $name,
                    $duration_months,
                    $price,
                    $features_json,
                    $id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update plan: ' . $stmt->error);
                }
                break;
                
            case 'deactivate':
                $id = (int)$data['id'];
                
                // Check for active subscriptions
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM subscriptions 
                    WHERE plan_id = ? AND status = 'active'
                ");
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot deactivate plan: Active subscriptions exist');
                }
                
                $stmt = $conn->prepare("
                    UPDATE plans 
                    SET deleted_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->bind_param("i", $id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to deactivate plan: ' . $stmt->error);
                }
                break;
                
            case 'activate':
                $id = (int)$data['id'];
                
                $stmt = $conn->prepare("
                    UPDATE plans 
                    SET deleted_at = NULL 
                    WHERE id = ?
                ");
                
                $stmt->bind_param("i", $id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to activate plan: ' . $stmt->error);
                }
                break;
                
            case 'delete':
                $id = (int)$data['id'];
                
                // Check for any subscriptions
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM subscriptions 
                    WHERE plan_id = ?
                ");
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot delete plan: Subscriptions exist');
                }
                
                $stmt = $conn->prepare("DELETE FROM plans WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete plan: ' . $stmt->error);
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}