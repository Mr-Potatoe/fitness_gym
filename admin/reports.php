<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'reports';
$page_title = 'Reports';

// Get date range from query parameters or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get reports data
$reports_data = [
    'total_revenue' => 0,
    'total_members' => 0,
    'active_members' => 0,
    'active_subscriptions' => 0,
    'new_members' => 0,
    'payment_methods' => [],
    'subscription_plans' => [],
    'revenue_by_plan' => []
];

// Get total revenue for the period
$revenue_query = "SELECT COALESCE(SUM(p.amount), 0) as total 
                 FROM payments p 
                 WHERE p.status = 'verified' 
                 AND DATE(p.created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($revenue_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$revenue_result = $stmt->get_result();
$reports_data['total_revenue'] = $revenue_result->fetch_assoc()['total'] ?? 0;

// Get member statistics
$members_query = "SELECT 
                    COUNT(DISTINCT u.id) as total_members,
                    COUNT(DISTINCT CASE 
                        WHEN u.status = 'active' AND s.status = 'active' 
                        AND s.start_date <= CURDATE() 
                        AND s.end_date >= CURDATE() 
                        THEN u.id 
                    END) as active_members
                 FROM users u 
                 LEFT JOIN subscriptions s ON u.id = s.user_id
                 WHERE u.role = 'member' 
                 AND u.permanently_deleted = 0";
$members_result = $conn->query($members_query);
$members_stats = $members_result->fetch_assoc();
$reports_data['total_members'] = $members_stats['total_members'];
$reports_data['active_members'] = $members_stats['active_members'];

// Get active subscriptions
$subs_query = "SELECT COUNT(*) as total 
               FROM subscriptions 
               WHERE status = 'active' 
               AND start_date <= CURDATE() 
               AND end_date >= CURDATE()";
$subs_result = $conn->query($subs_query);
$reports_data['active_subscriptions'] = $subs_result->fetch_assoc()['total'];

// Get new members for the period
$new_members_query = "SELECT COUNT(*) as total 
                     FROM users 
                     WHERE role = 'member' 
                     AND permanently_deleted = 0
                     AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($new_members_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$new_members_result = $stmt->get_result();
$reports_data['new_members'] = $new_members_result->fetch_assoc()['total'];

// Get payment methods distribution
$payment_methods_query = "SELECT 
                            payment_method, 
                            COUNT(*) as count,
                            SUM(amount) as total_amount
                         FROM payments 
                         WHERE status = 'verified' 
                         AND DATE(created_at) BETWEEN ? AND ? 
                         GROUP BY payment_method";
$stmt = $conn->prepare($payment_methods_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_methods_result = $stmt->get_result();
while ($row = $payment_methods_result->fetch_assoc()) {
    $reports_data['payment_methods'][$row['payment_method']] = [
        'count' => $row['count'],
        'amount' => $row['total_amount']
    ];
}

// Get subscription plans distribution and revenue
$plans_query = "SELECT 
                    p.name, 
                    COUNT(*) as count,
                    p.duration_months,
                    SUM(py.amount) as total_revenue
                FROM subscriptions s 
                JOIN plans p ON s.plan_id = p.id 
                JOIN payments py ON s.id = py.subscription_id
                WHERE s.status = 'active' 
                AND py.status = 'verified'
                AND DATE(s.created_at) BETWEEN ? AND ? 
                GROUP BY p.id";
$stmt = $conn->prepare($plans_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$plans_result = $stmt->get_result();
while ($row = $plans_result->fetch_assoc()) {
    $reports_data['subscription_plans'][$row['name']] = [
        'count' => $row['count'],
        'duration' => $row['duration_months'],
        'revenue' => $row['total_revenue']
    ];
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <!-- Date Range Filter -->
                        <div class="row">
                            <form class="col s12" method="GET">
                                <div class="row">
                                    <div class="input-field col s12 m5">
                                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                        <label for="start_date">Start Date</label>
                                    </div>
                                    <div class="input-field col s12 m5">
                                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                        <label for="end_date">End Date</label>
                                    </div>
                                    <div class="input-field col s12 m2">
                                        <button type="submit" class="btn blue waves-effect waves-light">
                                            Filter <i class="material-icons right">filter_list</i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row">
                            <div class="col s12 m6 l3">
                                <div class="card-panel blue white-text">
                                    <h5>Total Revenue</h5>
                                    <h4>₱<?php echo number_format($reports_data['total_revenue'], 2); ?></h4>
                                </div>
                            </div>
                            <div class="col s12 m6 l3">
                                <div class="card-panel green white-text">
                                    <h5>Active Members</h5>
                                    <h4><?php echo $reports_data['active_members']; ?></h4>
                                    <small class="white-text">of <?php echo $reports_data['total_members']; ?> total members</small>
                                </div>
                            </div>
                            <div class="col s12 m6 l3">
                                <div class="card-panel orange white-text">
                                    <h5>Active Subscriptions</h5>
                                    <h4><?php echo $reports_data['active_subscriptions']; ?></h4>
                                </div>
                            </div>
                            <div class="col s12 m6 l3">
                                <div class="card-panel red white-text">
                                    <h5>New Members</h5>
                                    <h4><?php echo $reports_data['new_members']; ?></h4>
                                    <small class="white-text">in selected period</small>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="row">
                            <div class="col s12 m6">
                                <div class="card">
                                    <div class="card-content">
                                        <span class="card-title">Payment Methods Distribution</span>
                                        <canvas id="paymentMethodsChart"></canvas>
                                        <div class="data-table">
                                            <table class="striped">
                                                <thead>
                                                    <tr>
                                                        <th>Method</th>
                                                        <th>Count</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reports_data['payment_methods'] as $method => $data): ?>
                                                    <tr>
                                                        <td><?php echo ucfirst($method); ?></td>
                                                        <td><?php echo $data['count']; ?></td>
                                                        <td>₱<?php echo number_format($data['amount'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col s12 m6">
                                <div class="card">
                                    <div class="card-content">
                                        <span class="card-title">Subscription Plans Distribution</span>
                                        <canvas id="subscriptionPlansChart"></canvas>
                                        <div class="data-table">
                                            <table class="striped">
                                                <thead>
                                                    <tr>
                                                        <th>Plan</th>
                                                        <th>Duration</th>
                                                        <th>Count</th>
                                                        <th>Revenue</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reports_data['subscription_plans'] as $plan => $data): ?>
                                                    <tr>
                                                        <td><?php echo $plan; ?></td>
                                                        <td><?php echo $data['duration']; ?> months</td>
                                                        <td><?php echo $data['count']; ?></td>
                                                        <td>₱<?php echo number_format($data['revenue'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

            // Payment Methods Chart
            new Chart(document.getElementById('paymentMethodsChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($reports_data['payment_methods'])); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($reports_data['payment_methods'], 'count')); ?>,
                        backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#F44336']
                    }]
                }
            });

            // Subscription Plans Chart
            new Chart(document.getElementById('subscriptionPlansChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($reports_data['subscription_plans'])); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($reports_data['subscription_plans'], 'count')); ?>,
                        backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#F44336']
                    }]
                }
            });
        });
    </script>

    <style>
        .card-panel {
            position: relative;
            padding: 20px;
            margin: 0.5rem 0 1rem 0;
            border-radius: 2px;
        }
        .card-panel h5 {
            margin: 0;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .card-panel h4 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 400;
        }
        .card-panel small {
            display: block;
            margin-top: 5px;
            opacity: 0.8;
        }
        .data-table {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .data-table table {
            font-size: 0.9rem;
        }
        canvas {
            margin-bottom: 20px;
        }
        @media only screen and (max-width: 600px) {
            .card-panel h4 {
                font-size: 1.8rem;
            }
        }
    </style>
</body>
</html>