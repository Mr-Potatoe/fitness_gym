<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fitness_gym');

// Simple URL definition for localhost
define('SITE_URL', 'http://localhost/fitness_gym');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');

// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
session_name('GYM_SESSION');

// Initialize database connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log($e->getMessage());
    die("Unable to connect to database. Please check if MySQL is running and try again.");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security functions
function validatePassword($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function checkLoginAttempts($username) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                           FROM login_attempts 
                           WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['attempts'] >= 5) {
        return false; // Account locked
    }
    return true;
}

/**
 * Check if a user has exceeded the maximum number of login attempts
 * Returns true if user can attempt login, false if they need to wait
 */
function checkLoginAttemptsNew($username) {
    global $conn;
    
    // Clean up old attempts (older than 15 minutes)
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute();
    
    // Count recent attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts 
                           WHERE username = ? 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['attempt_count'] < 5; // Allow up to 5 attempts
}

/**
 * Record a failed login attempt
 */
function recordLoginAttempt($username) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("ss", $username, $ip);
    
    return $stmt->execute();
}

/**
 * Clear login attempts for a user after successful login
 */
function clearLoginAttempts($username) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
    $stmt->bind_param("s", $username);
    
    return $stmt->execute();
}

/**
 * Get remaining login attempts for a user
 */
function getRemainingLoginAttempts($username) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts 
                           WHERE username = ? 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return 5 - $row['attempt_count']; // 5 is max attempts
}

/**
 * Get time until next login attempt is allowed (in minutes)
 */
function getLoginLockoutTime($username) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT MIN(TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(attempt_time, INTERVAL 15 MINUTE))) as wait_time 
                           FROM login_attempts 
                           WHERE username = ? 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return max(0, $row['wait_time']);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Function to redirect
function redirect($path) {
    $path = ltrim($path, '/');
    header("Location: " . SITE_URL . '/' . $path);
    exit;
}

// Function to get relative path
function getRelativePath($path) {
    return SITE_URL . '/' . ltrim($path, '/');
}

// Function to check if user should be redirected to dashboard
function checkDashboardRedirect() {
    if (isLoggedIn()) {
        $role = $_SESSION['user_role'];
        if (in_array($role, ['admin', 'staff', 'member'])) {
            redirect($role . '/dashboard.php');
        }
    }
}

/**
 * Get active plans with formatted details
 * @param bool $includeDeleted Whether to include soft-deleted plans
 * @return array Array of plan objects
 */
function getActivePlans($includeDeleted = false) {
    global $conn;
    
    $query = "SELECT p.*, 
              COALESCE(u.full_name, 'System') as created_by_name,
              (SELECT COUNT(*) FROM subscriptions s 
               WHERE s.plan_id = p.id 
               AND s.status = 'active') as active_subscribers
              FROM plans p 
              LEFT JOIN users u ON p.created_by = u.id
              WHERE 1=1 " . 
              (!$includeDeleted ? "AND p.deleted_at IS NULL " : "") . 
              "ORDER BY p.price ASC";
              
    $result = $conn->query($query);
    $plans = [];
    
    if ($result && $result->num_rows > 0) {
        while ($plan = $result->fetch_assoc()) {
            // Format features
            $plan['features'] = json_decode($plan['features'], true) ?: [];
            
            // Format price
            $plan['formatted_price'] = formatPrice($plan['price']);
            
            // Format duration
            $plan['duration_text'] = $plan['duration_months'] . ' ' . 
                                   ($plan['duration_months'] == 1 ? 'month' : 'months');
            
            $plans[] = $plan;
        }
    }
    
    return $plans;
}

/**
 * Get active plans with formatted details for staff
 * @return array Array of plan objects
 */
function getStaffPlans() {
    global $conn;
    
    $query = "SELECT p.*, 
              COALESCE(u.full_name, 'System') as created_by_name,
              (SELECT COUNT(*) FROM subscriptions s 
               WHERE s.plan_id = p.id 
               AND s.status = 'active') as active_subscribers
              FROM plans p 
              LEFT JOIN users u ON p.created_by = u.id
              WHERE p.deleted_at IS NULL 
              ORDER BY p.price ASC, p.name ASC";
              
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("SQL Error in getStaffPlans: " . $conn->error);
        return [];
    }
    
    $plans = [];
    while ($plan = $result->fetch_assoc()) {
        // Format features
        $plan['features'] = json_decode($plan['features'], true) ?: [];
        
        // Format price
        $plan['formatted_price'] = formatPrice($plan['price']);
        
        // Format duration
        $plan['duration_text'] = $plan['duration_months'] . ' ' . 
                               ($plan['duration_months'] == 1 ? 'month' : 'months');
        
        $plans[] = $plan;
    }
    
    return $plans;
}

/**
 * Format price with currency symbol
 * @param float $price Price to format
 * @return string Formatted price
 */
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

/**
 * Log admin actions for audit trail
 */
function logAdminAction($admin_id, $action_type, $target_id, $description) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_id, action_description, ip_address) 
                               VALUES (?, ?, ?, ?, ?)");
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("iisss", $admin_id, $action_type, $target_id, $description, $ip);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Get admin action logs with filtering
 */
function getAdminLogs($filters = [], $limit = 50, $offset = 0) {
    global $conn;
    
    $where_clauses = [];
    $params = [];
    $types = "";
    
    if (!empty($filters['admin_id'])) {
        $where_clauses[] = "admin_id = ?";
        $params[] = $filters['admin_id'];
        $types .= "i";
    }
    
    if (!empty($filters['action_type'])) {
        $where_clauses[] = "action_type = ?";
        $params[] = $filters['action_type'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $where_clauses[] = "created_at >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where_clauses[] = "created_at <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);
    
    $sql = "SELECT l.*, u.username as admin_username 
            FROM admin_logs l 
            JOIN users u ON l.admin_id = u.id 
            $where_sql 
            ORDER BY l.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

?>