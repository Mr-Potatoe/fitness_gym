<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'dashboard';
$page_title = 'Admin Dashboard';

// Get total and active members with active subscriptions
$members_query = "SELECT 
                    COUNT(DISTINCT u.id) as total,
                    COUNT(DISTINCT CASE 
                        WHEN u.status = 'active' AND s.status = 'active' 
                        AND s.start_date <= CURDATE() 
                        AND s.end_date >= CURDATE() 
                        THEN u.id 
                    END) as active_with_subscription
                 FROM users u 
                 LEFT JOIN subscriptions s ON u.id = s.user_id
                 WHERE u.role = 'member' 
                 AND u.permanently_deleted = 0";
$members_result = $conn->query($members_query);
$members_stats = $members_result->fetch_assoc();

$total_members = $members_stats['total'];
$active_members = $members_stats['active_with_subscription'];

// Get total active staff
$staff_query = "SELECT COUNT(*) as total FROM users 
                WHERE role = 'staff' 
                AND status = 'active' 
                AND permanently_deleted = 0";
$staff_result = $conn->query($staff_query);
$total_staff = $staff_result->fetch_assoc()['total'] ?? 0;

// Get total active subscriptions and revenue this month
$subscriptions_query = "SELECT 
                        COUNT(*) as total_subscriptions,
                        COALESCE(SUM(p.amount), 0) as monthly_revenue
                       FROM subscriptions s 
                       JOIN payments p ON s.id = p.subscription_id
                       WHERE s.status = 'active'
                       AND s.start_date <= CURDATE() 
                       AND s.end_date >= CURDATE()
                       AND p.status = 'verified'
                       AND MONTH(p.created_at) = MONTH(CURDATE())
                       AND YEAR(p.created_at) = YEAR(CURDATE())";
$subscriptions_result = $conn->query($subscriptions_query);
$subscription_stats = $subscriptions_result->fetch_assoc();
$total_subscriptions = $subscription_stats['total_subscriptions'];
$monthly_revenue = $subscription_stats['monthly_revenue'];

// Get total pending payments
$payments_query = "SELECT COUNT(*) as total FROM payments WHERE status = 'pending'";
$payments_result = $conn->query($payments_query);
$total_pending_payments = $payments_result->fetch_assoc()['total'] ?? 0;

// Get expiring subscriptions in next 7 days
$expiring_query = "SELECT COUNT(*) as total 
                  FROM subscriptions 
                  WHERE status = 'active'
                  AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$expiring_result = $conn->query($expiring_query);
$expiring_subscriptions = $expiring_result->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <main>
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col s12 m6 l3">
                <div class="card-stats card">
                    <div class="card-content blue white-text">
                        <p class="card-stats-title">Total Members</p>
                        <h4 class="card-stats-number"><?php echo $total_members; ?></h4>
                        <div class="card-stats-icon">
                            <i class="material-icons">group</i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col s12 m6 l3">
                <div class="card-stats card">
                    <div class="card-content green white-text">
                        <p class="card-stats-title">Active Members</p>
                        <h4 class="card-stats-number"><?php echo $active_members; ?></h4>
                        <small class="white-text">with active subscription</small>
                        <div class="card-stats-icon">
                            <i class="material-icons">how_to_reg</i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col s12 m6 l3">
                <div class="card-stats card">
                    <div class="card-content orange white-text">
                        <p class="card-stats-title">Active Staff</p>
                        <h4 class="card-stats-number"><?php echo $total_staff; ?></h4>
                        <div class="card-stats-icon">
                            <i class="material-icons">badge</i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col s12 m6 l3">
                <div class="card-stats card">
                    <div class="card-content red white-text">
                        <p class="card-stats-title">Pending Payments</p>
                        <h4 class="card-stats-number"><?php echo $total_pending_payments; ?></h4>
                        <div class="card-stats-icon">
                            <i class="material-icons">payments</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription Stats -->
        <div class="row">
            <div class="col s12 m6">
                <div class="card-stats card">
                    <div class="card-content pink white-text">
                        <p class="card-stats-title">Active Subscriptions</p>
                        <h4 class="card-stats-number"><?php echo $total_subscriptions; ?></h4>
                        <small class="white-text"><?php echo $expiring_subscriptions; ?> expiring in 7 days</small>
                        <div class="card-stats-icon">
                            <i class="material-icons">subscriptions</i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col s12 m6">
                <div class="card-stats card">
                    <div class="card-content purple white-text">
                        <p class="card-stats-title">Monthly Revenue</p>
                        <h4 class="card-stats-number">₱<?php echo number_format($monthly_revenue, 2); ?></h4>
                        <small class="white-text">This month</small>
                        <div class="card-stats-icon">
                            <i class="material-icons">account_balance_wallet</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <!-- Quick Actions -->
            <div class="col s12 m6">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Quick Actions</span>
                        <div class="collection">
                            <a href="members.php" class="collection-item">
                                <i class="material-icons left">group_add</i>Manage Members
                            </a>
                            <a href="staff.php" class="collection-item">
                                <i class="material-icons left">person_add</i>Manage Staff
                            </a>
                            <a href="announcements.php" class="collection-item">
                                <i class="material-icons left">campaign</i>Create Announcement
                            </a>
                            <a href="payments.php" class="collection-item">
                                <i class="material-icons left">payment</i>View Pending Payments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
        });
    </script>

    <style>
        .card-stats {
            position: relative;
            overflow: hidden;
            height: 200px; /* Set a fixed height for consistency */
        }
        .card-stats .card-content {
            position: relative;
            padding: 20px;
        }
        .card-stats-title {
            font-size: 1.1rem;
            margin: 0;
            margin-bottom: 10px;
        }
        .card-stats-number {
            font-size: 2.5rem;
            margin: 0;
            font-weight: 500;
        }
        .card-stats-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            opacity: 0.3;
        }
        .card-stats-icon i {
            font-size: 4rem;
        }
        .collection .collection-item {
            display: flex;
            align-items: center;
        }
        .collection .collection-item i {
            margin-right: 15px;
        }
    </style>
</body>
</html>