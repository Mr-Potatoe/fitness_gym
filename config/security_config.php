<?php
// Security settings
define('ENCRYPTION_KEY', bin2hex(random_bytes(32))); // Generate a random encryption key
define('ADMIN_EMAIL', 'admin@vikingsfit.com');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

// Session security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('REMEMBER_ME_LIFETIME', 30 * 24 * 60 * 60); // 30 days
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15); // 15 minutes
define('PASSWORD_RESET_EXPIRY', 24 * 60 * 60); // 24 hours

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf'
]);

// Rate limiting settings
define('RATE_LIMIT_WINDOW', 300); // 5 minutes
define('RATE_LIMIT_MAX_ATTEMPTS', [
    'login' => 5,
    'register' => 3,
    'password_reset' => 3,
    'api' => 100
]);

// Check if running in CLI mode
if (php_sapi_name() !== 'cli') {
    // Security headers
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\' https://cdnjs.cloudflare.com; img-src \'self\' data: https:; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com');
}

// Initialize error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Function to generate a secure random token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to hash a password securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Function to validate file upload
function validateFileUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_FILE_TYPES)) {
        return false;
    }

    return true;
}

// Function to sanitize filename
function sanitizeFilename($filename) {
    // Remove any path components
    $filename = basename($filename);
    
    // Remove any non-alphanumeric characters except dots and dashes
    $filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $filename);
    
    // Remove any runs of dots
    $filename = preg_replace('/\.+/', '.', $filename);
    
    // Maximum length of 255 characters
    $filename = substr($filename, 0, 255);
    
    return $filename;
}

// Function to generate a secure upload path
function generateSecureUploadPath($originalFilename) {
    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $randomName = bin2hex(random_bytes(16));
    return date('Y/m/d/') . $randomName . '.' . $ext;
}

// Function to check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Function to check if request is from allowed origin
function isAllowedOrigin() {
    $allowedOrigins = [
        'http://localhost',
        'https://vikingsfit.com'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    return in_array($origin, $allowedOrigins);
}

// Function to validate and sanitize input
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'email':
            $data = filter_var($data, FILTER_SANITIZE_EMAIL);
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;
        
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        
        case 'url':
            $data = filter_var($data, FILTER_SANITIZE_URL);
            return filter_var($data, FILTER_VALIDATE_URL) ? $data : false;
        
        case 'string':
        default:
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }
}

// Function to check if IP is banned
function isIPBanned($ip) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 1 FROM banned_ips 
        WHERE ip_address = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

// Check if running in CLI mode
if (php_sapi_name() !== 'cli') {
    // Check if the current IP is banned
    if (isset($_SERVER['REMOTE_ADDR']) && isIPBanned($_SERVER['REMOTE_ADDR'])) {
        http_response_code(403);
        die('Access Denied');
    }
}
