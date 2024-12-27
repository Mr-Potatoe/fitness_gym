<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = $conn->real_escape_string($_POST['staff_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    
    // Check if username is unique (excluding current user)
    $check_username = $conn->query("SELECT id FROM users WHERE username = '$username' AND id != $staff_id");
    if ($check_username->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    // Check if email is unique (excluding current user)
    $check_email = $conn->query("SELECT id FROM users WHERE email = '$email' AND id != $staff_id");
    if ($check_email->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    $sql = "UPDATE users SET 
            full_name = '$full_name',
            username = '$username',
            email = '$email'";

    // Add password update if provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password = '$password'";
    }

    $sql .= " WHERE id = $staff_id AND role = 'staff'";

    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating staff']);
    }
    exit;
}

// Handle GET request to fetch staff details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $staff_id = $conn->real_escape_string($_GET['id']);
    $result = $conn->query("SELECT id, full_name, username, email FROM users WHERE id = $staff_id AND role = 'staff'");
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 