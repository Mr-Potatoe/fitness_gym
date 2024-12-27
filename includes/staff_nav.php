<?php
// Check for any staff-specific data needed in navigation
?>

<nav class="blue darken-3">
    <div class="nav-wrapper">
        <a href="#!" class="brand-logo"><?php echo $page_title ?? 'Staff Dashboard'; ?></a>
        <a href="#" data-target="staff-sidenav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
        <ul class="right hide-on-med-and-down">
            <li><a href="#!" class="dropdown-trigger" data-target="user-dropdown">
                <i class="material-icons left">person</i>
                <?php echo htmlspecialchars($_SESSION['username']); ?>
                <i class="material-icons right">arrow_drop_down</i>
            </a></li>
        </ul>
    </div>
</nav>

<!-- User Dropdown -->
<ul id="user-dropdown" class="dropdown-content">
    <li><a href="#!"><i class="material-icons">person</i>Profile</a></li>
    <li class="divider"></li>
    <li><a href="../logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
</ul>

<!-- Sidenav -->
<ul id="staff-sidenav" class="sidenav sidenav-fixed">
    <li>
        <div class="user-view">
            <div class="background blue darken-3"></div>
            <span class="white-text name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <span class="white-text email">Staff Member</span>
        </div>
    </li>
    <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/dashboard.php">
            <i class="material-icons">dashboard</i>Dashboard
        </a>
    </li>
    <li class="<?php echo $current_page === 'members' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/members.php">
            <i class="material-icons">group</i>Members
        </a>
    </li>
    <li class="<?php echo $current_page === 'plans' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/plans.php">
            <i class="material-icons">fitness_center</i>Plans
        </a>
    </li>
    <li class="<?php echo $current_page === 'subscriptions' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/subscriptions.php">
            <i class="material-icons">card_membership</i>Subscriptions
        </a>
    </li>
    <li class="<?php echo $current_page === 'payments' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/payments.php">
            <i class="material-icons">payment</i>Payments
        </a>
    </li>
    <li>
        <div class="divider"></div>
    </li>
    <li>
        <a href="../logout.php" class="waves-effect">
            <i class="material-icons">exit_to_app</i>Logout
        </a>
    </li>
</ul>

<!-- Mobile Navigation -->
<ul id="nav-mobile" class="sidenav">
    <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/dashboard.php">
            <i class="material-icons">dashboard</i>Dashboard
        </a>
    </li>
    <li class="<?php echo $current_page === 'members' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/members.php">
            <i class="material-icons">group</i>Members
        </a>
    </li>
    <li class="<?php echo $current_page === 'plans' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/plans.php">
            <i class="material-icons">fitness_center</i>Plans
        </a>
    </li>
    <li class="<?php echo $current_page === 'subscriptions' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/subscriptions.php">
            <i class="material-icons">card_membership</i>Subscriptions
        </a>
    </li>
    <li class="<?php echo $current_page === 'payments' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/staff/payments.php">
            <i class="material-icons">payment</i>Payments
        </a>
    </li>
</ul>

<style>
    .sidenav { 
        width: 250px;
        height: 100%;
        position: fixed !important;
        top: 0;
        left: 0;
        z-index: 999;
        overflow-y: auto;
    }
    .sidenav li>a { 
        display: flex !important; 
        align-items: center;
        padding: 0 32px;
        height: 48px;
        line-height: 48px;
    }
    .sidenav li>a>i { 
        margin-right: 32px !important;
        width: 24px;
        text-align: center;
        line-height: inherit;
        height: auto;
        margin-bottom: 0 !important;
        display: flex;
        align-items: center;
    }
    main { 
        padding: 20px; 
        margin-left: 250px; 
    }
    @media only screen and (max-width: 992px) {
        main { margin-left: 0; }
    }
    .sidenav .user-view {
        padding: 32px 32px 16px;
        margin-bottom: 8px;
        background-color: #1565C0;
    }
    .sidenav li.active > a {
        background-color: rgba(0,0,0,0.05);
        color: #2196F3;
    }
    .sidenav li.active > a i {
        color: #2196F3;
    }
    .brand-logo {
        margin-left: 15px;
    }
    @media only screen and (min-width: 993px) {
        .brand-logo {
            margin-left: 250px;
        }
        nav .nav-wrapper {
            padding-left: 250px;
        }
    }
    .sidenav .divider {
        margin: 8px 0;
    }
    /* Fixed header */
    nav {
        position: fixed;
        top: 0;
        z-index: 998;
        width: 100%;
    }
    /* Adjust main content */
    main {
        margin-top: 64px;
    }
    /* Dropdown styles */
    .dropdown-content {
        min-width: 200px !important;
        z-index: 1000;
    }
    .dropdown-content li > a {
        display: flex !important;
        align-items: center;
        padding: 0 16px;
        height: 48px;
        line-height: 48px;
    }
    .dropdown-content li > a > i {
        margin-right: 16px !important;
        width: 24px;
        text-align: center;
    }
    /* Page title style */
    .page-title {
        margin: 20px 0;
        text-align: center;
        color: #1976d2;
    }
</style> 