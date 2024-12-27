<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/security_config.php';
require_once dirname(__DIR__) . '/includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

try {
    // Start transaction
    $conn->begin_transaction();

    // Clean up expired sessions
    $stmt = $conn->prepare("
        DELETE FROM user_sessions 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    
    $stmt->bind_param("i", SESSION_LIFETIME);
    $stmt->execute();
    
    // Clean up expired remember me tokens
    $stmt = $conn->prepare("
        DELETE FROM remember_tokens 
        WHERE expires_at < NOW()
    ");
    
    $stmt->execute();
    
    // Clean up expired password reset requests
    $stmt = $conn->prepare("
        DELETE FROM password_reset_requests 
        WHERE expires_at < NOW() OR used = 1
    ");
    
    $stmt->execute();
    
    // Clean up expired 2FA codes
    $stmt = $conn->prepare("
        DELETE FROM two_factor_codes 
        WHERE expires_at < NOW() OR used = 1
    ");
    
    $stmt->execute();
    
    // Clean up old login attempts
    $stmt = $conn->prepare("
        DELETE FROM login_attempts 
        WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    
    $stmt->bind_param("i", LOGIN_LOCKOUT_TIME);
    $stmt->execute();
    
    // Clean up old rate limiting records
    $stmt = $conn->prepare("
        DELETE FROM rate_limiting 
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    
    $stmt->bind_param("i", RATE_LIMIT_WINDOW);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Log successful cleanup
    $securityManager->getErrorLogger()->logError(
        'system',
        'Session cleanup completed successfully',
        null,
        [
            'sessions_cleaned' => $conn->affected_rows
        ]
    );

    echo "Cleanup completed successfully\n";

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Log error
    $securityManager->getErrorLogger()->logError(
        'system',
        'Session cleanup failed: ' . $e->getMessage(),
        $e
    );

    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
