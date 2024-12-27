<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid request token';
    } else {
        // Validate required fields
        $required_fields = ['username', 'email', 'password', 'confirm_password', 'full_name', 'contact_number', 'address'];
        $missing_fields = false;
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                $missing_fields = true;
                break;
            }
        }

        if ($missing_fields) {
            $response['message'] = 'All fields are required';
        } else {
            // Validate password
            if (!validatePassword($_POST['password'])) {
                $response['message'] = 'Password must be at least 8 characters long and contain uppercase, lowercase, and numbers';
            } else if ($_POST['password'] !== $_POST['confirm_password']) {
                $response['message'] = 'Passwords do not match';
            } else {
                try {
                    $conn->begin_transaction();

                    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                    $full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
                    $contact_number = filter_var($_POST['contact_number'], FILTER_SANITIZE_STRING);
                    $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                    // Check if username exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND permanently_deleted = 0");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Username already exists');
                    }

                    // Check if email exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND permanently_deleted = 0");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Email already registered');
                    }

                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, contact_number, address, role, status, created_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', CURRENT_TIMESTAMP)");
                    $stmt->bind_param("ssssss", $username, $password, $email, $full_name, $contact_number, $address);
                    
                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;

                        // Create welcome notification
                        $title = 'Welcome to VikingsFit Gym!';
                        $message = 'Thank you for registering. Please check our membership plans to get started.';
                        $type = 'welcome';
                        
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $user_id, $title, $message, $type);
                        
                        if (!$stmt->execute()) {
                            throw new Exception('Error creating welcome notification');
                        }

                        $conn->commit();

                        // Set session variables
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['email'] = $email;
                        $_SESSION['user_role'] = 'member';

                        $response = ['success' => true, 'message' => 'Registration successful'];
                    } else {
                        throw new Exception('Error creating account');
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = $e->getMessage();
                }
            }
        }
    }
}

echo json_encode($response);
exit;
?>