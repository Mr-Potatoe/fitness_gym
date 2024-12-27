<?php
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/TwoFactorAuth.php';
require_once __DIR__ . '/PaymentProcessor.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/ErrorLogger.php';
require_once __DIR__ . '/FileUploadHandler.php';

class SecurityManager {
    private $conn;
    private $errorLogger;
    private $rateLimiter;
    private $twoFactorAuth;
    private $passwordReset;
    private $paymentProcessor;
    private $fileUploadHandler;
    private $encryptionKey;

    public function __construct($conn, $encryptionKey) {
        $this->conn = $conn;
        $this->encryptionKey = $encryptionKey;
        $this->initializeComponents();
    }

    /**
     * Initialize security components
     */
    private function initializeComponents() {
        $this->errorLogger = new ErrorLogger($this->conn, ADMIN_EMAIL);
        $this->rateLimiter = new RateLimiter($this->conn);
        $this->twoFactorAuth = new TwoFactorAuth($this->conn, $this->errorLogger);
        $this->passwordReset = new PasswordReset($this->conn, $this->errorLogger);
        $this->fileUploadHandler = new FileUploadHandler($this->conn, UPLOAD_DIR, $this->errorLogger);
        $this->paymentProcessor = new PaymentProcessor(
            $this->conn,
            $this->errorLogger,
            $this->fileUploadHandler,
            $this->encryptionKey
        );
    }

    /**
     * Handle user login
     * @param string $username Username
     * @param string $password Password
     * @param bool $remember Remember me option
     * @return array Login result
     */
    public function handleLogin($username, $password, $remember = false) {
        try {
            // Check rate limiting
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            if ($this->rateLimiter->shouldLimit('login', $ipAddress)) {
                throw new Exception("Too many login attempts. Please try again later.");
            }

            // Validate credentials
            $stmt = $this->conn->prepare("
                SELECT id, username, email, password, role, status, 
                       requires_2fa, failed_attempts
                FROM users 
                WHERE username = ? AND status = 'active'
            ");
            
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->logFailedLogin($username, $ipAddress);
                throw new Exception("Invalid credentials");
            }

            $user = $result->fetch_assoc();

            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->logFailedLogin($username, $ipAddress);
                throw new Exception("Invalid credentials");
            }

            // Check if 2FA is required
            if ($user['requires_2fa']) {
                // Generate and send 2FA code
                $this->twoFactorAuth->generateAndSendCode($user['id'], $user['email']);
                
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'user_id' => $user['id']
                ];
            }

            // Create session
            $this->createUserSession($user, $remember);

            // Log successful login
            $this->logSuccessfulLogin($user['id']);

            return [
                'success' => true,
                'requires_2fa' => false,
                'redirect' => $this->getRedirectUrl($user['role'])
            ];

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'authentication',
                'Login failed: ' . $e->getMessage(),
                null,
                ['username' => $username]
            );

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify 2FA code
     * @param int $userId User ID
     * @param string $code 2FA code
     * @param bool $remember Remember me option
     * @return array Verification result
     */
    public function verify2FACode($userId, $code, $remember = false) {
        try {
            if (!$this->twoFactorAuth->verifyCode($userId, $code)) {
                throw new Exception("Invalid or expired code");
            }

            // Get user data
            $stmt = $this->conn->prepare("
                SELECT * FROM users WHERE id = ? AND status = 'active'
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("User not found");
            }

            $user = $result->fetch_assoc();

            // Create session
            $this->createUserSession($user, $remember);

            // Log successful 2FA
            $this->errorLogger->logError(
                'authentication',
                '2FA verification successful',
                null,
                ['user_id' => $userId]
            );

            return [
                'success' => true,
                'redirect' => $this->getRedirectUrl($user['role'])
            ];

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'authentication',
                '2FA verification failed: ' . $e->getMessage(),
                null,
                ['user_id' => $userId]
            );

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create user session
     * @param array $user User data
     * @param bool $remember Remember me option
     */
    private function createUserSession($user, $remember = false) {
        // Generate session ID
        $sessionId = bin2hex(random_bytes(32));
        
        // Store session
        $stmt = $this->conn->prepare("
            INSERT INTO user_sessions (
                user_id, session_id, ip_address, user_agent
            ) VALUES (?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt->bind_param(
            "isss",
            $user['id'],
            $sessionId,
            $ipAddress,
            $userAgent
        );
        
        $stmt->execute();

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['session_id'] = $sessionId;

        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            // Store token in database
            $stmt = $this->conn->prepare("
                INSERT INTO remember_tokens (
                    user_id, token, expires_at
                ) VALUES (?, ?, FROM_UNIXTIME(?))
            ");
            
            $stmt->bind_param("isi", $user['id'], $token, $expiry);
            $stmt->execute();

            // Set cookie
            setcookie(
                'remember_token',
                $token,
                $expiry,
                '/',
                '',
                true, // Secure
                true  // HttpOnly
            );
        }
    }

    /**
     * Handle logout
     */
    public function handleLogout() {
        try {
            if (isset($_SESSION['user_id'])) {
                // Remove session from database
                if (isset($_SESSION['session_id'])) {
                    $stmt = $this->conn->prepare("
                        DELETE FROM user_sessions 
                        WHERE session_id = ?
                    ");
                    
                    $stmt->bind_param("s", $_SESSION['session_id']);
                    $stmt->execute();
                }

                // Remove remember token if exists
                if (isset($_COOKIE['remember_token'])) {
                    $stmt = $this->conn->prepare("
                        DELETE FROM remember_tokens 
                        WHERE token = ?
                    ");
                    
                    $stmt->bind_param("s", $_COOKIE['remember_token']);
                    $stmt->execute();

                    setcookie('remember_token', '', time() - 3600, '/');
                }

                // Log logout
                $this->errorLogger->logError(
                    'authentication',
                    'User logged out',
                    null,
                    ['user_id' => $_SESSION['user_id']]
                );
            }

            // Destroy session
            session_destroy();
            
            return [
                'success' => true,
                'redirect' => '/login.php'
            ];

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'authentication',
                'Logout failed: ' . $e->getMessage(),
                null,
                ['user_id' => $_SESSION['user_id'] ?? null]
            );

            return [
                'success' => false,
                'message' => 'Logout failed'
            ];
        }
    }

    /**
     * Log failed login attempt
     * @param string $username Username
     * @param string $ipAddress IP address
     */
    private function logFailedLogin($username, $ipAddress) {
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (
                username, ip_address, user_agent, success
            ) VALUES (?, ?, ?, 0)
        ");
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt->bind_param("sss", $username, $ipAddress, $userAgent);
        $stmt->execute();

        // Update failed attempts count
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET failed_attempts = failed_attempts + 1,
                last_failed_attempt = NOW()
            WHERE username = ?
        ");
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }

    /**
     * Log successful login
     * @param int $userId User ID
     */
    private function logSuccessfulLogin($userId) {
        // Reset failed attempts
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET failed_attempts = 0,
                last_failed_attempt = NULL,
                last_login = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        // Log successful attempt
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (
                username, ip_address, user_agent, success, user_id
            ) VALUES (
                (SELECT username FROM users WHERE id = ?),
                ?, ?, 1, ?
            )
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt->bind_param("issi", $userId, $ipAddress, $userAgent, $userId);
        $stmt->execute();
    }

    /**
     * Get redirect URL based on user role
     * @param string $role User role
     * @return string Redirect URL
     */
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'admin':
                return '/admin/dashboard.php';
            case 'staff':
                return '/staff/dashboard.php';
            case 'member':
                return '/member/dashboard.php';
            default:
                return '/index.php';
        }
    }

    /**
     * Get component instances
     */
    public function getErrorLogger() { return $this->errorLogger; }
    public function getRateLimiter() { return $this->rateLimiter; }
    public function getTwoFactorAuth() { return $this->twoFactorAuth; }
    public function getPasswordReset() { return $this->passwordReset; }
    public function getPaymentProcessor() { return $this->paymentProcessor; }
    public function getFileUploadHandler() { return $this->fileUploadHandler; }
}
