<?php
class FileUploadHandler {
    private $conn;
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    private $errorLogger;

    public function __construct($conn, $uploadDir, $errorLogger) {
        $this->conn = $conn;
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->errorLogger = $errorLogger;
        
        // Default allowed types
        $this->allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf'
        ];
        
        // Default max file size (5MB)
        $this->maxFileSize = 5 * 1024 * 1024;
    }

    /**
     * Set allowed file types
     * @param array $types Array of allowed mime types and their extensions
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }

    /**
     * Set maximum file size
     * @param int $size Maximum file size in bytes
     */
    public function setMaxFileSize($size) {
        $this->maxFileSize = $size;
    }

    /**
     * Handle file upload
     * @param array $file $_FILES array element
     * @param int $userId ID of user uploading the file
     * @param string $subDir Optional subdirectory within upload directory
     * @return array|false Returns file info array on success, false on failure
     */
    public function handleUpload($file, $userId, $subDir = '') {
        try {
            // Validate file
            $this->validateFile($file);

            // Generate unique filename
            $fileInfo = $this->getUploadedFileInfo($file);
            $storedName = $this->generateUniqueFilename($fileInfo['extension']);
            
            // Create target directory if it doesn't exist
            $targetDir = $this->uploadDir;
            if ($subDir) {
                $targetDir .= '/' . trim($subDir, '/');
            }
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $targetPath = $targetDir . '/' . $storedName;
            
            // Move file to target location
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("Failed to move uploaded file");
            }

            // Calculate file hash
            $fileHash = hash_file('sha256', $targetPath);

            // Store file information in database
            $stmt = $this->conn->prepare("
                INSERT INTO file_uploads (
                    original_name, stored_name, file_path, file_type,
                    file_size, mime_type, hash, uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $relativePath = ($subDir ? $subDir . '/' : '') . $storedName;
            
            $stmt->bind_param(
                "ssssissi",
                $file['name'],
                $storedName,
                $relativePath,
                $fileInfo['extension'],
                $file['size'],
                $file['type'],
                $fileHash,
                $userId
            );

            if (!$stmt->execute()) {
                // If database insert fails, delete the uploaded file
                unlink($targetPath);
                throw new Exception("Failed to store file information in database");
            }

            $fileId = $this->conn->insert_id;

            // Scan file for viruses (if ClamAV is available)
            $this->scanFile($fileId, $targetPath);

            return [
                'id' => $fileId,
                'original_name' => $file['name'],
                'stored_name' => $storedName,
                'path' => $relativePath,
                'size' => $file['size'],
                'type' => $file['type'],
                'hash' => $fileHash
            ];

        } catch (Exception $e) {
            $this->errorLogger->logError('file_upload', $e->getMessage(), null, [
                'file' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ]);
            return false;
        }
    }

    /**
     * Validate uploaded file
     * @param array $file $_FILES array element
     * @throws Exception if validation fails
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed with error code: " . $file['error']);
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("File size exceeds maximum allowed size");
        }

        // Check file type
        if (!isset($this->allowedTypes[$file['type']])) {
            throw new Exception("File type not allowed");
        }

        // Verify it's a real image if it claims to be one
        if (strpos($file['type'], 'image/') === 0) {
            if (!getimagesize($file['tmp_name'])) {
                throw new Exception("Invalid image file");
            }
        }

        // Additional security checks
        $this->performSecurityChecks($file);
    }

    /**
     * Perform additional security checks on the file
     * @param array $file $_FILES array element
     * @throws Exception if security checks fail
     */
    private function performSecurityChecks($file) {
        // Check for PHP code in file
        $content = file_get_contents($file['tmp_name']);
        $dangerousPatterns = [
            '<?php',
            '<?=',
            '<script',
            'eval(',
            'exec(',
            'system(',
            'shell_exec('
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                throw new Exception("File contains potentially malicious code");
            }
        }

        // Check for double file extensions
        $filename = $file['name'];
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            throw new Exception("Multiple file extensions not allowed");
        }
    }

    /**
     * Get information about an uploaded file
     * @param array $file $_FILES array element
     * @return array File information
     */
    private function getUploadedFileInfo($file) {
        $extension = $this->allowedTypes[$file['type']];
        return [
            'extension' => $extension,
            'mime_type' => $file['type']
        ];
    }

    /**
     * Generate a unique filename
     * @param string $extension File extension
     * @return string Unique filename
     */
    private function generateUniqueFilename($extension) {
        return uniqid(more_entropy: true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }

    /**
     * Scan file for viruses using ClamAV
     * @param int $fileId ID of the file in database
     * @param string $filePath Path to the file
     */
    private function scanFile($fileId, $filePath) {
        // Check if ClamAV is available
        if (!function_exists('clamav_scan_file')) {
            // Update scan status to pending
            $stmt = $this->conn->prepare("
                UPDATE file_uploads 
                SET scan_status = 'pending'
                WHERE id = ?
            ");
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            return;
        }

        try {
            $result = clamav_scan_file($filePath);
            $status = $result === true ? 'clean' : 'infected';

            $stmt = $this->conn->prepare("
                UPDATE file_uploads 
                SET scan_status = ?
                WHERE id = ?
            ");
            $stmt->bind_param("si", $status, $fileId);
            $stmt->execute();

            if ($status === 'infected') {
                // Log the infection
                $this->errorLogger->logError('virus_scan', "Infected file detected", null, [
                    'file_id' => $fileId,
                    'file_path' => $filePath
                ]);

                // Delete the infected file
                unlink($filePath);
            }
        } catch (Exception $e) {
            $this->errorLogger->logError('virus_scan', $e->getMessage(), null, [
                'file_id' => $fileId,
                'file_path' => $filePath
            ]);
        }
    }

    /**
     * Delete a file
     * @param int $fileId ID of the file to delete
     * @return bool Whether deletion was successful
     */
    public function deleteFile($fileId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT stored_name, file_path 
                FROM file_uploads 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();

            if (!$file) {
                return false;
            }

            $filePath = $this->uploadDir . '/' . $file['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $deleteStmt = $this->conn->prepare("
                DELETE FROM file_uploads 
                WHERE id = ?
            ");
            $deleteStmt->bind_param("i", $fileId);
            return $deleteStmt->execute();

        } catch (Exception $e) {
            $this->errorLogger->logError('file_delete', $e->getMessage(), null, [
                'file_id' => $fileId
            ]);
            return false;
        }
    }

    /**
     * Get file information by ID
     * @param int $fileId ID of the file
     * @return array|false File information or false if not found
     */
    public function getFileInfo($fileId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM file_uploads 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
