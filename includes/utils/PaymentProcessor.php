<?php
class PaymentProcessor {
    private $conn;
    private $errorLogger;
    private $fileUploadHandler;
    private $encryptionKey;

    public function __construct($conn, $errorLogger, $fileUploadHandler, $encryptionKey) {
        $this->conn = $conn;
        $this->errorLogger = $errorLogger;
        $this->fileUploadHandler = $fileUploadHandler;
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Process a new payment
     * @param array $paymentData Payment details
     * @param array $file Payment proof file
     * @param int $userId User making the payment
     * @return array|false Payment result or false on failure
     */
    public function processPayment($paymentData, $file, $userId) {
        try {
            // Start transaction
            $this->conn->begin_transaction();

            // Validate payment data
            $this->validatePaymentData($paymentData);

            // Handle file upload
            $uploadResult = $this->fileUploadHandler->handleUpload(
                $file,
                $userId,
                'payments'
            );

            if (!$uploadResult) {
                throw new Exception("Failed to upload payment proof");
            }

            // Encrypt sensitive payment data
            $encryptedData = $this->encryptPaymentData($paymentData);

            // Create payment record
            $stmt = $this->conn->prepare("
                INSERT INTO payments (
                    user_id, amount, payment_method, reference_number,
                    proof_file_id, encrypted_data, status, notes,
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");

            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt->bind_param(
                "idssssss",
                $userId,
                $paymentData['amount'],
                $paymentData['method'],
                $paymentData['reference'],
                $uploadResult['id'],
                $encryptedData,
                $paymentData['notes'],
                $ipAddress,
                $userAgent
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create payment record");
            }

            $paymentId = $this->conn->insert_id;

            // Create payment audit trail
            $this->createAuditTrail($paymentId, 'created', $userId);

            // Commit transaction
            $this->conn->commit();

            // Log successful payment
            $this->errorLogger->logError(
                'payment',
                'Payment processed successfully',
                null,
                [
                    'payment_id' => $paymentId,
                    'user_id' => $userId,
                    'amount' => $paymentData['amount']
                ]
            );

            return [
                'payment_id' => $paymentId,
                'status' => 'pending',
                'message' => 'Payment processed successfully'
            ];

        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();

            $this->errorLogger->logError(
                'payment',
                'Payment processing failed: ' . $e->getMessage(),
                null,
                [
                    'user_id' => $userId,
                    'amount' => $paymentData['amount'] ?? null
                ]
            );

            return false;
        }
    }

    /**
     * Verify a payment
     * @param int $paymentId Payment ID
     * @param int $adminId Admin verifying the payment
     * @param string $action verify/reject
     * @param string $notes Verification notes
     * @return bool Whether verification was successful
     */
    public function verifyPayment($paymentId, $adminId, $action, $notes = '') {
        try {
            // Start transaction
            $this->conn->begin_transaction();

            // Get current payment status
            $stmt = $this->conn->prepare("
                SELECT status, user_id 
                FROM payments 
                WHERE id = ?
            ");
            
            $stmt->bind_param("i", $paymentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Payment not found");
            }

            $payment = $result->fetch_assoc();
            
            if ($payment['status'] !== 'pending') {
                throw new Exception("Payment already processed");
            }

            // Update payment status
            $status = $action === 'verify' ? 'verified' : 'rejected';
            
            $stmt = $this->conn->prepare("
                UPDATE payments 
                SET status = ?, 
                    verified_by = ?,
                    verified_at = NOW(),
                    verification_notes = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "sisi",
                $status,
                $adminId,
                $notes,
                $paymentId
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update payment status");
            }

            // Create audit trail
            $this->createAuditTrail($paymentId, $action, $adminId, $notes);

            // If payment is verified, update subscription status
            if ($status === 'verified') {
                $this->updateSubscriptionStatus($paymentId, $payment['user_id']);
            }

            // Commit transaction
            $this->conn->commit();

            // Log verification
            $this->errorLogger->logError(
                'payment',
                'Payment ' . $status,
                null,
                [
                    'payment_id' => $paymentId,
                    'admin_id' => $adminId,
                    'action' => $action
                ]
            );

            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();

            $this->errorLogger->logError(
                'payment',
                'Payment verification failed: ' . $e->getMessage(),
                null,
                [
                    'payment_id' => $paymentId,
                    'admin_id' => $adminId
                ]
            );

            return false;
        }
    }

    /**
     * Validate payment data
     * @param array $data Payment data to validate
     * @throws Exception if validation fails
     */
    private function validatePaymentData($data) {
        $required = ['amount', 'method', 'reference'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception("Invalid amount");
        }

        // Add more validation as needed
    }

    /**
     * Encrypt sensitive payment data
     * @param array $data Data to encrypt
     * @return string Encrypted data
     */
    private function encryptPaymentData($data) {
        $sensitiveData = json_encode([
            'account_number' => $data['account_number'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'reference' => $data['reference'],
            'timestamp' => time()
        ]);

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt(
            $sensitiveData,
            'aes-256-cbc',
            $this->encryptionKey,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt payment data
     * @param string $encryptedData Data to decrypt
     * @return array Decrypted data
     */
    private function decryptPaymentData($encryptedData) {
        $data = base64_decode($encryptedData);
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLen);
        $encrypted = substr($data, $ivLen);

        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $this->encryptionKey,
            0,
            $iv
        );

        return json_decode($decrypted, true);
    }

    /**
     * Create payment audit trail
     * @param int $paymentId Payment ID
     * @param string $action Action performed
     * @param int $userId User performing the action
     * @param string $notes Additional notes
     */
    private function createAuditTrail($paymentId, $action, $userId, $notes = '') {
        $stmt = $this->conn->prepare("
            INSERT INTO payment_audit_trail (
                payment_id, action, user_id, notes,
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $stmt->bind_param(
            "isssss",
            $paymentId,
            $action,
            $userId,
            $notes,
            $ipAddress,
            $userAgent
        );

        $stmt->execute();
    }

    /**
     * Update subscription status after payment verification
     * @param int $paymentId Payment ID
     * @param int $userId User ID
     */
    private function updateSubscriptionStatus($paymentId, $userId) {
        // Get subscription details from payment
        $stmt = $this->conn->prepare("
            SELECT s.* 
            FROM subscriptions s
            JOIN payments p ON p.subscription_id = s.id
            WHERE p.id = ?
        ");
        
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $subscription = $result->fetch_assoc();
            
            // Update subscription status
            $stmt = $this->conn->prepare("
                UPDATE subscriptions 
                SET status = 'active',
                    payment_status = 'paid',
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("i", $subscription['id']);
            $stmt->execute();
        }
    }

    /**
     * Get payment details
     * @param int $paymentId Payment ID
     * @return array|false Payment details or false if not found
     */
    public function getPaymentDetails($paymentId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, u.username, u.email,
                       f.original_name as proof_filename,
                       f.file_path as proof_path
                FROM payments p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN file_uploads f ON f.id = p.proof_file_id
                WHERE p.id = ?
            ");
            
            $stmt->bind_param("i", $paymentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }

            $payment = $result->fetch_assoc();
            
            // Decrypt sensitive data
            if ($payment['encrypted_data']) {
                $payment['decrypted_data'] = $this->decryptPaymentData(
                    $payment['encrypted_data']
                );
            }

            return $payment;

        } catch (Exception $e) {
            $this->errorLogger->logError(
                'payment',
                'Failed to get payment details: ' . $e->getMessage(),
                null,
                ['payment_id' => $paymentId]
            );
            return false;
        }
    }
}
