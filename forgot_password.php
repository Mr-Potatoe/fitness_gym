<?php
require_once 'config/config.php';
require_once 'includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = $securityManager->getPasswordReset()->initiateReset($email);
        
        if ($result) {
            $success = "If an account exists with this email, you will receive password reset instructions.";
        } else {
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .forgot-container {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-container">
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
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-content">
                    <span class="card-title">Forgot Password</span>
                    <p>Enter your email address and we'll send you instructions to reset your password.</p>
                    <form method="POST" action="">
                        <div class="input-field">
                            <i class="material-icons prefix">email</i>
                            <input type="email" id="email" name="email" required>
                            <label for="email">Email Address</label>
                        </div>
                        <button type="submit" class="btn waves-effect waves-light">
                            Send Reset Link
                            <i class="material-icons right">send</i>
                        </button>
                    </form>
                    <div class="card-action">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
