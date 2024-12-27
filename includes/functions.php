<?php

/**
 * Get database connection
 * @return mysqli Database connection object
 */
function getConnection() {
    global $conn;
    return $conn;
}

/**
 * Get user by ID
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function getUserById($user_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND permanently_deleted = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get user by username
 * @param string $username Username
 * @return array|null User data or null if not found
 */
function getUserByUsername($username) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND permanently_deleted = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Authenticate user
 * @param string $username Username
 * @param string $password Password
 * @param string $role User role (admin, staff, member)
 * @return array Authentication result with success status and message
 */
function authenticateUser($username, $password, $role) {
    $conn = getConnection();
    
    // Check if user exists and is active
    $stmt = $conn->prepare("SELECT * FROM users 
                           WHERE username = ? 
                           AND role = ? 
                           AND status = 'active' 
                           AND permanently_deleted = 0 
                           AND deleted_at IS NULL");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

/**
 * Login user
 * @param string $username Username
 * @param string $password Password
 * @param mysqli $conn Database connection object
 * @return array Login result with success status, message, and redirect URL
 */
function loginUser($username, $password, $conn) {
    $response = array(
        'success' => false,
        'message' => '',
        'redirect' => ''
    );

    try {
        // Debug log for login attempt
        error_log("Login attempt for username: " . $username);

        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = ? AND permanently_deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Debug log for user data
            error_log("User found: " . print_r($user, true));
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if user is active
                if ($user['status'] !== 'active') {
                    $response['message'] = 'Your account is inactive. Please contact the administrator.';
                    error_log("Inactive account for user: " . $username);
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    $response['success'] = true;
                    $response['message'] = 'Login successful!';

                    // Debug log for role
                    error_log("User role: " . $user['role']);

                    // Set redirect based on role with proper paths
                    switch ($user['role']) {
                        case 'staff':
                            $response['redirect'] = 'staff/dashboard.php';
                            error_log("Staff redirect set to: " . $response['redirect']);
                            break;
                        case 'member':
                            $response['redirect'] = 'member/dashboard.php';
                            break;
                        case 'admin':
                            $response['redirect'] = 'admin/dashboard.php';
                            break;
                        default:
                            $response['redirect'] = 'index.php';
                    }

                    // Debug log for final response
                    error_log("Login response: " . print_r($response, true));
                }
            } else {
                $response['message'] = 'Invalid username or password';
                error_log("Invalid password for user: " . $username);
            }
        } else {
            $response['message'] = 'Invalid username or password';
            error_log("User not found: " . $username);
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        $response['message'] = 'An error occurred during login';
    }

    return $response;
}

/**
 * Check staff access
 */
function checkStaffAccess() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Debug log
    error_log('Staff Access Check - Session data: ' . print_r($_SESSION, true));

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || (!isset($_SESSION['role']) && !isset($_SESSION['user_role']))) {
        error_log('Staff Access Check - User not logged in');
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }

    // Get the role (check both possible session variables)
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

    // Check if user is staff
    if ($role !== 'staff') {
        error_log('Staff Access Check - Invalid role: ' . $role);
        header('Location: ' . SITE_URL . '/login.php?error=' . urlencode('Access denied. Staff only area.'));
        exit;
    }

    error_log('Staff Access Check - Access granted for user ID: ' . $_SESSION['user_id']);
}

/**
 * Change user password
 * @param int $user_id User ID
 * @param string $old_password Old password
 * @param string $new_password New password
 * @return array Result with success status and message
 */
function changePassword($user_id, $old_password, $new_password) {
    $conn = getConnection();
    
    // Get user
    $user = getUserById($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Verify old password
    if (!password_verify($old_password, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    return ['success' => false, 'message' => 'Error changing password'];
}

/**
 * Log out user
 */
function logout() {
    session_unset();
    session_destroy();
    session_start();
}
