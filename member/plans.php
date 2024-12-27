<?php
require_once '../config/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'plans';
$page_title = 'Membership Plans';

// Check for active subscription
$user_id = $_SESSION['user_id'];
$active_sub_query = "SELECT s.*, p.name as plan_name, 
                            DATEDIFF(s.end_date, CURRENT_DATE()) as days_remaining 
                     FROM subscriptions s 
                     JOIN plans p ON s.plan_id = p.id 
                     WHERE s.user_id = $user_id 
                     AND s.status = 'active' 
                     AND s.end_date >= CURRENT_DATE()";
$active_sub_result = $conn->query($active_sub_query);
$has_active_subscription = $active_sub_result && $active_sub_result->num_rows > 0;
$active_subscription = $has_active_subscription ? $active_sub_result->fetch_assoc() : null;

// Get available plans
$plans_query = "SELECT * FROM plans WHERE deleted_at IS NULL ORDER BY price ASC";
$plans = $conn->query($plans_query);

// Get payment accounts from admin
$payment_accounts_query = "SELECT * FROM payment_accounts WHERE is_active = 1 AND deleted_at IS NULL ORDER BY account_type";
$payment_accounts = $conn->query($payment_accounts_query);
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
            <?php if ($has_active_subscription): ?>
                <div class="col s12">
                    <div class="card orange lighten-4">
                        <div class="card-content">
                            <span class="card-title">Active Subscription</span>
                            <p>You are currently subscribed to: <strong><?php echo htmlspecialchars($active_subscription['plan_name']); ?></strong></p>
                            <p>Valid until: <strong><?php echo date('F d, Y', strtotime($active_subscription['end_date'])); ?></strong></p>
                            <?php if ($active_subscription['days_remaining'] > 0): ?>
                                <p>Days remaining: <strong><?php echo $active_subscription['days_remaining']; ?> days</strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php while($plan = $plans->fetch_assoc()): ?>
                <div class="col s12 m6 l4">
                    <div class="card">
                        <div class="card-content">
                            <span class="card-title"><?php echo htmlspecialchars($plan['name']); ?></span>
                            <div class="price">
                                <h4><?php echo formatPrice($plan['price']); ?></h4>
                                <span class="duration"><?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'month' : 'months'; ?></span>
                            </div>
                            <div class="features">
                                <?php 
                                $features = json_decode($plan['features'], true);
                                if ($features): 
                                    foreach($features as $feature):
                                ?>
                                    <p><i class="material-icons tiny">check</i> <?php echo htmlspecialchars($feature); ?></p>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>
                        <div class="card-action center-align">
                            <?php if ($has_active_subscription): ?>
                                <button class="btn disabled" disabled>Already Subscribed</button>
                            <?php else: ?>
                                <button class="btn blue waves-effect waves-light subscribe-btn" 
                                        data-id="<?php echo $plan['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                        data-price="<?php echo formatPrice($plan['price']); ?>"
                                        data-duration="<?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'month' : 'months'; ?>">
                                    Subscribe Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </main>

    <!-- Subscribe Modal -->
    <div id="subscribeModal" class="modal">
        <form id="subscribeForm" enctype="multipart/form-data">
            <div class="modal-content">
                <h4>Subscribe to <span id="planName"></span></h4>
                <div class="row">
                    <div class="col s12">
                        <p>
                            <strong>Duration:</strong> <span id="planDuration"></span><br>
                            <strong>Price:</strong> <span id="planPrice"></span>
                        </p>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <select name="payment_method" id="paymentMethod" required>
                                <option value="" disabled selected>Choose your payment method</option>
                                <option value="cash">Cash Payment (Pay at Gym)</option>
                                <?php while($account = $payment_accounts->fetch_assoc()): ?>
                                    <option value="<?php echo strtolower($account['account_type']); ?>"
                                            data-name="<?php echo htmlspecialchars($account['account_name']); ?>"
                                            data-number="<?php echo htmlspecialchars($account['account_number']); ?>">
                                        <?php echo htmlspecialchars($account['account_type']); ?> (Online)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <label>Payment Method</label>
                        </div>
                    </div>
                    <div class="col s12" id="accountDetails" style="display: none;">
                        <div class="card-panel">
                            <h6>Account Details:</h6>
                            <pre id="accountDetailsText" class="account-details"></pre>
                            <div class="input-field" id="referenceNumberField" style="display: none;">
                                <input type="text" id="reference_number" name="reference_number" class="validate">
                                <label for="reference_number">Reference Number</label>
                            </div>
                            <div class="file-field input-field">
                                <div class="btn">
                                    <span>Payment Proof</span>
                                    <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                <div class="file-path-wrapper">
                                    <input class="file-path validate" type="text" placeholder="Upload payment proof">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="plan_id" id="planId">
                <button type="button" class="modal-close waves-effect waves-light btn-flat">Cancel</button>
                <button type="submit" class="waves-effect waves-light btn blue">Submit</button>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    
    <style>
        main {
            padding: 20px;
        }
        @media (min-width: 993px) {
            main {
                padding-left: 310px;
                padding-right: 30px;
            }
        }
        .price {
            text-align: center;
            margin: 20px 0;
        }
        .price h4 {
            margin: 0;
            color: #2196F3;
        }
        .duration {
            color: #9e9e9e;
            font-size: 0.9rem;
        }
        .features {
            margin-top: 20px;
        }
        .features p {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
        }
        .features i {
            color: #4CAF50;
        }
        #accountDetails {
            margin-top: 20px;
        }
        .account-details {
            white-space: pre-wrap;
            font-family: monospace;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        M.AutoInit();

        // Handle payment method change
        document.getElementById('paymentMethod').addEventListener('change', function() {
            const details = this.options[this.selectedIndex].dataset;
            const detailsDiv = document.getElementById('accountDetails');
            const detailsText = document.getElementById('accountDetailsText');
            const paymentProofInput = document.querySelector('input[name="payment_proof"]');
            const referenceNumberField = document.getElementById('referenceNumberField');
            
            if (this.value === 'cash') {
                detailsDiv.style.display = 'none';
                paymentProofInput.removeAttribute('required');
                referenceNumberField.style.display = 'none';
            } else {
                detailsText.textContent = `Account Name: ${details.name}\nAccount Number: ${details.number}`;
                detailsDiv.style.display = 'block';
                paymentProofInput.setAttribute('required', 'required');
                referenceNumberField.style.display = 'block';
            }
        });

        // Handle form submission
        document.getElementById('subscribeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('ajax/subscribe.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    M.toast({html: result.message, classes: 'green'});
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    M.toast({html: result.message || 'An error occurred', classes: 'red'});
                }
            } catch (error) {
                console.error('Error:', error);
                M.toast({html: 'Error processing subscription. Please try again.', classes: 'red'});
            }
        });

        // Handle subscribe button clicks
        document.querySelectorAll('.subscribe-btn').forEach(button => {
            button.addEventListener('click', function() {
                const modal = document.getElementById('subscribeModal');
                const data = this.dataset;
                
                // Update modal content
                document.getElementById('planName').textContent = data.name;
                document.getElementById('planDuration').textContent = data.duration;
                document.getElementById('planPrice').textContent = data.price;
                document.getElementById('planId').value = data.id;
                
                // Reset form
                document.getElementById('subscribeForm').reset();
                document.getElementById('accountDetails').style.display = 'none';
                document.getElementById('referenceNumberField').style.display = 'none';
                
                // Open modal
                M.Modal.getInstance(modal).open();
            });
        });
    });
    </script>
</body>
</html>