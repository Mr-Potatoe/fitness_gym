<?php

// Check if username exists (excluding current user)
$sql = "SELECT id FROM users WHERE username = '$username' AND id != $staff_id AND permanently_deleted = 0";
if ($conn->query($sql)->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    exit;
}

// Check if email exists (excluding current user)
$sql = "SELECT id FROM users WHERE email = '$email' AND id != $staff_id AND permanently_deleted = 0";
if ($conn->query($sql)->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}

$conn->close();
?> 