<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $conn->real_escape_string($_POST['member_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Check if username is unique (excluding current user)
    $check_username = $conn->query("SELECT id FROM users WHERE username = '$username' AND id != $member_id");
    if ($check_username->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    // Check if email is unique (excluding current user)
    $check_email = $conn->query("SELECT id FROM users WHERE email = '$email' AND id != $member_id");
    if ($check_email->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    $sql = "UPDATE users SET 
            full_name = '$full_name',
            username = '$username',
            email = '$email',
            status = '$status'";

    // Add password update if provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password = '$password'";
    }

    $sql .= " WHERE id = $member_id AND role = 'member'";

    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating member']);
    }
    exit;
}

// Handle GET request to fetch member details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $member_id = $conn->real_escape_string($_GET['id']);
    $result = $conn->query("SELECT id, full_name, username, email, status FROM users WHERE id = $member_id AND role = 'member'");
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 