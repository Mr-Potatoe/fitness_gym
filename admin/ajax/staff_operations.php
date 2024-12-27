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
        switch ($data['action']) {
            case 'add':
                $response = addStaff($conn, $data);
                break;
            case 'edit':
                $response = editStaff($conn, $data);
                break;
            case 'get':
                $response = getStaffDetails($conn, $data);
                break;
            case 'toggle_status':
                $response = toggleStaffStatus($conn, $data);
                break;
            case 'delete':
                $response = deleteStaff($conn, $data);
                break;
        }
    }

    echo json_encode($response);
    exit;
}

function addStaff($conn, $data) {
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

    // Insert new staff
    $query = "INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'staff', 'active')";
    $stmt = $conn->prepare($query);
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt->bind_param("ssss", $data['username'], $data['email'], $hashed_password, $data['full_name']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Staff added successfully'];
    }

    return ['success' => false, 'message' => 'Error adding staff'];
}

function editStaff($conn, $data) {
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

    // Update staff
    $query = "UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ? AND role = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $data['username'], $data['email'], $data['full_name'], $data['id']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Staff updated successfully'];
    }

    return ['success' => false, 'message' => 'Error updating staff'];
}

function getStaffDetails($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Staff ID is required'];
    }

    $query = "SELECT id, username, email, full_name, status FROM users WHERE id = ? AND role = 'staff' AND permanently_deleted = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($staff = $result->fetch_assoc()) {
        return ['success' => true, 'data' => $staff];
    }

    return ['success' => false, 'message' => 'Staff not found'];
}

function toggleStaffStatus($conn, $data) {
    if (empty($data['id']) || empty($data['status'])) {
        return ['success' => false, 'message' => 'Invalid request'];
    }

    $new_status = $data['status'] === 'active' ? 'inactive' : 'active';
    $query = "UPDATE users SET status = ? WHERE id = ? AND role = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_status, $data['id']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Staff status updated successfully'];
    }

    return ['success' => false, 'message' => 'Error updating staff status'];
}

function deleteStaff($conn, $data) {
    if (empty($data['id'])) {
        return ['success' => false, 'message' => 'Staff ID is required'];
    }

    $query = "UPDATE users SET permanently_deleted = 1 WHERE id = ? AND role = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $data['id']);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Staff deleted successfully'];
    }

    return ['success' => false, 'message' => 'Error deleting staff'];
} 