<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'], $data['full_name'], $data['email'], $data['username'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $id = $conn->real_escape_string($data['id']);
    $full_name = $conn->real_escape_string($data['full_name']);
    $email = $conn->real_escape_string($data['email']);
    $username = $conn->real_escape_string($data['username']);
    
    // Check if email already exists
    $check_email = "SELECT id FROM users WHERE email = '$email' AND id != $id";
    $email_result = $conn->query($check_email);
    if ($email_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Check if username already exists
    $check_username = "SELECT id FROM users WHERE username = '$username' AND id != $id";
    $username_result = $conn->query($check_username);
    if ($username_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        if (isset($data['password']) && !empty($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $update_query = "UPDATE users 
                            SET full_name = '$full_name',
                                email = '$email',
                                username = '$username',
                                password = '$password',
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = $id AND role = 'member'";
        } else {
            $update_query = "UPDATE users 
                            SET full_name = '$full_name',
                                email = '$email',
                                username = '$username',
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = $id AND role = 'member'";
        }
        
        if ($conn->query($update_query)) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Member updated successfully'
            ]);
        } else {
            throw new Exception('Error updating member');
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 