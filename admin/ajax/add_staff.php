<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $full_name = $conn->real_escape_string($_POST['full_name']);
    
    // Validate input
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    // Check if username/email exists but is deleted
    $check_sql = "SELECT id FROM users WHERE (username = '$username' OR email = '$email') AND deleted_at IS NOT NULL";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Reactivate the deleted account
        $sql = "UPDATE users SET 
                username = '$username',
                email = '$email',
                password = '" . password_hash($password, PASSWORD_DEFAULT) . "',
                full_name = '$full_name',
                role = 'staff',
                status = 'active',
                deleted_at = NULL,
                updated_at = CURRENT_TIMESTAMP
                WHERE (username = '$username' OR email = '$email') AND deleted_at IS NOT NULL";
        
        if ($conn->query($sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Staff account reactivated successfully'
            ]);
            exit;
        }
    }
    
    // Check for active accounts with same username/email
    $active_check = "SELECT id FROM users WHERE (username = '$username' OR email = '$email') 
                     AND (deleted_at IS NULL OR permanently_deleted = 1)";
    if ($conn->query($active_check)->num_rows > 0) {
        $errors[] = "Username or email is already in use by an active account";
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
        exit;
    }
    
    // Insert new staff member
    $sql = "INSERT INTO users (username, email, password, full_name, role, status, created_at) 
            VALUES ('$username', '$email', '" . password_hash($password, PASSWORD_DEFAULT) . "', 
                    '$full_name', 'staff', 'active', CURRENT_TIMESTAMP)";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Staff member added successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error adding staff member. Please try again.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 