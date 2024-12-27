<?php
require_once '../config/config.php';

if (isLoggedIn()) {
    redirect('/member/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'confirm_password', 'full_name', 'contact_number', 'address'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $error_message = 'All fields are required';
            break;
        }
    }

    if (!isset($error_message)) {
        $username = $conn->real_escape_string(trim($_POST['username']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $full_name = $conn->real_escape_string(trim($_POST['full_name']));
        $contact_number = $conn->real_escape_string(trim($_POST['contact_number']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        
        // Validate password match
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $error_message = 'Passwords do not match';
        } else {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            try {
                $conn->begin_transaction();
                
                // Check if username exists
                $check_username = "SELECT id FROM users WHERE username = '$username' AND permanently_deleted = 0";
                if ($conn->query($check_username)->num_rows > 0) {
                    throw new Exception('Username already exists');
                }
                
                // Check if email exists
                $check_email = "SELECT id FROM users WHERE email = '$email' AND permanently_deleted = 0";
                if ($conn->query($check_email)->num_rows > 0) {
                    throw new Exception('Email already registered');
                }
                
                // Insert new user
                $insert_sql = "INSERT INTO users (
                    username, 
                    password, 
                    email, 
                    full_name, 
                    contact_number, 
                    address, 
                    role,
                    status,
                    created_at
                ) VALUES (
                    '$username',
                    '$password',
                    '$email',
                    '$full_name',
                    '$contact_number',
                    '$address',
                    'member',
                    'active',
                    CURRENT_TIMESTAMP
                )";
                
                if ($conn->query($insert_sql)) {
                    $user_id = $conn->insert_id;
                    
                    // Create welcome notification
                    $notify_sql = "INSERT INTO notifications (
                        user_id, 
                        title, 
                        message, 
                        type
                    ) VALUES (
                        $user_id,
                        'Welcome to VikingsFit Gym!',
                        'Thank you for registering. Please check our membership plans to get started.',
                        'welcome'
                    )";
                    
                    if (!$conn->query($notify_sql)) {
                        throw new Exception('Error creating welcome notification');
                    }
                    
                    $conn->commit();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_role'] = 'member';
                    
                    // Redirect to dashboard
                    redirect('/member/dashboard.php');
                } else {
                    throw new Exception('Error creating account');
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FitnessPro Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .register-container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .register-card {
            padding: 20px;
            border-radius: 10px;
        }
        .input-field .prefix {
            font-size: 1.5rem;
            top: 0.5rem;
        }
        .input-field .prefix ~ input {
            margin-left: 3rem;
            width: calc(100% - 3rem);
        }
        .input-field .prefix ~ label {
            margin-left: 3rem;
        }
        .card-panel {
            margin-top: 0;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="grey lighten-4">
    <!-- Navigation -->
    <nav class="blue darken-3">
        <div class="nav-wrapper container">
            <a href="../index.php" class="brand-logo">VikingsFit</a>
            <a href="#" data-target="mobile-nav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
            <ul id="nav-mobile" class="right hide-on-med-and-down">
                <li><a href="../index.php">Home</a></li>
                <li><a href="../index.php#plans">Plans</a></li>
                <li><a href="../index.php#testimonials">Testimonials</a></li>
                <li><a href="../login.php" class="btn white black-text">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- Mobile Navigation -->
    <ul class="sidenav" id="mobile-nav">
        <li><a href="../index.php">Home</a></li>
        <li><a href="../index.php#plans">Plans</a></li>
        <li><a href="../index.php#testimonials">Testimonials</a></li>
        <li><a href="../login.php">Login</a></li>
    </ul>

    <!-- Registration Form -->
    <div class="container register-container">
        <div class="row">
            <div class="col s12 m8 offset-m2">
                <div class="card register-card">
                    <div class="card-content">
                        <h4 class="center-align blue-text text-darken-3">Create Account</h4>
                        <?php if (!empty($error_message)): ?>
                            <div class="card-panel red lighten-4 red-text">
                                <p><i class="material-icons tiny">error</i> <?php echo $error_message; ?></p>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="row" id="registerForm">
                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">person</i>
                                <input type="text" id="username" name="username" required minlength="3" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <label for="username">Username</label>
                            </div>

                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">email</i>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <label for="email">Email</label>
                            </div>

                            <div class="input-field col s12">
                                <i class="material-icons prefix">badge</i>
                                <input type="text" id="full_name" name="full_name" required 
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                <label for="full_name">Full Name</label>
                            </div>

                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">phone</i>
                                <input type="tel" id="contact_number" name="contact_number" required 
                                       value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                                <label for="contact_number">Contact Number</label>
                            </div>

                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">home</i>
                                <input type="text" id="address" name="address" required 
                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                                <label for="address">Address</label>
                            </div>

                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">lock</i>
                                <input type="password" id="password" name="password" required minlength="6">
                                <label for="password">Password</label>
                            </div>

                            <div class="input-field col s12 m6">
                                <i class="material-icons prefix">lock_outline</i>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                <label for="confirm_password">Confirm Password</label>
                            </div>

                            <div class="col s12 center-align" style="margin-top: 20px;">
                                <button type="submit" class="btn-large blue darken-3 waves-effect waves-light">
                                    Register <i class="material-icons right">send</i>
                                </button>
                                <p class="grey-text" style="margin-top: 15px;">
                                    Already have an account? <a href="../login.php" class="blue-text">Login here</a>
                                </p>
                            </div>
                        </form>
                    </div>
                    <div class="card-action center-align grey lighten-4">
                        <p>Already have an account?</p>
                        <a href="../login.php" class="btn waves-effect waves-light blue darken-3">
                            Login
                            <i class="material-icons right">login</i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            form.addEventListener('submit', function(e) {
                // Reset custom validity
                password.setCustomValidity('');
                confirmPassword.setCustomValidity('');

                // Check password length
                if (password.value.length < 6) {
                    password.setCustomValidity('Password must be at least 6 characters long');
                    e.preventDefault();
                    return;
                }

                // Check if passwords match
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    e.preventDefault();
                    return;
                }
            });

            // Initialize Materialize components
            M.AutoInit();
        });
    </script>
</body>
</html> 