<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'payments';
$page_title = 'Payment History';

// Get member's payments with subscription and plan details
$member_id = $_SESSION['user_id'];
$query = "SELECT p.*, s.start_date, s.end_date, pl.name as plan_name,
          pl.duration_months, pl.price
          FROM payments p 
          JOIN subscriptions s ON p.subscription_id = s.id 
          JOIN plans pl ON s.plan_id = pl.id
          WHERE s.user_id = ? 
          ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$payments = $stmt->get_result();
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
                            <i class="material-icons">receipt</i>
                            Payment History
                        </span>

                        <?php if ($payments && $payments->num_rows > 0): ?>
                            <div class="payment-list">
                                <?php while($payment = $payments->fetch_assoc()): ?>
                                    <div class="payment-item">
                                        <div class="payment-header">
                                            <div class="plan-info">
                                                <h5><?php echo htmlspecialchars($payment['plan_name']); ?></h5>
                                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </div>
                                            <div class="price-info">
                                                ₱<?php echo number_format($payment['price'], 2); ?>
                                            </div>
                                        </div>

                                        <div class="payment-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Payment Date:</span>
                                                <span class="detail-value"><?php echo date('F j, Y', strtotime($payment['created_at'])); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Method:</span>
                                                <span class="detail-value"><?php echo strtoupper($payment['payment_method']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Period:</span>
                                                <span class="detail-value">
                                                    <?php echo date('M j, Y', strtotime($payment['start_date'])); ?> - 
                                                    <?php echo date('M j, Y', strtotime($payment['end_date'])); ?>
                                                </span>
                                            </div>
                                            <?php if ($payment['payment_proof']): ?>
                                            <div class="detail-row">
                                                <span class="detail-label">Proof:</span>
                                                <span class="detail-value">
                                                    <a href="#" onclick="viewPaymentProof('<?php echo htmlspecialchars($payment['payment_proof']); ?>')" class="blue-text">
                                                        View Payment Proof
                                                    </a>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="center-align grey-text">
                                <p>No payment history found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Payment Proof Modal -->
    <div id="proofModal" class="modal">
        <div class="modal-content">
            <h4>Payment Proof</h4>
            <img id="proofImage" src="" alt="Payment Proof" style="width: 100%; max-height: 70vh; object-fit: contain;">
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-blue btn-flat">Close</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
        });

        function viewPaymentProof(proofPath) {
            const modal = M.Modal.getInstance(document.getElementById('proofModal'));
            document.getElementById('proofImage').src = '../uploads/payments/' + proofPath;
            modal.open();
        }
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

        /* Payment list styles */
        .payment-list {
            display: flex;
            flex-direction: column;
            gap: 12px;              /* Reduced gap */
        }

        .payment-item {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 12px;          /* Reduced padding */
            background-color: #fff;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;     /* Reduced margin */
            padding-bottom: 8px;     /* Reduced padding */
            border-bottom: 1px solid #e0e0e0;
        }

        .plan-info h5 {
            margin: 0 0 6px 0;      /* Reduced margin */
            font-size: 1.1rem;      /* Reduced font size */
            color: #1565C0;
        }

        .price-info {
            font-size: 1.1rem;      /* Reduced font size */
            font-weight: 500;
            color: #1565C0;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;               /* Reduced gap */
        }

        /* Status badge styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
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

            .payment-header {
                flex-direction: column;
            }

            .price-info {
                margin-top: 8px;
            }
        }
    </style>
</body>
</html> 