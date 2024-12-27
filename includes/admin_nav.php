<nav class="blue darken-3">
    <div class="nav-wrapper">
        <a href="#!" class="brand-logo"><?php echo $page_title ?? 'Admin Dashboard'; ?></a>
        <a href="#" data-target="admin-sidenav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
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
    <li><a href="<?php echo SITE_URL; ?>/admin/profile.php">
        <i class="material-icons">account_circle</i>My Profile
    </a></li>
    <li><a href="<?php echo SITE_URL; ?>/admin/settings.php">
        <i class="material-icons">settings</i>Settings
    </a></li>
    <li class="divider"></li>
    <li><a href="<?php echo SITE_URL; ?>/admin/change_password.php">
        <i class="material-icons">lock</i>Change Password
    </a></li>
    <li class="divider"></li>
    <li><a href="<?php echo SITE_URL; ?>/logout.php" class="red-text">
        <i class="material-icons">exit_to_app</i>Logout
    </a></li>
</ul>

<!-- Fixed Admin Sidenav -->
<ul id="admin-sidenav" class="sidenav sidenav-fixed">
    <li>
        <div class="user-view">
            <div class="background blue darken-3"></div>
            <a href="#!"><span class="white-text name"><?php echo htmlspecialchars($_SESSION['username']); ?></span></a>
            <a href="#!"><span class="white-text email">Administrator</span></a>
        </div>
    </li>
    <li <?php echo $current_page === 'dashboard' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">
            <i class="material-icons">dashboard</i>Dashboard
        </a>
    </li>
    <li <?php echo $current_page === 'staff' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/staff.php">
            <i class="material-icons">badge</i>Staff
        </a>
    </li>
    <li <?php echo $current_page === 'members' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/members.php">
            <i class="material-icons">group</i>Members
        </a>
    </li>
    <li <?php echo $current_page === 'subscriptions' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/subscriptions.php">
            <i class="material-icons">subscriptions</i>Subscriptions
        </a>
    </li>
    <li <?php echo $current_page === 'payment_accounts' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/payment_accounts.php">
            <i class="material-icons" style="margin-bottom: 0;">account_balance</i>Accounts
        </a>
    </li>
    <li <?php echo $current_page === 'payments' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/payments.php">
            <i class="material-icons" style="margin-bottom: 0;">payments</i>Payments
        </a>
    </li>
    <li <?php echo $current_page === 'reports' ? 'class="active"' : ''; ?>>
        <a href="<?php echo SITE_URL; ?>/admin/reports.php">
            <i class="material-icons">assessment</i>Reports
        </a>
    </li>
    <li class="<?php echo $current_page === 'plans' ? 'active' : ''; ?>">
        <a href="<?php echo SITE_URL; ?>/admin/plans.php" class="waves-effect">
            <i class="material-icons">fitness_center</i>Plans
        </a>
    </li>
    <li>
        <div class="divider"></div>
    </li>
    <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
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
    /* Ensure dropdown appears above sidenav */
    .dropdown-content {
        z-index: 1000;
    }
    .dropdown-content {
        min-width: 200px !important;
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
        line-height: inherit;
        height: auto;
        margin-bottom: 0 !important;
        display: flex;
        align-items: center;
    }
</style> 