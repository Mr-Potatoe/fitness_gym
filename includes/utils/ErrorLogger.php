<?php
class ErrorLogger {
    private $conn;
    private $notificationEmail;
    private $environment;

    public function __construct($conn, $notificationEmail = null, $environment = 'production') {
        $this->conn = $conn;
        $this->notificationEmail = $notificationEmail;
        $this->environment = $environment;
    }

    /**
     * Log an error to the database and optionally send notification
     * @param string $errorType Type of error (e.g., 'database', 'validation', 'system')
     * @param string $message Error message
     * @param string|null $errorCode Optional error code
     * @param array $context Additional context data
     */
    public function logError($errorType, $message, $errorCode = null, $context = []) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $errorFile = $trace[0]['file'] ?? 'unknown';
        $errorLine = $trace[0]['line'] ?? 0;
        $stackTrace = json_encode($trace);
        
        // Handle CLI mode
        $isCli = php_sapi_name() === 'cli';
        $userId = $isCli ? null : ($_SESSION['user_id'] ?? null);
        $ipAddress = $isCli ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $isCli ? 'CLI' : ($_SERVER['HTTP_USER_AGENT'] ?? null);
        
        $requestData = json_encode([
            'get' => $isCli ? [] : $_GET,
            'post' => $isCli ? [] : $_POST,
            'server' => $isCli ? [] : $_SERVER,
            'context' => $context
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO error_logs (
                error_type, error_message, error_code, file_name, 
                line_number, stack_trace, request_data, user_id, 
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Fix the bind_param call by ensuring all parameters are properly typed
        $stmt->bind_param(
            "ssssisssss", 
            $errorType, 
            $message, 
            $errorCode, 
            $errorFile, 
            $errorLine, 
            $stackTrace, 
            $requestData, 
            $userId, 
            $ipAddress, 
            $userAgent
        );

        $stmt->execute();
        $errorId = $this->conn->insert_id;

        // Send notification for critical errors
        if ($this->shouldNotify($errorType)) {
            $this->sendNotification($errorId, $errorType, $message, $context);
        }

        // Log to system error log in development
        if ($this->environment === 'development') {
            error_log("[{$errorType}] {$message} in {$errorFile}:{$errorLine}");
        }
    }

    /**
     * Determine if an error notification should be sent
     * @param string $errorType Type of error
     * @return bool Whether to send notification
     */
    private function shouldNotify($errorType) {
        $criticalTypes = ['security', 'database', 'payment', 'system'];
        return in_array($errorType, $criticalTypes) && $this->notificationEmail !== null;
    }

    /**
     * Send error notification email
     * @param int $errorId Error ID from database
     * @param string $errorType Type of error
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function sendNotification($errorId, $errorType, $message, $context) {
        if (!$this->notificationEmail) return;

        $subject = "[VikingsFit Gym] Critical Error: {$errorType}";
        $body = "Error ID: {$errorId}\n";
        $body .= "Type: {$errorType}\n";
        $body .= "Message: {$message}\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        $body .= "Context:\n" . print_r($context, true);

        // Use PHP's mail function or a proper email library
        mail($this->notificationEmail, $subject, $body);
    }

    /**
     * Get recent errors with optional filtering
     * @param array $filters Filtering options
     * @param int $limit Number of errors to retrieve
     * @return array Array of error records
     */
    public function getRecentErrors($filters = [], $limit = 100) {
        $where = [];
        $params = [];
        $types = "";

        if (!empty($filters['error_type'])) {
            $where[] = "error_type = ?";
            $params[] = $filters['error_type'];
            $types .= "s";
        }

        if (!empty($filters['start_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['start_date'];
            $types .= "s";
        }

        if (!empty($filters['end_date'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['end_date'];
            $types .= "s";
        }

        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        $query = "
            SELECT * FROM error_logs 
            {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ?
        ";

        $params[] = $limit;
        $types .= "i";

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        $errors = [];
        while ($row = $result->fetch_assoc()) {
            $errors[] = $row;
        }

        return $errors;
    }

    /**
     * Clean up old error logs
     * @param int $days Number of days to keep logs for
     */
    public function cleanup($days = 30) {
        $stmt = $this->conn->prepare("
            DELETE FROM error_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->bind_param("i", $days);
        $stmt->execute();
    }
}
