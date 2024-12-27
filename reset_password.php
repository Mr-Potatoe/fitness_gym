<?php
require_once 'config/config.php';
require_once 'includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

// Get token from URL
$token = $_GET['token'] ?? '';
$userData = null;

if (!empty($token)) {
    $userData = $securityManager->getPasswordReset()->validateToken($token);
    if (!$userData) {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $result = $securityManager->getPasswordReset()->completeReset($token, $password);
        
        if ($result) {
            $success = "Password reset successful. You can now login with your new password.";
            $userData = null; // Hide the form
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .reset-container {
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
        .success-message {
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="brand-logo">
                <h4>VikingsFit Gym</h4>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                    <p><a href="login.php" class="btn waves-effect waves-light">Go to Login</a></p>
                </div>
            <?php endif; ?>

            <?php if ($userData): ?>
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Reset Password</span>
                        <p>Please enter your new password.</p>
                        <form method="POST" action="" id="resetForm">
                            <div class="input-field">
                                <i class="material-icons prefix">lock</i>
                                <input type="password" id="password" name="password" required 
                                       minlength="8" onkeyup="checkPasswordStrength(this.value)">
                                <label for="password">New Password</label>
                                <div id="passwordStrength" class="password-strength"></div>
                            </div>
                            <div class="input-field">
                                <i class="material-icons prefix">lock</i>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       required minlength="8" onkeyup="checkPasswordMatch()">
                                <label for="confirm_password">Confirm Password</label>
                                <div id="passwordMatch" class="password-strength"></div>
                            </div>
                            <button type="submit" class="btn waves-effect waves-light">
                                Reset Password
                                <i class="material-icons right">send</i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (!isset($success)): ?>
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Invalid Reset Link</span>
                        <p>This password reset link is invalid or has expired.</p>
                        <a href="forgot_password.php" class="btn waves-effect waves-light">
                            Request New Reset Link
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            const strength = {
                0: "Very Weak",
                1: "Weak",
                2: "Medium",
                3: "Strong",
                4: "Very Strong"
            };
            let score = 0;
            
            // Length check
            if (password.length >= 8) score++;
            // Contains number
            if (/\d/.test(password)) score++;
            // Contains lowercase
            if (/[a-z]/.test(password)) score++;
            // Contains uppercase
            if (/[A-Z]/.test(password)) score++;
            // Contains special char
            if (/[^A-Za-z0-9]/.test(password)) score++;

            strengthDiv.textContent = `Strength: ${strength[score]}`;
            strengthDiv.style.color = ['#f44336', '#FF5722', '#FFC107', '#8BC34A', '#4CAF50'][score];
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchDiv.textContent = 'Passwords match';
                    matchDiv.style.color = '#4CAF50';
                } else {
                    matchDiv.textContent = 'Passwords do not match';
                    matchDiv.style.color = '#f44336';
                }
            } else {
                matchDiv.textContent = '';
            }
        }
    </script>
</body>
</html>
