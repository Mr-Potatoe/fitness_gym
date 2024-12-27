<?php
require_once 'config/config.php';
require_once 'includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
    $code = $_POST['code'] ?? '';
    $userId = $_POST['user_id'] ?? null;

    if ($userId && $code) {
        // Handle 2FA verification
        $result = $securityManager->verify2FACode($userId, $code, $remember);
    } else {
        // Handle initial login
        $result = $securityManager->handleLogin($username, $password, $remember);
    }

    if ($result['success']) {
        if (isset($result['requires_2fa']) && $result['requires_2fa']) {
            // Show 2FA form
            $showTwoFactor = true;
            $twoFactorUserId = $result['user_id'];
        } else {
            // Redirect to appropriate page
            header('Location: ' . $result['redirect']);
            exit;
        }
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .login-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }
        .brand-logo {
            margin-bottom: 30px;
            text-align: center;
        }
        .error-message {
            color: #f44336;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="brand-logo">
                <h4>VikingsFit Gym</h4>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($showTwoFactor) && $showTwoFactor): ?>
                <!-- 2FA Form -->
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Two-Factor Authentication</span>
                        <p>Please enter the verification code sent to your email.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($twoFactorUserId); ?>">
                            <div class="input-field">
                                <input type="text" id="code" name="code" required>
                                <label for="code">Verification Code</label>
                            </div>
                            <button type="submit" class="btn waves-effect waves-light">
                                Verify Code
                                <i class="material-icons right">send</i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login Form -->
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Login</span>
                        <form method="POST" action="">
                            <div class="input-field">
                                <i class="material-icons prefix">person</i>
                                <input type="text" id="username" name="username" required>
                                <label for="username">Username</label>
                            </div>
                            <div class="input-field">
                                <i class="material-icons prefix">lock</i>
                                <input type="password" id="password" name="password" required>
                                <label for="password">Password</label>
                            </div>
                            <p>
                                <label>
                                    <input type="checkbox" name="remember">
                                    <span>Remember me</span>
                                </label>
                            </p>
                            <button type="submit" class="btn waves-effect waves-light">
                                Login
                                <i class="material-icons right">send</i>
                            </button>
                        </form>
                        <div class="card-action">
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>