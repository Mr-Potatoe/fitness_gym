<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Fetch admin profile data
$admin_id = $_SESSION['user_id'];
$query = "SELECT username, email, full_name FROM users WHERE id = $admin_id";
$result = $conn->query($query);
$admin = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile update
    $new_username = $conn->real_escape_string($_POST['username']);
    $new_email = $conn->real_escape_string($_POST['email']);
    $new_full_name = $conn->real_escape_string($_POST['full_name']);

    $update_query = "UPDATE users SET username = '$new_username', email = '$new_email', full_name = '$new_full_name' WHERE id = $admin_id";
    if ($conn->query($update_query)) {
        $success_message = 'Profile updated successfully.';
    } else {
        $error_message = 'Error updating profile: ' . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    <div class="container">
        <h4>Profile</h4>
        <?php if (isset($success_message)): ?>
            <div class="card-panel green lighten-4 green-text"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="card-panel red lighten-4 red-text"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="input-field">
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                <label for="username">Username</label>
            </div>
            <div class="input-field">
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                <label for="email">Email</label>
            </div>
            <div class="input-field">
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                <label for="full_name">Full Name</label>
            </div>
            <button type="submit" class="btn blue">Update Profile</button>
        </form>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>