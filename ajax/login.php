<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Clear any existing session
session_unset();
session_regenerate_id(true);

$username = $conn->real_escape_string($_POST['username']);
$password = $_POST['password'];
$role = $_POST['role'];

// First check if user was deleted
$check_deleted = $conn->query("SELECT deleted_at, permanently_deleted FROM users 
                              WHERE username = '$username' 
                              AND role = '$role'");
if ($check_deleted && $check_deleted->num_rows > 0) {
    $user_status = $check_deleted->fetch_assoc();
    
    if ($user_status['permanently_deleted']) {
        // Account is permanently deleted
        $message = $role === 'staff' 
            ? 'Your account has been permanently deleted. Please contact the administrator.'
            : 'Your account has been permanently deleted. Please create a new account to continue.';
            
        echo json_encode([
            'success' => false,
            'message' => $message,
            'show_register' => ($role === 'member')
        ]);
        exit;
    } else if ($user_status['deleted_at'] !== null) {
        // Account is temporarily deactivated
        $message = $role === 'staff' 
            ? 'Your account has been deactivated by the administrator. Please contact them for reactivation.'
            : 'Your account has been deactivated. Please contact the administrator for reactivation or create a new account.';
            
        echo json_encode([
            'success' => false,
            'message' => $message,
            'show_register' => ($role === 'member')
        ]);
        exit;
    }
}

// Check if user exists and is active
$sql = "SELECT * FROM users WHERE username = '$username' AND role = '$role' AND status = 'active' AND deleted_at IS NULL";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if ($password === $user['password'] || password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];

        // Check subscription status for members
        if ($user['role'] === 'member') {
            $sub_sql = "SELECT * FROM subscriptions WHERE user_id = {$user['id']} AND status = 'active' ORDER BY end_date DESC LIMIT 1";
            $sub_result = $conn->query($sub_sql);
            if ($sub_result && $sub_result->num_rows > 0) {
                $subscription = $sub_result->fetch_assoc();
                $_SESSION['subscription_id'] = $subscription['id'];
                $_SESSION['subscription_status'] = $subscription['status'];
            }
        }

        $redirect = SITE_URL . '/' . $user['role'] . '/dashboard.php';

        echo json_encode([
            'success' => true,
            'redirect' => $redirect,
            'message' => 'Login successful'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid username or password'
]); 