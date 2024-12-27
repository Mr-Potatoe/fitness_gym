<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is staff
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
                
                // Insert new plan
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
                if (!isset($data['id'], $data['name'], $data['duration_months'], $data['price'])) {
                    throw new Exception('Missing required fields');
                }

                // Verify ownership
                $check_stmt = $conn->prepare("
                    SELECT created_by 
                    FROM plans 
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $check_stmt->bind_param("i", $data['id']);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();

                if (!$result || $result['created_by'] != $_SESSION['user_id']) {
                    throw new Exception('You can only edit your own plans');
                }

                // Validate duration range
                $duration_months = (int)$data['duration_months'];
                if ($duration_months < 1 || $duration_months > 36) {
                    throw new Exception('Duration must be between 1 and 36 months');
                }

                // Validate price
                $price = (float)$data['price'];
                if ($price <= 0) {
                    throw new Exception('Price must be greater than 0');
                }

                // Process features
                $features = isset($data['features']) ? array_filter(array_map('trim', (array)$data['features'])) : [];
                $features_json = json_encode($features);
                
                // Check for active subscriptions
                $subs_check = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM subscriptions 
                    WHERE plan_id = ? AND status = 'active'
                ");
                $subs_check->bind_param("i", $data['id']);
                $subs_check->execute();
                $subs_result = $subs_check->get_result()->fetch_assoc();
                
                if ($subs_result['count'] > 0) {
                    throw new Exception('Cannot modify plan: Active subscriptions exist');
                }
                
                // Update plan
                $stmt = $conn->prepare("
                    UPDATE plans 
                    SET name = ?,
                        duration_months = ?,
                        price = ?,
                        features = ?,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? 
                    AND deleted_at IS NULL
                    AND created_by = ?
                ");
                
                $stmt->bind_param(
                    'sidsii', 
                    $data['name'],
                    $duration_months,
                    $price,
                    $features_json,
                    $data['id'],
                    $_SESSION['user_id']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Error updating plan: ' . $stmt->error);
                }

                if ($stmt->affected_rows === 0) {
                    throw new Exception('Plan not found or no changes made');
                }
                break;

            case 'delete':
                if (!isset($data['id'])) {
                    throw new Exception('Plan ID is required');
                }

                // Verify ownership and check active subscriptions
                $check_stmt = $conn->prepare("
                    SELECT p.created_by,
                           COUNT(s.id) as active_subs
                    FROM plans p
                    LEFT JOIN subscriptions s ON p.id = s.plan_id 
                         AND s.status = 'active'
                    WHERE p.id = ? AND p.deleted_at IS NULL
                    GROUP BY p.id
                ");
                $check_stmt->bind_param("i", $data['id']);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();

                if (!$result) {
                    throw new Exception('Plan not found');
                }

                if ($result['created_by'] != $_SESSION['user_id']) {
                    throw new Exception('You can only delete your own plans');
                }

                if ($result['active_subs'] > 0) {
                    throw new Exception('Cannot delete plan with active subscriptions');
                }

                // Soft delete the plan
                $stmt = $conn->prepare("
                    UPDATE plans 
                    SET deleted_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? 
                    AND deleted_at IS NULL
                    AND created_by = ?
                ");
                
                $stmt->bind_param("ii", $data['id'], $_SESSION['user_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Error deleting plan');
                }

                if ($stmt->affected_rows === 0) {
                    throw new Exception('Plan not found or already deleted');
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