<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'profile';
$page_title = 'My Profile';

// Get member's details
$member_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = $member_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $address = $conn->real_escape_string($_POST['address']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    }
    // Check if email is already taken by another user
    else {
        $email_check = $conn->query("SELECT id FROM users WHERE email = '$email' AND id != $member_id");
        if ($email_check->num_rows > 0) {
            $error_message = "Email is already taken by another user";
        }
    }

    // If no errors and password is being changed
    if (empty($error_message)) {
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $error_message = "Current password is incorrect";
            }
            // Validate new password
            elseif (strlen($new_password) < 6) {
                $error_message = "New password must be at least 6 characters long";
            }
            elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match";
            }
        }
    }

    // Update profile if no errors
    if (empty($error_message)) {
        $update_query = "UPDATE users SET 
                        full_name = '$full_name',
                        email = '$email',
                        contact_number = '$contact_number',
                        address = '$address'";
        
        // Add password update if new password is provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query .= ", password = '$hashed_password'";
        }
        
        $update_query .= " WHERE id = $member_id";

        if ($conn->query($update_query)) {
            $success_message = "Profile updated successfully";
            // Refresh user data
            $user_result = $conn->query($user_query);
            $user = $user_result->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VikingsFit Gym</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body>
    <?php include '../includes/member_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">
                            <i class="material-icons">person</i>
                            My Profile
                        </span>

                        <?php if ($success_message): ?>
                            <div class="success-message">
                                <i class="material-icons">check_circle</i>
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="error-message">
                                <i class="material-icons">error</i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="profile-form">
                            <div class="row">
                                <div class="input-field col s12 m6">
                                    <i class="material-icons prefix">account_circle</i>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    <label for="full_name">Full Name</label>
                                </div>

                                <div class="input-field col s12 m6">
                                    <i class="material-icons prefix">email</i>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <label for="email">Email</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s12 m6">
                                    <i class="material-icons prefix">phone</i>
                                    <input type="text" id="contact_number" name="contact_number" 
                                           value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                                    <label for="contact_number">Contact Number</label>
                                </div>

                                <div class="input-field col s12 m6">
                                    <i class="material-icons prefix">location_on</i>
                                    <textarea id="address" name="address" class="materialize-textarea" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    <label for="address">Address</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col s12">
                                    <span class="card-title">Change Password (Optional)</span>
                                </div>
                                <div class="input-field col s12 m4">
                                    <i class="material-icons prefix">lock</i>
                                    <input type="password" id="current_password" name="current_password">
                                    <label for="current_password">Current Password</label>
                                </div>

                                <div class="input-field col s12 m4">
                                    <i class="material-icons prefix">lock_outline</i>
                                    <input type="password" id="new_password" name="new_password">
                                    <label for="new_password">New Password</label>
                                </div>

                                <div class="input-field col s12 m4">
                                    <i class="material-icons prefix">lock_outline</i>
                                    <input type="password" id="confirm_password" name="confirm_password">
                                    <label for="confirm_password">Confirm New Password</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col s12 center-align">
                                    <button type="submit" class="btn blue waves-effect waves-light">
                                        Update Profile
                                        <i class="material-icons right">save</i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    
    <style>
        /* Main content padding adjustments */
        main {
            padding-left: 310px;
            padding-right: 30px;
            padding-top: 20px;
        }

        .success-message,
        .error-message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background-color: #E8F5E9;
            color: #2E7D32;
        }

        .error-message {
            background-color: #FFEBEE;
            color: #C62828;
        }

        .profile-form {
            margin-top: 20px;
        }

        .profile-form .card-title {
            font-size: 1.2rem;
            color: #1976d2;
            margin: 20px 0 10px 0;
        }

        .input-field .prefix {
            font-size: 1.5rem;
        }

        .input-field .prefix.active {
            color: #1976d2;
        }

        /* Make textarea higher */
        textarea.materialize-textarea {
            min-height: 100px;
        }

        @media only screen and (max-width: 992px) {
            main {
                padding-left: 0;
                padding-right: 0;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Materialize components
            M.updateTextFields();
            M.textareaAutoResize(document.querySelector('textarea'));
        });
    </script>
</body>
</html>
