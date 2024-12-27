<?php
// Add this at the top of the file, after the opening PHP tag
// Check for active subscription
$has_active_subscription = false;
if (isLoggedIn() && hasRole('member')) {
    $member_id = $_SESSION['user_id'];
    $subscription_check = "SELECT COUNT(*) as count 
                          FROM subscriptions 
                          WHERE user_id = $member_id 
                          AND status = 'active'";
    $result = $conn->query($subscription_check);
    if ($result) {
        $has_active_subscription = $result->fetch_assoc()['count'] > 0;
    }
}
?>

<nav class="blue darken-3">
    <div class="nav-wrapper">
        <a href="#!" class="brand-logo"><?php echo $page_title ?? 'Member Dashboard'; ?></a>
        <a href="#" data-target="member-sidenav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
        <ul class="right hide-on-med-and-down">
            <li>
                <a href="#!" class="dropdown-trigger" data-target="user-dropdown">
                    <i class="material-icons left">person</i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                    <i class="material-icons right">arrow_drop_down</i>
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Sidenav -->
<ul id="member-sidenav" class="sidenav sidenav-fixed">
    <li class="gym-header">
        <div class="gym-name">
            Vikings Fitness
        </div>
    </li>

    <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/member/dashboard.php" class="waves-effect">
            <i class="material-icons">dashboard</i>Dashboard
        </a>
    </li>

    <li class="<?php echo $current_page === 'plans' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/member/plans.php" class="waves-effect">
            <i class="material-icons">fitness_center</i>Membership Plans
        </a>
    </li>

    <li class="<?php echo $current_page === 'subscriptions' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/member/subscriptions.php" class="waves-effect">
            <i class="material-icons">card_membership</i>My Subscriptions
        </a>
    </li>

    <li class="<?php echo $current_page === 'payments' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/member/payments.php" class="waves-effect">
            <i class="material-icons">payment</i>Payment History
        </a>
    </li>

    <li>
        <div class="divider"></div>
    </li>

    <li class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/member/profile.php" class="waves-effect">
            <i class="material-icons">account_circle</i>My Profile
        </a>
    </li>

    <li>
        <a href="<?php echo SITE_URL; ?>/logout.php" class="waves-effect">
            <i class="material-icons">exit_to_app</i>Logout
        </a>
    </li>
</ul>

<style>
/* Update these styles */
.sidenav {
    width: 300px;
    height: 100%;
    background-color: #fff;
    top: 0;
    box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2);
}

.sidenav .gym-header {
    height: 64px;
    padding: 0 32px;
    background-color: #1565C0;
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.sidenav .gym-header .gym-name {
    color: white;
    font-size: 1.4rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: 0.5px;
}

.sidenav li > a {
    height: 48px;
    line-height: 48px;
    font-weight: normal;
    padding: 0 32px;
    display: flex;
    align-items: center;
}

.sidenav li > a > i {
    height: 48px;
    line-height: 48px;
    margin-right: 32px;
    color: #616161;
}

.sidenav li.active {
    background-color: #e3f2fd;
}

.sidenav li.active > a {
    color: #1976d2;
}

.sidenav li.active > a > i {
    color: #1976d2;
}

.divider {
    margin: 8px 0;
}

/* Navigation and main content spacing */
header, main, footer {
    padding-left: 300px;
}

nav {
    padding: 0 15px;
    height: 64px;
    line-height: 64px;
    position: fixed;
    top: 0;
    z-index: 997;
}

nav .brand-logo {
    font-size: 1.5rem;
    font-weight: 400;
}

@media only screen and (min-width: 993px) {
    nav .brand-logo {
        margin-left: 310px;
    }
    main {
        margin-top: 65px;
    }
}

@media only screen and (max-width: 992px) {
    header, main, footer {
        padding-left: 0;
    }
    nav .brand-logo {
        margin-left: 0;
    }
}

/* Dropdown styles */
.dropdown-content {
    min-width: 200px;
}

.dropdown-content li > a {
    color: #424242;
    display: flex;
    align-items: center;
    padding: 14px 16px;
}

.dropdown-content li > a > i {
    margin-right: 16px;
}

.new.badge {
    float: none;
    margin-left: auto;
}
</style>

<?php
// Helper function for notification icons
function getNotificationIcon($type) {
    switch ($type) {
        case 'payment_verified':
            return 'check_circle';
        case 'payment_rejected':
            return 'cancel';
        case 'subscription_expiring':
            return 'access_time';
        case 'announcement':
            return 'announcement';
        default:
            return 'notifications';
    }
}

// Helper function for time ago
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}
?>

<!-- User Dropdown -->
<ul id="user-dropdown" class="dropdown-content">
    <li><a href="<?php echo SITE_URL; ?>/member/profile.php">
        <i class="material-icons">account_circle</i>Profile
    </a></li>
    <li class="divider"></li>
    <li><a href="<?php echo SITE_URL; ?>/logout.php" class="red-text">
        <i class="material-icons">exit_to_app</i>Logout
    </a></li>
</ul>

<style>
header, main, footer {
    padding-left: 300px;
}

@media only screen and (max-width : 992px) {
    header, main, footer {
        padding-left: 0;
    }
}

nav {
    padding: 0 15px;
    height: 64px;
    line-height: 64px;
}

nav .brand-logo {
    font-size: 1.5rem;
    font-weight: 400;
}

@media only screen and (min-width: 993px) {
    nav .brand-logo {
        margin-left: 310px;
    }
}

.sidenav {
    width: 300px;
    height: 100%;
    background-color: #fff;
}

.sidenav .user-view {
    position: relative;
    padding: 32px 32px 16px;
    margin-bottom: 8px;
}

.sidenav .user-view .background {
    overflow: hidden;
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: -1;
}

.sidenav .user-view .background img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidenav li > a {
    font-weight: normal;
    padding: 0 32px;
    display: flex;
    align-items: center;
}

.sidenav li > a > i {
    margin-right: 32px;
    color: #616161;
}

.sidenav li.active {
    background-color: #e3f2fd;
}

.sidenav li.active > a {
    color: #1976d2;
}

.sidenav li.active > a > i {
    color: #1976d2;
}

.dropdown-content {
    min-width: 200px;
}

.dropdown-content li > a {
    color: #424242;
    display: flex;
    align-items: center;
    padding: 14px 16px;
}

.dropdown-content li > a > i {
    margin-right: 16px;
}

.new.badge {
    float: none;
    margin-left: auto;
}

.divider {
    margin: 8px 0;
}
</style> 