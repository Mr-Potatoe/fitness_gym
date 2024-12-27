<?php
class RateLimiter {
    private $conn;
    private $defaultLimit = 60; // requests per minute
    private $windowSize = 60; // seconds
    private $limits = [
        'login' => 5, // 5 attempts per minute
        'register' => 3, // 3 attempts per minute
        'payment' => 10, // 10 attempts per minute
        'api' => 100 // 100 requests per minute
    ];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Check if the request should be rate limited
     * @param string $endpoint The endpoint being accessed
     * @param string $ipAddress The IP address making the request
     * @return bool|array Returns false if not limited, or array with wait time if limited
     */
    public function shouldLimit($endpoint, $ipAddress) {
        $limit = $this->limits[$endpoint] ?? $this->defaultLimit;
        
        // Clean up old records
        $this->cleanup();

        // Get current window
        $stmt = $this->conn->prepare("
            SELECT requests, window_start 
            FROM rate_limits 
            WHERE ip_address = ? 
            AND endpoint = ? 
            AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY window_start DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param("ssi", $ipAddress, $endpoint, $this->windowSize);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // First request in this window
            $insertStmt = $this->conn->prepare("
                INSERT INTO rate_limits (ip_address, endpoint, requests, window_start)
                VALUES (?, ?, 1, NOW())
            ");
            $insertStmt->bind_param("ss", $ipAddress, $endpoint);
            $insertStmt->execute();
            return false;
        }

        $row = $result->fetch_assoc();
        $requests = $row['requests'];
        $windowStart = strtotime($row['window_start']);
        $now = time();
        $timePassed = $now - $windowStart;

        if ($timePassed < $this->windowSize) {
            if ($requests >= $limit) {
                // Rate limit exceeded
                return [
                    'limited' => true,
                    'wait_time' => $this->windowSize - $timePassed,
                    'limit' => $limit,
                    'requests' => $requests
                ];
            }

            // Update request count
            $updateStmt = $this->conn->prepare("
                UPDATE rate_limits 
                SET requests = requests + 1 
                WHERE ip_address = ? 
                AND endpoint = ? 
                AND window_start = ?
            ");
            $windowStartStr = date('Y-m-d H:i:s', $windowStart);
            $updateStmt->bind_param("sss", $ipAddress, $endpoint, $windowStartStr);
            $updateStmt->execute();
        } else {
            // Start new window
            $insertStmt = $this->conn->prepare("
                INSERT INTO rate_limits (ip_address, endpoint, requests, window_start)
                VALUES (?, ?, 1, NOW())
            ");
            $insertStmt->bind_param("ss", $ipAddress, $endpoint);
            $insertStmt->execute();
        }

        return false;
    }

    /**
     * Clean up old rate limiting records
     */
    private function cleanup() {
        $this->conn->query("
            DELETE FROM rate_limits 
            WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
    }

    /**
     * Get remaining requests for an IP and endpoint
     * @param string $endpoint The endpoint being accessed
     * @param string $ipAddress The IP address making the request
     * @return array Information about remaining requests
     */
    public function getRemainingRequests($endpoint, $ipAddress) {
        $limit = $this->limits[$endpoint] ?? $this->defaultLimit;
        
        $stmt = $this->conn->prepare("
            SELECT requests, window_start 
            FROM rate_limits 
            WHERE ip_address = ? 
            AND endpoint = ? 
            AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY window_start DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param("ssi", $ipAddress, $endpoint, $this->windowSize);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'remaining' => $limit,
                'limit' => $limit,
                'reset' => time() + $this->windowSize
            ];
        }

        $row = $result->fetch_assoc();
        $requests = $row['requests'];
        $windowStart = strtotime($row['window_start']);
        $now = time();
        $timePassed = $now - $windowStart;

        if ($timePassed >= $this->windowSize) {
            return [
                'remaining' => $limit,
                'limit' => $limit,
                'reset' => time() + $this->windowSize
            ];
        }

        return [
            'remaining' => max(0, $limit - $requests),
            'limit' => $limit,
            'reset' => $windowStart + $this->windowSize
        ];
    }
}
