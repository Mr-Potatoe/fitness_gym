<?php
require_once '../config/config.php';
require_once '../config/security_config.php';
require_once '../includes/utils/SecurityManager.php';

// Initialize security manager
$securityManager = new SecurityManager($conn, ENCRYPTION_KEY);

// Check if user is logged in and is a member
if (!isLoggedIn() || !hasRole('member')) {
    redirect('/login.php');
}

$current_page = 'subscribe';
$page_title = 'Subscribe to Plan';

// Get plan ID from URL with validation
$plan_id = filter_input(INPUT_GET, 'plan', FILTER_VALIDATE_INT);
if ($plan_id === false || $plan_id === null) {
    setFlashMessage('error', 'Invalid plan selected.');
    redirect('plans.php');
}

// Get plan details using prepared statement
$plan_stmt = $conn->prepare("SELECT * FROM plans WHERE id = ? AND deleted_at IS NULL");
$plan_stmt->bind_param("i", $plan_id);
$plan_stmt->execute();
$plan_result = $plan_stmt->get_result();
$plan = $plan_result->fetch_assoc();
$plan_stmt->close();

if (!$plan) {
    setFlashMessage('error', 'Selected plan not found or is no longer available.');
    redirect('plans.php');
}

// Check for active subscription using prepared statement
$member_id = $_SESSION['user_id'];
$active_sub_stmt = $conn->prepare("SELECT * FROM subscriptions 
                                  WHERE user_id = ? 
                                  AND status = 'active' 
                                  AND end_date >= CURRENT_DATE()");
$active_sub_stmt->bind_param("i", $member_id);
$active_sub_stmt->execute();
$active_sub = $active_sub_stmt->get_result()->fetch_assoc();
$active_sub_stmt->close();

if ($active_sub) {
    setFlashMessage('error', 'You already have an active subscription.');
    redirect('plans.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid request");
        }

        // Validate plan selection
        $planId = filter_var($_POST['plan_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$planId) {
            throw new Exception("Please select a valid plan");
        }

        // Get plan details
        $stmt = $conn->prepare("
            SELECT * FROM plans 
            WHERE id = ? AND status = 'active'
        ");
        
        $stmt->bind_param("i", $planId);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        
        if (!$plan) {
            throw new Exception("Selected plan is not available");
        }

        // Process payment
        $paymentData = [
            'amount' => $plan['price'],
            'method' => sanitizeInput($_POST['payment_method']),
            'reference' => sanitizeInput($_POST['reference_number']),
            'account_number' => sanitizeInput($_POST['account_number']),
            'account_name' => sanitizeInput($_POST['account_name'])
        ];

        // Handle file upload
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("Please upload payment proof");
        }

        // Process payment with proof
        $result = $securityManager->getPaymentProcessor()->processPayment(
            $paymentData,
            $_FILES['payment_proof'],
            $_SESSION['user_id']
        );

        if ($result) {
            // Create subscription
            $stmt = $conn->prepare("
                INSERT INTO subscriptions (
                    user_id, plan_id, start_date, end_date,
                    payment_status, status, amount
                ) VALUES (
                    ?, ?, CURDATE(),
                    DATE_ADD(CURDATE(), INTERVAL ? MONTH),
                    'pending', 'pending', ?
                )
            ");
            
            $stmt->bind_param(
                "iiid",
                $_SESSION['user_id'],
                $planId,
                $plan['duration_months'],
                $plan['price']
            );
            
            if ($stmt->execute()) {
                $subscriptionId = $conn->insert_id;
                
                // Update payment with subscription ID
                $stmt = $conn->prepare("
                    UPDATE payments 
                    SET subscription_id = ? 
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ii", $subscriptionId, $result['payment_id']);
                $stmt->execute();

                setFlashMessage('success', "Subscription request submitted successfully. Please wait for admin verification.");
                redirect('subscriptions.php');
                exit;
            }
        }

        throw new Exception("Failed to process payment");

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .payment-method-section { margin: 20px 0; }
        .account-details { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }
        .payment-proof-preview { max-width: 300px; margin: 10px 0; }
        .error-text { color: #f44336; }
        .helper-text { font-size: 0.8rem; color: #757575; }
    </style>
</head>
<body>
    <?php include '../includes/member_nav.php'; ?>

    <main>
        <div class="row">
            <!-- Plan Summary Card -->
            <div class="col s12 m4">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Plan Summary</span>
                        <div class="plan-details">
                            <p><strong>Plan:</strong> <?php echo htmlspecialchars($plan['name']); ?></p>
                            <p><strong>Price:</strong> ₱<?php echo number_format($plan['price'], 2); ?></p>
                            <p><strong>Duration:</strong> <?php echo $plan['duration_months'] . ' ' . ($plan['duration_months'] == 1 ? 'month' : 'months'); ?></p>
                            <p><strong>Valid Until:</strong> <?php echo date('F j, Y', strtotime('+' . $plan['duration_months'] . ' months')); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="col s12 m8">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">
                            <i class="material-icons">payment</i>
                            Payment Details
                        </span>

                        <form id="payment-form" action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                            
                            <div class="payment-method-section">
                                <p>Select Payment Method:</p>
                                <p>
                                    <label>
                                        <input name="payment_method" type="radio" value="gcash" checked />
                                        <span>GCash</span>
                                    </label>
                                </p>
                                <p>
                                    <label>
                                        <input name="payment_method" type="radio" value="bank" />
                                        <span>Bank Transfer</span>
                                    </label>
                                </p>

                                <!-- GCash Account Details -->
                                <div id="gcash-details" class="account-details">
                                    <h6>GCash Account Details:</h6>
                                    <?php 
                                        $gcash_stmt = $conn->prepare("SELECT * FROM admin_payment_accounts 
                                                                        WHERE account_type = 'gcash' 
                                                                        AND is_active = 1");
                                        $gcash_stmt->execute();
                                        $gcash_accounts = $gcash_stmt->get_result();
                                        $gcash_stmt->close();
                                    ?>
                                    <?php if ($gcash_accounts->num_rows > 0): ?>
                                        <?php while ($account = $gcash_accounts->fetch_assoc()): ?>
                                            <p>
                                                <strong>Account Name:</strong> <?php echo htmlspecialchars($account['account_name']); ?><br>
                                                <strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?>
                                            </p>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="error-text">No GCash accounts available. Please contact admin.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Bank Account Details -->
                                <div id="bank-details" class="account-details" style="display: none;">
                                    <h6>Bank Account Details:</h6>
                                    <?php 
                                        $bank_stmt = $conn->prepare("SELECT * FROM admin_payment_accounts 
                                                                        WHERE account_type = 'bank' 
                                                                        AND is_active = 1");
                                        $bank_stmt->execute();
                                        $bank_accounts = $bank_stmt->get_result();
                                        $bank_stmt->close();
                                    ?>
                                    <?php if ($bank_accounts->num_rows > 0): ?>
                                        <?php while ($account = $bank_accounts->fetch_assoc()): ?>
                                            <p>
                                                <strong>Bank:</strong> <?php echo htmlspecialchars($account['bank_name']); ?><br>
                                                <strong>Account Name:</strong> <?php echo htmlspecialchars($account['account_name']); ?><br>
                                                <strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?>
                                            </p>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="error-text">No bank accounts available. Please contact admin.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Payment Proof Upload -->
                            <div class="file-field input-field">
                                <div class="btn blue">
                                    <span>Upload Proof</span>
                                    <input type="file" name="payment_proof" accept="image/*" required>
                                </div>
                                <div class="file-path-wrapper">
                                    <input class="file-path validate" type="text" placeholder="Upload payment screenshot">
                                </div>
                                <span class="helper-text">Accepted formats: JPG, PNG, GIF (Max size: 5MB)</span>
                            </div>

                            <div id="proof-preview" class="payment-proof-preview"></div>

                            <!-- Payment Notes -->
                            <div class="input-field">
                                <i class="material-icons prefix">note</i>
                                <textarea id="payment_notes" name="payment_notes" class="materialize-textarea"></textarea>
                                <label for="payment_notes">Additional Notes (Optional)</label>
                            </div>

                            <div class="center-align">
                                <button type="submit" class="btn-large waves-effect waves-light blue">
                                    Submit Payment
                                    <i class="material-icons right">send</i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
            M.updateTextFields();
            M.textareaAutoResize(document.querySelector('textarea'));

            // Payment method toggle
            const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
            const gcashDetails = document.getElementById('gcash-details');
            const bankDetails = document.getElementById('bank-details');

            paymentMethodRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'gcash') {
                        gcashDetails.style.display = 'block';
                        bankDetails.style.display = 'none';
                    } else {
                        gcashDetails.style.display = 'none';
                        bankDetails.style.display = 'block';
                    }
                });
            });

            // Payment proof preview
            const fileInput = document.querySelector('input[type="file"]');
            const previewDiv = document.getElementById('proof-preview');

            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        M.toast({html: 'Please upload only image files (JPG, PNG, GIF)', classes: 'red'});
                        this.value = '';
                        previewDiv.innerHTML = '';
                        return;
                    }

                    // Validate file size (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        M.toast({html: 'File size should not exceed 5MB', classes: 'red'});
                        this.value = '';
                        previewDiv.innerHTML = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewDiv.innerHTML = `<img src="${e.target.result}" style="max-width: 100%; height: auto;">`;
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewDiv.innerHTML = '';
                }
            });

            // Form submission
            document.getElementById('payment-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('ajax/process_subscription.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        M.toast({html: 'Payment submitted successfully!', classes: 'green'});
                        setTimeout(() => window.location.href = 'subscriptions.php', 2000);
                    } else {
                        M.toast({html: data.message || 'Error processing payment', classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error submitting payment', classes: 'red'});
                });
            });
        });
    </script>
</body>
</html>