<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'dashboard';
$page_title = 'Dashboard';

// Get member's active subscription
$member_id = $_SESSION['user_id'];

// Get user details
$user_query = "SELECT * FROM users WHERE id = $member_id";
$user = $conn->query($user_query)->fetch_assoc();

// Get active subscription
$subscription_query = "SELECT s.*, p.name as plan_name, p.duration_in_days 
                      FROM subscriptions s 
                      JOIN plans p ON s.plan_id = p.id 
                      WHERE s.user_id = $member_id 
                      AND s.status = 'active' 
                      AND s.deleted_at IS NULL 
                      ORDER BY s.created_at DESC 
                      LIMIT 1";
$subscription = $conn->query($subscription_query)->fetch_assoc();

// Only get announcements if user has active subscription
// $announcements = null;
// if ($subscription) {
//     $announcements_query = "SELECT * FROM announcements WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5";
//     $announcements = $conn->query($announcements_query);
// }
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
            <!-- Subscription Status -->
            <div class="col s12">
                <?php if ($subscription): ?>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    $status_icon = '';
                    
                    if ($subscription['status'] === 'active') {
                        $status_class = 'green';
                        $status_text = 'Active';
                        $status_icon = 'check_circle';
                    } else {
                        $status_class = 'orange';
                        $status_text = 'Pending Verification';
                        $status_icon = 'pending';
                    }
                    ?>
                    <div class="card <?php echo $status_class; ?> lighten-4">
                        <div class="card-content">
                            <span class="card-title">
                                <i class="material-icons left"><?php echo $status_icon; ?></i>
                                Membership Status
                            </span>
                            <div class="subscription-details">
                                <p>
                                    <strong>Plan:</strong> <?php echo htmlspecialchars($subscription['plan_name']); ?>
                                </p>
                                <p>
                                    <strong>Status:</strong> 
                                    <span class="<?php echo $status_class; ?>-text text-darken-2">
                                        <?php echo $status_text; ?>
                                    </span>
                                </p>
                                <?php
                                $start_date = new DateTime($subscription['created_at']);
                                $end_date = $start_date->add(new DateInterval('P' . $subscription['duration_in_days'] . 'D'));
                                $now = new DateTime();
                                $days_remaining = $end_date->diff($now)->days;
                                ?>
                                <p>
                                    <strong>Days Remaining:</strong> <?php echo $days_remaining; ?> days
                                </p>
                                <p>
                                    <strong>Expiry Date:</strong> <?php echo $end_date->format('F j, Y'); ?>
                                </p>
                                <div class="subscription-actions">
                                    <a href="subscriptions.php" class="btn blue waves-effect waves-light">
                                        <i class="material-icons left">visibility</i>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card grey lighten-4">
                        <div class="card-content">
                            <span class="card-title">
                                <i class="material-icons left">fitness_center</i>
                                No Active Membership
                            </span>
                            <p>You don't have an active membership. Subscribe to a plan to start your fitness journey!</p>
                            <div class="subscription-actions">
                                <a href="plans.php" class="btn blue waves-effect waves-light">
                                    <i class="material-icons left">add_circle</i>
                                    View Plans
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

          
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    
    <style>
        main {
            padding-left: 310px;
            padding-right: 30px;
            padding-top: 20px;
        }

        .subscription-details {
            margin-top: 20px;
        }

        .subscription-details p {
            margin: 10px 0;
            font-size: 1.1rem;
        }

        .subscription-actions {
            margin-top: 20px;
        }

        .announcements-list {
            margin-top: 20px;
        }

        .announcement-item {
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .announcement-title {
            font-weight: 500;
            font-size: 1.1rem;
        }

        .announcement-date {
            color: #666;
            font-size: 0.9rem;
        }

        .announcement-content {
            color: #333;
            margin: 0;
            line-height: 1.5;
        }

        @media only screen and (max-width: 992px) {
            main {
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media only screen and (max-width: 600px) {
            .announcement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</body>
</html>