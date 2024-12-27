<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $member_id = $data['id'] ?? '';

    if (empty($member_id)) {
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Check for related subscriptions
        $subscriptions_query = "SELECT * FROM subscriptions WHERE user_id = ?";
        $subscriptions_stmt = $conn->prepare($subscriptions_query);
        $subscriptions_stmt->bind_param('i', $member_id);
        $subscriptions_stmt->execute();
        $subscriptions_result = $subscriptions_stmt->get_result();

        // Delete related subscriptions
        while ($subscription = $subscriptions_result->fetch_assoc()) {
            $delete_subscription_query = "DELETE FROM subscriptions WHERE id = ?";
            $delete_subscription_stmt = $conn->prepare($delete_subscription_query);
            $delete_subscription_stmt->bind_param('i', $subscription['id']);
            $delete_subscription_stmt->execute();
        }

        // Now delete the member
        $delete_member_query = "DELETE FROM users WHERE id = ?";
        $delete_member_stmt = $conn->prepare($delete_member_query);
        $delete_member_stmt->bind_param('i', $member_id);
        $delete_member_stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting member: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}