<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

$current_page = 'dashboard';
$page_title = 'Staff Dashboard';

// Get staff statistics
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

$stats = [
    'total_members' => $members_stats['total'],
    'active_members' => $members_stats['active_with_subscription'],
    'pending_payments' => $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")->fetch_assoc()['count']
];
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
    <?php include '../includes/staff_nav.php'; ?>

    <main>
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col s12 m4">
                <div class="card blue darken-1">
                    <div class="card-content white-text">
                        <span class="card-title">Total Members</span>
                        <h3><?php echo $stats['total_members']; ?></h3>
                    </div>
                    <div class="card-action">
                        <a href="members.php" class="white-text">View Members</a>
                    </div>
                </div>
            </div>
            <div class="col s12 m4">
                <div class="card green darken-1">
                    <div class="card-content white-text">
                        <span class="card-title">Active Members</span>
                        <h3><?php echo $stats['active_members']; ?></h3>
                    </div>
                    <div class="card-action">
                        <a href="members.php" class="white-text">View Active Members</a>
                    </div>
                </div>
            </div>
            <div class="col s12 m4">
                <div class="card orange darken-1">
                    <div class="card-content white-text">
                        <span class="card-title">Pending Payments</span>
                        <h3><?php echo $stats['pending_payments']; ?></h3>
                    </div>
                    <div class="card-action">
                        <a href="payments.php" class="white-text">View Payments</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Recent Activities</span>
                        <table class="striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Activity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Add your recent activities data here -->
                                <tr>
                                    <td colspan="4" class="center-align">No recent activities</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Quick Actions</span>
                        <div class="collection">
                            <a href="members.php" class="collection-item">
                                <i class="material-icons left">group_add</i>View Members
                            </a>
                            <a href="payments.php" class="collection-item">
                                <i class="material-icons left">payment</i>Check Payments
                            </a>
                            <a href="announcements.php" class="collection-item">
                                <i class="material-icons left">campaign</i>View Announcements
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
        .card-title {
            font-size: 1.4rem;
            font-weight: 500;
        }
        .card h3 {
            margin: 15px 0 0;
            font-size: 2.5rem;
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