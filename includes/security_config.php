<?php
// Security Configuration
define('ADMIN_EMAIL', 'admin@vikingsfit.com'); // Change this to your admin email
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'vikingsfit_csrf_token');
define('API_RATE_LIMIT', 100); // requests per minute
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Initialize error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Initialize session with secure settings
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Initialize security headers
function setSecurityHeaders() {
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Initialize database connection with secure settings
function initSecureDatabase() {
    require_once __DIR__ . '/config.php';
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Connection failed");
    }
    
    // Set secure SQL mode
    $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    
    return $conn;
}

// Initialize security components
function initSecurity() {
    // Create required directories if they don't exist
    $directories = [
        __DIR__ . '/../logs',
        __DIR__ . '/../uploads',
        __DIR__ . '/../uploads/payments',
        __DIR__ . '/../uploads/profiles'
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Initialize secure session
    initSecureSession();

    // Set security headers
    setSecurityHeaders();

    // Initialize database connection
    $conn = initSecureDatabase();

    // Initialize error logger
    require_once __DIR__ . '/utils/ErrorLogger.php';
    $errorLogger = new ErrorLogger($conn, ADMIN_EMAIL);

    // Initialize rate limiter
    require_once __DIR__ . '/utils/RateLimiter.php';
    $rateLimiter = new RateLimiter($conn);

    // Initialize file upload handler
    require_once __DIR__ . '/utils/FileUploadHandler.php';
    $fileUploadHandler = new FileUploadHandler($conn, UPLOAD_DIR, $errorLogger);

    return [
        'conn' => $conn,
        'errorLogger' => $errorLogger,
        'rateLimiter' => $rateLimiter,
        'fileUploadHandler' => $fileUploadHandler
    ];
}

// Clean potentially harmful input
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email address
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate secure random token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Hash password securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Verify password hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Log user activity
function logUserActivity($conn, $userId, $action, $details = []) {
    $stmt = $conn->prepare("
        INSERT INTO user_activity_logs (
            user_id, action, details, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $detailsJson = json_encode($details);
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt->bind_param(
        "issss",
        $userId,
        $action,
        $detailsJson,
        $ipAddress,
        $userAgent
    );
    
    return $stmt->execute();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has required role
function hasRole($conn, $userId, $requiredRole) {
    $stmt = $conn->prepare("
        SELECT role FROM users 
        WHERE id = ? AND status = 'active'
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['role'] === $requiredRole;
    }
    
    return false;
}

// Initialize security components when this file is included
$security = initSecurity();
