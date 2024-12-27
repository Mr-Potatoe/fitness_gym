<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/security_config.php';
require_once dirname(__DIR__) . '/includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

// Function to run tests
function runTests($securityManager) {
    $results = [];
    
    // Test 1: Rate Limiting
    try {
        $rateLimiter = $securityManager->getRateLimiter();
        for ($i = 0; $i < 6; $i++) {
            $limited = $rateLimiter->shouldLimit('login', '127.0.0.1');
            if ($i === 5 && !$limited) {
                throw new Exception("Rate limiting not working");
            }
        }
        $results[] = ['Rate Limiting', 'PASS', 'Successfully limited after 5 attempts'];
    } catch (Exception $e) {
        $results[] = ['Rate Limiting', 'FAIL', $e->getMessage()];
    }

    // Test 2: Password Reset
    try {
        $passwordReset = $securityManager->getPasswordReset();
        $result = $passwordReset->initiateReset('test@example.com');
        if (!$result) {
            throw new Exception("Failed to initiate password reset");
        }
        $results[] = ['Password Reset', 'PASS', 'Successfully initiated password reset'];
    } catch (Exception $e) {
        $results[] = ['Password Reset', 'FAIL', $e->getMessage()];
    }

    // Test 3: Two-Factor Authentication
    try {
        $twoFactorAuth = $securityManager->getTwoFactorAuth();
        $result = $twoFactorAuth->generateAndSendCode(1, 'test@example.com');
        if (!$result) {
            throw new Exception("Failed to generate 2FA code");
        }
        $results[] = ['Two-Factor Auth', 'PASS', 'Successfully generated 2FA code'];
    } catch (Exception $e) {
        $results[] = ['Two-Factor Auth', 'FAIL', $e->getMessage()];
    }

    // Test 4: File Upload
    try {
        $testFile = __DIR__ . '/test.jpg';
        if (!file_exists($testFile)) {
            throw new Exception("Test file not found");
        }

        $fileUploadHandler = $securityManager->getFileUploadHandler();
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $testFile,
            'error' => 0,
            'size' => filesize($testFile)
        ];

        if (!validateFileUpload($file)) {
            throw new Exception("File validation failed");
        }
        $results[] = ['File Upload', 'PASS', 'Successfully validated file upload'];
    } catch (Exception $e) {
        $results[] = ['File Upload', 'FAIL', $e->getMessage()];
    }

    // Test 5: Payment Processing
    try {
        $paymentProcessor = $securityManager->getPaymentProcessor();
        $paymentData = [
            'amount' => 1000,
            'method' => 'test',
            'reference' => 'TEST123',
            'account_number' => '1234567890',
            'account_name' => 'Test Account'
        ];

        $testFile = __DIR__ . '/test.jpg';
        if (!file_exists($testFile)) {
            throw new Exception("Test file not found");
        }

        $file = [
            'name' => 'payment.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $testFile,
            'error' => 0,
            'size' => filesize($testFile)
        ];

        $result = $paymentProcessor->processPayment($paymentData, $file, 1);
        if (!$result) {
            throw new Exception("Failed to process payment");
        }
        $results[] = ['Payment Processing', 'PASS', 'Successfully processed test payment'];
    } catch (Exception $e) {
        $results[] = ['Payment Processing', 'FAIL', $e->getMessage()];
    }

    return $results;
}

// Run tests and display results
$testResults = runTests($securityManager);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Tests - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .test-result {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .test-pass {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
        .test-fail {
            background-color: #ffcdd2;
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h4>Security Tests Results</h4>
        
        <?php foreach ($testResults as $test): ?>
            <div class="test-result <?php echo $test[1] === 'PASS' ? 'test-pass' : 'test-fail'; ?>">
                <strong><?php echo htmlspecialchars($test[0]); ?>:</strong>
                <?php echo htmlspecialchars($test[1]); ?><br>
                <small><?php echo htmlspecialchars($test[2]); ?></small>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 30px;">
            <a href="../admin/dashboard.php" class="btn waves-effect waves-light">
                Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
