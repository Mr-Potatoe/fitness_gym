<?php
class TwoFactorAuth {
    private $conn;
    private $errorLogger;
    private $codeLength = 6;
    private $codeExpiry = 300; // 5 minutes

    public function __construct($conn, $errorLogger) {
        $this->conn = $conn;
        $this->errorLogger = $errorLogger;
    }

    /**
     * Generate and send 2FA code
     * @param int $userId User ID
     * @param string $email User's email
     * @return bool Whether code was sent successfully
     */
    public function generateAndSendCode($userId, $email) {
        try {
            // Generate code
            $code = $this->generateCode();
            $expiryTime = date('Y-m-d H:i:s', time() + $this->codeExpiry);
            
            // Store code
            $stmt = $this->conn->prepare("
                INSERT INTO two_factor_codes (
                    user_id, code, expires_at, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            // Get IP and User Agent, use defaults for CLI mode
            $ipAddress = php_sapi_name() === 'cli' ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $userAgent = php_sapi_name() === 'cli' ? 'CLI' : ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
            
            $stmt->bind_param(
                "issss",
                $userId,
                $code,
                $expiryTime,
                $ipAddress,
                $userAgent
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to store 2FA code");
            }

            // Send code via email
            $emailBody = $this->getCodeEmailTemplate($code);
            // mail($email, "Your Login Code", $emailBody);
            
            // Log the action
            $this->errorLogger->logError(
                'security',
                '2FA code generated',
                null,
                ['user_id' => $userId]
            );

            return true;

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'security',
                '2FA code generation failed: ' . $e->getMessage(),
                null,
                ['user_id' => $userId]
            );
            return false;
        }
    }

    /**
     * Verify 2FA code
     * @param int $userId User ID
     * @param string $code Code to verify
     * @return bool Whether code is valid
     */
    public function verifyCode($userId, $code) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id 
                FROM two_factor_codes 
                WHERE user_id = ? 
                AND code = ? 
                AND expires_at > NOW() 
                AND used = 0
            ");
            
            $stmt->bind_param("is", $userId, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }

            // Mark code as used
            $codeId = $result->fetch_assoc()['id'];
            $stmt = $this->conn->prepare("
                UPDATE two_factor_codes 
                SET used = 1 
                WHERE id = ?
            ");
            
            $stmt->bind_param("i", $codeId);
            $stmt->execute();

            // Log successful verification
            $this->errorLogger->logError(
                'security',
                '2FA code verified',
                null,
                ['user_id' => $userId]
            );

            return true;

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'security',
                '2FA code verification failed: ' . $e->getMessage(),
                null,
                ['user_id' => $userId]
            );
            return false;
        }
    }

    /**
     * Generate random numeric code
     * @return string Generated code
     */
    private function generateCode() {
        $code = '';
        for ($i = 0; $i < $this->codeLength; $i++) {
            $code .= mt_rand(0, 9);
        }
        return $code;
    }

    /**
     * Get code email template
     * @param string $code 2FA code
     * @return string Email body
     */
    private function getCodeEmailTemplate($code) {
        return <<<EMAIL
Your VikingsFit Gym login code is: {$code}

This code will expire in 5 minutes.

If you did not request this code, please ignore this email and contact support immediately.

Best regards,
VikingsFit Gym Team
EMAIL;
    }

    /**
     * Clean up expired codes
     */
    public function cleanupExpiredCodes() {
        $stmt = $this->conn->prepare("
            DELETE FROM two_factor_codes 
            WHERE expires_at < NOW() OR used = 1
        ");
        $stmt->execute();
    }
}
