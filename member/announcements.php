<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'announcements';
$page_title = 'Announcements';

// Get member ID
$member_id = $_SESSION['user_id'];

// Check for active subscription
$subscription_query = "SELECT s.*, p.name as plan_name 
                      FROM subscriptions s 
                      JOIN plans p ON s.plan_id = p.id 
                      WHERE s.user_id = $member_id 
                      AND s.status = 'active' 
                      AND s.end_date >= CURRENT_DATE()
                      ORDER BY s.end_date DESC 
                      LIMIT 1";
$active_subscription = $conn->query($subscription_query)->fetch_assoc();

// Only get announcements if there's an active subscription
$announcements = null;
$announcements_data = [];

if ($active_subscription) {
    // Get all announcements
    $query = "SELECT a.*, u.full_name as author_name 
              FROM announcements a 
              JOIN users u ON a.created_by = u.id 
              WHERE a.status = 'active'
              ORDER BY a.created_at DESC";
    $announcements = $conn->query($query);

    // Mark announcements as read
    if ($announcements && $announcements->num_rows > 0) {
        // First get the announcement IDs
        $announcement_ids = [];
        while ($row = $announcements->fetch_assoc()) {
            $announcement_ids[] = $row['id'];
            $announcements_data[] = $row;
        }
        
        // Reset the result pointer
        $announcements->data_seek(0);
        
        // Insert read records if there are announcements
        if (!empty($announcement_ids)) {
            $values = [];
            foreach ($announcement_ids as $aid) {
                $values[] = "($aid, $member_id)";
            }
            
            $insert_reads = "INSERT IGNORE INTO announcement_reads 
                            (announcement_id, user_id) 
                            VALUES " . implode(',', $values);
            $conn->query($insert_reads);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/member.css">
</head>
<body>
    <?php include '../includes/member_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">
                            <i class="material-icons">campaign</i>
                            Announcements
                        </span>

                        <?php if (!$active_subscription): ?>
                            <div class="center-align subscription-required">
                                <i class="material-icons large">lock</i>
                                <h5>Subscription Required</h5>
                                <p>You need an active subscription to view announcements.</p>
                                <a href="plans.php" class="btn blue waves-effect waves-light">
                                    View Available Plans
                                    <i class="material-icons right">arrow_forward</i>
                                </a>
                            </div>
                        <?php elseif ($announcements && $announcements->num_rows > 0): ?>
                            <div class="announcements-list">
                                <?php while($announcement = $announcements->fetch_assoc()): ?>
                                    <div class="announcement-item">
                                        <div class="announcement-header">
                                            <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <div class="announcement-meta">
                                                <i class="material-icons tiny">person</i>
                                                <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                <i class="material-icons tiny">access_time</i>
                                                <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="announcement-content">
                                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="center-align grey-text">
                                <p>No announcements available.</p>
                            </div>
                        <?php endif; ?>
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
        /* Main content padding adjustments */
        main {
            padding-left: 310px;   /* Sidebar padding (250px + 60px) */
            padding-right: 30px;   /* Reduced right padding */
            padding-top: 20px;
            padding-bottom: 40px;
        }

        /* Container and grid adjustments */
        .row {
            max-width: 1280px;
            margin: 0 auto 0.75rem;  /* Reduced bottom margin */
            padding: 0 0.25rem;      /* Minimal padding */
        }

        /* Card adjustments */
        .card {
            margin: 0.15rem 0;      /* Minimal margin */
            border-radius: 4px;
        }

        .card .card-content {
            padding: 14px;          /* Reduced padding */
        }

        .card .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .card .card-title i {
            color: #1565C0;
        }

        /* Announcements list styles */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .announcement-item {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 16px;
            background-color: #fff;
        }

        .announcement-header {
            margin-bottom: 12px;
        }

        .announcement-header h5 {
            margin: 0 0 8px 0;
            font-size: 1.2rem;
            color: #1565C0;
            font-weight: 500;
        }

        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #757575;
            font-size: 0.9rem;
        }

        .announcement-meta i {
            font-size: 16px;
            color: #9e9e9e;
        }

        .announcement-content {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #424242;
            white-space: pre-line;
        }

        /* Responsive adjustments */
        @media only screen and (max-width: 992px) {
            main {
                padding-left: 30px;
                padding-right: 30px;
            }
        }

        @media only screen and (max-width: 600px) {
            main {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .row {
                padding: 0 0.15rem;
            }
            
            .card .card-content {
                padding: 12px;
            }

            .announcement-item {
                padding: 12px;
            }

            .announcement-meta {
                flex-wrap: wrap;
            }
        }

        .subscription-required {
            padding: 40px 20px;
        }

        .subscription-required i.large {
            font-size: 4rem;
            color: #9e9e9e;
            margin-bottom: 20px;
        }

        .subscription-required h5 {
            color: #616161;
            margin-bottom: 10px;
        }

        .subscription-required p {
            color: #757575;
            margin-bottom: 20px;
        }
    </style>
</body>
</html>