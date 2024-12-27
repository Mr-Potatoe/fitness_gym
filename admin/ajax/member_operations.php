<?php
require_once '../../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $response = ['success' => false, 'message' => 'Invalid request'];

    if (isset($data['action'])) {
        try {
            $conn->begin_transaction();
            
            switch ($data['action']) {
                case 'add':
                    $response = addMember($conn, $data);
                    break;
                case 'edit':
                    $response = editMember($conn, $data);
                    break;
                case 'get':
                    $response = getMemberDetails($conn, $data);
                    break;
                case 'verify':
                    $response = verifyMember($conn, $data);
                    break;
                case 'delete':
                    $response = deleteMember($conn, $data);
                    break;
                case 'renew':
                    $response = renewMembership($conn, $data);
                    break;
                default:
                    $response = ['success' => false, 'message' => 'Invalid action'];
            }
            
            if ($response['success']) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }

    echo json_encode($response);
    exit;
}

function addMember($conn, $data) {
    // Validate required fields
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
        return ['success' => false, 'message' => 'All fields are required'];
    }

    // Check if username or email already exists
    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND permanently_deleted = 0";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ss", $data['username'], $data['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }

    // Insert new member
    $query = "INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'member', 'active')";
    $stmt = $conn->prepare($query);
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt->bind_param("ssss", $data['username'], $data['email'], $hashed_password, $data['full_name']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Member added successfully'];
    }

    return ['success' => false, 'message' => 'Error adding member: ' . $stmt->error];
}

function editMember($conn, $data) {
    // Validate required fields
    if (empty($data['id']) || empty($data['username']) || empty($data['email']) || empty($data['full_name'])) {
        return ['success' => false, 'message' => 'All fields are required'];
    }

    // Check if username or email already exists for other users
    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? AND permanently_deleted = 0";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ssi", $data['username'], $data['email'], $data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }

    // Update member
    $query = "UPDATE users SET username = ?, email = ?, full_name = ?, staff_notes = ? WHERE id = ? AND role = 'member'";
    $stmt = $conn->prepare($query);
    $staff_notes = isset($data['staff_notes']) ? $data['staff_notes'] : '';
    $stmt->bind_param("ssssi", $data['username'], $data['email'], $data['full_name'], $staff_notes, $data['id']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Member updated successfully'];
    }

    return ['success' => false, 'message' => 'Error updating member: ' . $stmt->error];
}

function getMemberDetails($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Member ID is required'];
    }

    $query = "SELECT u.*, 
              CASE 
                WHEN s.status = 'active' AND s.start_date <= CURDATE() AND s.end_date >= CURDATE() THEN 'Active'
                WHEN s.status = 'pending' THEN 'Pending'
                WHEN s.status = 'active' AND s.end_date < CURDATE() THEN 'Expired'
                ELSE 'Inactive'
              END as subscription_status,
              p.name as plan_name,
              p.duration_months,
              s.start_date,
              s.end_date,
              s.id as subscription_id,
              py.status as payment_status,
              py.amount as payment_amount,
              py.payment_date,
              py.payment_method,
              admin.full_name as verified_by_name,
              (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id) as total_subscriptions,
              (SELECT COUNT(*) FROM gym_visits WHERE user_id = u.id) as total_visits,
              (SELECT visit_date FROM gym_visits WHERE user_id = u.id ORDER BY visit_date DESC LIMIT 1) as last_visit
              FROM users u 
              LEFT JOIN (
                  SELECT * FROM subscriptions 
                  WHERE (status = 'active' AND end_date >= CURDATE()) 
                  OR status = 'pending'
                  ORDER BY created_at DESC 
                  LIMIT 1
              ) s ON u.id = s.user_id
              LEFT JOIN plans p ON s.plan_id = p.id 
              LEFT JOIN payments py ON s.id = py.subscription_id
              LEFT JOIN users admin ON u.verified_by = admin.id
              WHERE u.id = ? AND u.role = 'member' AND u.permanently_deleted = 0";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($member = $result->fetch_assoc()) {
        // Get subscription history
        $history_query = "SELECT s.*, p.name as plan_name, p.duration_months,
                         py.status as payment_status, py.amount as payment_amount,
                         py.payment_date, py.payment_method
                         FROM subscriptions s
                         LEFT JOIN plans p ON s.plan_id = p.id
                         LEFT JOIN payments py ON s.id = py.subscription_id
                         WHERE s.user_id = ?
                         ORDER BY s.created_at DESC";
        
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        $history_result = $stmt->get_result();
        
        $subscription_history = [];
        while ($history = $history_result->fetch_assoc()) {
            $subscription_history[] = $history;
        }
        
        $member['subscription_history'] = $subscription_history;
        
        return ['success' => true, 'data' => $member];
    }

    return ['success' => false, 'message' => 'Member not found'];
}

function verifyMember($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Member ID is required'];
    }

    // Get current admin ID from session
    $admin_id = $_SESSION['user_id'];
    
    $query = "UPDATE users SET verified = 1, verified_at = NOW(), verified_by = ? 
              WHERE id = ? AND role = 'member' AND verified = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $admin_id, $data['id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return ['success' => true, 'message' => 'Member verified successfully'];
    }

    return ['success' => false, 'message' => 'Error verifying member or member already verified'];
}

function deleteMember($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Member ID is required'];
    }

    // Check if member has any active subscriptions
    $check_query = "SELECT id FROM subscriptions 
                   WHERE user_id = ? AND status = 'active' 
                   AND start_date <= CURDATE() AND end_date >= CURDATE()";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Cannot delete member with active subscription'];
    }

    // Soft delete the member
    $query = "UPDATE users SET permanently_deleted = 1, deleted_at = NOW() WHERE id = ? AND role = 'member'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $data['id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return ['success' => true, 'message' => 'Member deleted successfully'];
    }

    return ['success' => false, 'message' => 'Error deleting member or member not found'];
}

function renewMembership($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Member ID is required'];
    }

    // Check if member exists and is verified
    $check_query = "SELECT id, verified FROM users WHERE id = ? AND role = 'member' AND permanently_deleted = 0";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Member not found'];
    }
    
    $member = $result->fetch_assoc();
    if (!$member['verified']) {
        return ['success' => false, 'message' => 'Member must be verified before renewing membership'];
    }

    // Check if member has any pending subscriptions
    $check_pending = "SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($check_pending);
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Member has a pending subscription'];
    }

    // Create new subscription with pending status
    // The actual plan selection and payment will be handled by the subscription process
    return ['success' => true, 'redirect' => "/admin/subscription.php?member_id=" . $data['id']];
}