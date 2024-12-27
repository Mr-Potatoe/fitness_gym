<?php
class PasswordReset {
    private $conn;
    private $errorLogger;
    private $mailer;
    private $tokenExpiry = 3600; // 1 hour

    public function __construct($conn, $errorLogger) {
        $this->conn = $conn;
        $this->errorLogger = $errorLogger;
    }

    /**
     * Initiate password reset process
     * @param string $email User's email
     * @return bool Whether reset email was sent successfully
     */
    public function initiateReset($email) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }

            // Check if email exists
            $stmt = $this->conn->prepare("
                SELECT id, username, email, role 
                FROM users 
                WHERE email = ? AND status = 'active'
            ");
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Don't reveal that email doesn't exist
                return true;
            }

            $user = $result->fetch_assoc();
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiryTime = date('Y-m-d H:i:s', time() + $this->tokenExpiry);
            
            // Store reset request
            $stmt = $this->conn->prepare("
                INSERT INTO password_reset_requests (
                    user_id, token, ip_address, user_agent, expires_at
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt->bind_param(
                "issss",
                $user['id'],
                $token,
                $ipAddress,
                $userAgent,
                $expiryTime
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create password reset request");
            }

            // Send reset email
            $resetLink = SITE_URL . "/reset_password.php?token=" . $token;
            $emailBody = $this->getResetEmailTemplate($user['username'], $resetLink);
            
            // Log the action
            $this->errorLogger->logError(
                'security',
                'Password reset requested',
                null,
                ['user_id' => $user['id'], 'email' => $email]
            );

            // Send email using your preferred method
            // For example: mail($email, "Password Reset Request", $emailBody);
            
            return true;

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'security',
                'Password reset failed: ' . $e->getMessage(),
                null,
                ['email' => $email]
            );
            return false;
        }
    }

    /**
     * Validate reset token
     * @param string $token Reset token
     * @return array|false User data if valid, false if not
     */
    public function validateToken($token) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, u.username, u.email 
                FROM password_reset_requests r
                JOIN users u ON u.id = r.user_id
                WHERE r.token = ? 
                AND r.expires_at > NOW()
                AND r.used = 0
            ");
            
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }

            return $result->fetch_assoc();

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'security',
                'Token validation failed: ' . $e->getMessage(),
                null,
                ['token' => $token]
            );
            return false;
        }
    }

    /**
     * Complete password reset
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return bool Whether reset was successful
     */
    public function completeReset($token, $newPassword) {
        try {
            $userData = $this->validateToken($token);
            if (!$userData) {
                throw new Exception("Invalid or expired token");
            }

            // Start transaction
            $this->conn->begin_transaction();

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET password = ? 
                WHERE id = ?
            ");
            
            $stmt->bind_param("si", $hashedPassword, $userData['user_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update password");
            }

            // Mark token as used
            $stmt = $this->conn->prepare("
                UPDATE password_reset_requests 
                SET used = 1 
                WHERE token = ?
            ");
            
            $stmt->bind_param("s", $token);
            if (!$stmt->execute()) {
                throw new Exception("Failed to mark token as used");
            }

            // Log the action
            $this->errorLogger->logError(
                'security',
                'Password reset completed',
                null,
                ['user_id' => $userData['user_id']]
            );

            // Commit transaction
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            
            $this->errorLogger->logError(
                'security',
                'Password reset completion failed: ' . $e->getMessage(),
                null,
                ['token' => $token]
            );
            return false;
        }
    }

    /**
     * Get password reset email template
     * @param string $username Username
     * @param string $resetLink Reset link
     * @return string Email body
     */
    private function getResetEmailTemplate($username, $resetLink) {
        return <<<EMAIL
Hello {$username},

A password reset was requested for your VikingsFit Gym account. If you did not request this, please ignore this email.

To reset your password, click the following link:
{$resetLink}

This link will expire in 1 hour.

For security reasons, if you did not request this reset, please log in to your account and change your password immediately.

Best regards,
VikingsFit Gym Team
EMAIL;
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens() {
        $stmt = $this->conn->prepare("
            DELETE FROM password_reset_requests 
            WHERE expires_at < NOW() OR used = 1
        ");
        $stmt->execute();
    }
}
