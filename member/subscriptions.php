<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'subscriptions';
$page_title = 'My Subscriptions';

// Get member's subscriptions with plan details and payment status
$member_id = $_SESSION['user_id'];
$query = "SELECT s.*, 
          p.name as plan_name, 
          p.duration_months, 
          p.price,
          DATEDIFF(s.end_date, CURRENT_DATE()) as days_remaining,
          py.status as payment_status,
          py.payment_method,
          py.payment_proof,
          py.verified,
          py.verified_at,
          py.created_at as payment_date,
          s.created_at
          FROM subscriptions s 
          JOIN plans p ON s.plan_id = p.id 
          LEFT JOIN payments py ON s.id = py.subscription_id
          WHERE s.user_id = ? 
          ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$subscriptions = $stmt->get_result();
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
            <div class="col s12">
                <!-- Success Message Section -->
                <div id="successMessage" style="display: none;" class="card-panel green lighten-4 green-text text-darken-4">
                    <i class="material-icons left">check_circle</i>
                    <span></span>
                </div>

                <div class="card">
                    <div class="card-content">
                        <span class="card-title">
                            <i class="material-icons">subscriptions</i>
                            My Subscriptions
                        </span>

                        <?php if ($subscriptions && $subscriptions->num_rows > 0): ?>
                            <div class="subscription-list">
                                <?php while($subscription = $subscriptions->fetch_assoc()): 
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    if ($subscription['status'] === 'active') {
                                        $status_class = 'green';
                                        $status_text = 'Active';
                                    } elseif ($subscription['status'] === 'pending') {
                                        $status_class = 'orange';
                                        $status_text = 'Pending Verification';
                                    } elseif ($subscription['status'] === 'expired') {
                                        $status_class = 'grey';
                                        $status_text = 'Expired';
                                    } elseif ($subscription['status'] === 'cancelled') {
                                        $status_class = 'red';
                                        $status_text = 'Cancelled';
                                    }

                                    // Calculate hours since subscription creation
                                    $created_at = strtotime($subscription['created_at']);
                                    $hours_old = (time() - $created_at) / 3600;
                                ?>
                                    <div class="subscription-item">
                                        <div class="subscription-header">
                                            <div class="plan-info">
                                                <h5><?php echo htmlspecialchars($subscription['plan_name']); ?></h5>
                                                <span class="badge <?php echo $status_class; ?> white-text">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            <div class="subscription-dates">
                                                <p>
                                                    <i class="material-icons tiny">event</i>
                                                    Start: <?php echo date('M d, Y', strtotime($subscription['start_date'])); ?>
                                                </p>
                                                <p>
                                                    <i class="material-icons tiny">event</i>
                                                    End: <?php echo date('M d, Y', strtotime($subscription['end_date'])); ?>
                                                </p>
                                                <?php if ($subscription['days_remaining'] > 0 && $subscription['status'] === 'active'): ?>
                                                    <p class="days-remaining">
                                                        <i class="material-icons tiny">timer</i>
                                                        <?php echo $subscription['days_remaining']; ?> days remaining
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="subscription-details">
                                            <div class="payment-info">
                                                <p>
                                                    <strong>Amount:</strong> 
                                                    ₱<?php echo number_format($subscription['price'], 2); ?>
                                                </p>
                                                <p>
                                                    <strong>Payment Method:</strong> 
                                                    <?php echo ucfirst($subscription['payment_method']); ?>
                                                </p>
                                                <p>
                                                    <strong>Payment Date:</strong> 
                                                    <?php echo date('M d, Y h:i A', strtotime($subscription['payment_date'])); ?>
                                                </p>
                                                <p>
                                                    <strong>Payment Status:</strong>
                                                    <?php if ($subscription['verified']): ?>
                                                        <span class="green-text">
                                                            <i class="material-icons tiny">check_circle</i>
                                                            Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="orange-text">
                                                            <i class="material-icons tiny">pending</i>
                                                            Pending Verification
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>

                                            <div class="payment-actions">
                                                <?php if ($subscription['payment_proof']): ?>
                                                    <a href="#!" class="btn-small blue waves-effect waves-light view-proof" 
                                                       data-proof="../<?php echo htmlspecialchars($subscription['payment_proof']); ?>">
                                                        <i class="material-icons left">receipt</i>
                                                        View Payment Proof
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php
                                                // Debug information
                                                echo "<!-- Debug Info:\n";
                                                echo "Status: " . $subscription['status'] . "\n";
                                                echo "Created At: " . $subscription['created_at'] . "\n";
                                                echo "Hours Old: " . $hours_old . "\n";
                                                echo "Payment Status: " . $subscription['payment_status'] . "\n";
                                                echo "-->";
                                                
                                                // Check both subscription status and payment status
                                                if (($subscription['status'] === 'pending' || $subscription['payment_status'] === 'pending') && $hours_old <= 24): ?>
                                                    <button class="btn-small red waves-effect waves-light cancel-subscription"
                                                            data-id="<?php echo $subscription['id']; ?>">
                                                        <i class="material-icons left">cancel</i>
                                                        Cancel Subscription
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if (isset($subscription['cancelled_at'])): ?>
                                            <div class="cancellation-info red-text text-darken-2">
                                                <i class="material-icons tiny">info</i>
                                                Cancelled on <?php echo date('M d, Y h:i A', strtotime($subscription['cancelled_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="center-align grey-text">
                                <p>No subscription history found.</p>
                                <a href="plans.php" class="btn blue waves-effect waves-light">
                                    <i class="material-icons left">fitness_center</i>
                                    View Plans
                                </a>
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
            <img src="" alt="Payment Proof" style="width: 100%; max-width: 500px;">
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-green btn-flat">Close</a>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h4>Cancel Subscription</h4>
            <p>Are you sure you want to cancel this subscription? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-green btn-flat">No, Keep It</a>
            <a href="#!" class="modal-close waves-effect waves-red btn red" id="confirmCancel">Yes, Cancel It</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    
    <style>
        main {
            padding-left: 310px;
            padding-right: 30px;
            padding-top: 20px;
        }

        .subscription-list {
            margin-top: 20px;
        }

        .subscription-item {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 20px;
            background-color: #fff;
        }

        .subscription-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f5f5f5;
        }

        .plan-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .plan-info h5 {
            margin: 0;
            font-size: 1.2rem;
        }

        .subscription-dates {
            margin-top: 10px;
            display: flex;
            gap: 20px;
        }

        .subscription-dates p {
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .days-remaining {
            color: #1565C0;
            font-weight: 500;
        }

        .subscription-details {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .payment-info p {
            margin: 5px 0;
        }

        .payment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .cancellation-info {
            padding: 10px 15px;
            background-color: #ffebee;
            border-top: 1px solid #ffcdd2;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        @media only screen and (max-width: 992px) {
            main {
                padding-left: 0;
                padding-right: 0;
            }

            .subscription-dates {
                flex-direction: column;
                gap: 5px;
            }

            .subscription-details {
                flex-direction: column;
                gap: 15px;
            }

            .payment-info {
                text-align: center;
            }

            .payment-actions {
                justify-content: center;
            }
        }

        @media only screen and (max-width: 600px) {
            .payment-actions {
                flex-direction: column;
                width: 100%;
            }

            .payment-actions .btn-small {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            var proofModal = M.Modal.init(document.querySelector('#proofModal'));
            var cancelModal = M.Modal.init(document.querySelector('#cancelModal'));

            // Handle payment proof view
            document.querySelectorAll('.view-proof').forEach(function(button) {
                button.addEventListener('click', function() {
                    var proofUrl = this.getAttribute('data-proof');
                    document.querySelector('#proofModal img').src = proofUrl;
                    proofModal.open();
                });
            });

            // Handle subscription cancellation
            let subscriptionToCancel = null;
            
            document.querySelectorAll('.cancel-subscription').forEach(function(button) {
                button.addEventListener('click', function() {
                    subscriptionToCancel = this.getAttribute('data-id');
                    cancelModal.open();
                });
            });

            document.getElementById('confirmCancel').addEventListener('click', function() {
                if (!subscriptionToCancel) return;

                const formData = new FormData();
                formData.append('subscription_id', subscriptionToCancel);

                // Show loading state
                this.disabled = true;
                this.innerHTML = '<i class="material-icons left">hourglass_empty</i>Cancelling...';

                fetch('ajax/cancel_subscription.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        M.toast({
                            html: data.message,
                            classes: 'green',
                            displayLength: 4000
                        });
                        // Reload page after short delay
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        M.toast({
                            html: data.message,
                            classes: 'red'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({
                        html: 'Error cancelling subscription',
                        classes: 'red'
                    });
                })
                .finally(() => {
                    // Reset button state
                    this.disabled = false;
                    this.innerHTML = 'Yes, Cancel It';
                    subscriptionToCancel = null;
                });
            });

            // Check for success message in session storage
            const successMessage = sessionStorage.getItem('paymentSuccess');
            if (successMessage) {
                const messageDiv = document.getElementById('successMessage');
                messageDiv.querySelector('span').textContent = successMessage;
                messageDiv.style.display = 'block';
                
                // Remove the message from session storage
                sessionStorage.removeItem('paymentSuccess');
                
                // Hide the message after 5 seconds
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>