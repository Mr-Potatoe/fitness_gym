<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

// Helper functions for status classes
function getStatusClass($status) {
    $statusMap = [
        'active' => 'status-active',
        'pending' => 'status-pending',
        'expired' => 'status-expired',
        'cancelled' => 'status-cancelled'
    ];
    return $statusMap[strtolower($status)] ?? 'status-pending';
}

function getPaymentStatusClass($status) {
    $statusMap = [
        'paid' => 'payment-paid',
        'pending_payment' => 'payment-pending',
        'pending_verification' => 'payment-pending',
        'failed' => 'payment-failed'
    ];
    return $statusMap[strtolower($status)] ?? 'payment-pending';
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

$current_page = 'subscriptions';
$page_title = 'Manage Subscriptions';

// Get filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';

// Base query
$query = "SELECT s.*, 
          u.username, u.full_name, 
          p.name as plan_name, p.duration_months, p.price as amount,
          py.id as payment_id, py.status as payment_status, py.payment_method,
          py.payment_proof, py.verified_by, py.verified_at,
          v.username as verifier_name
          FROM subscriptions s 
          JOIN users u ON s.user_id = u.id 
          JOIN plans p ON s.plan_id = p.id 
          LEFT JOIN payments py ON s.id = py.subscription_id
          LEFT JOIN users v ON py.verified_by = v.id
          WHERE 1=1";

// Add filters if selected
if (!empty($status_filter)) {
    $query .= " AND s.status = ?";
}
if (!empty($payment_filter)) {
    $query .= " AND py.status = ?";
}

// Add ordering
$query .= " ORDER BY 
              CASE s.status 
                  WHEN 'pending' THEN 1
                  WHEN 'active' THEN 2
                  WHEN 'expired' THEN 3
                  ELSE 4
              END,
              s.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($status_filter) && !empty($payment_filter)) {
    $stmt->bind_param('ss', $status_filter, $payment_filter);
} elseif (!empty($status_filter)) {
    $stmt->bind_param('s', $status_filter);
} elseif (!empty($payment_filter)) {
    $stmt->bind_param('s', $payment_filter);
}
$stmt->execute();
$result = $stmt->get_result();

include 'includes/header.php';
?>

<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <div class="card-title flex-row">
                    <span>Subscription Management</span>
                    <div class="flex-right">
                        <div class="input-field inline status-filter">
                            <select id="statusFilter" onchange="applyFilters()">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <label>Filter by Status</label>
                        </div>
                        <div class="input-field inline payment-filter">
                            <select id="paymentFilter" onchange="applyFilters()">
                                <option value="">All Payments</option>
                                <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending_payment" <?php echo $payment_filter === 'pending_payment' ? 'selected' : ''; ?>>Pending Payment</option>
                                <option value="pending_verification" <?php echo $payment_filter === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                                <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                            <label>Filter by Payment</label>
                        </div>
                        <a href="plans.php" class="btn blue waves-effect waves-light">
                            <i class="material-icons left">view_list</i>
                            View Plans
                        </a>
                    </div>
                </div>

                <div class="table-container">
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <th>Subscription Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0):
                                while($subscription = $result->fetch_assoc()): 
                                    // Format duration text
                                    $duration_text = $subscription['duration_months'] . ' ' . 
                                        ($subscription['duration_months'] > 1 ? 'months' : 'month');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscription['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                    <td><?php echo $duration_text; ?></td>
                                    <td>₱<?php echo number_format($subscription['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($subscription['payment_method'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getPaymentStatusClass($subscription['payment_status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $subscription['payment_status'] ?? 'Pending')); ?>
                                        </span>
                                        <?php if ($subscription['verified_by']): ?>
                                            <br><small>by <?php echo htmlspecialchars($subscription['verifier_name']); ?></small>
                                            <br><small><?php echo formatDate($subscription['verified_at']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($subscription['status']); ?>">
                                            <?php echo ucfirst($subscription['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($subscription['start_date']); ?></td>
                                    <td><?php echo formatDate($subscription['end_date']); ?></td>
                                    <td>
                                        <button class="btn-small blue waves-effect waves-light" 
                                                onclick="viewSubscription(<?php echo $subscription['id']; ?>)">
                                            <i class="material-icons">visibility</i>
                                        </button>
                                        <?php if ($subscription['payment_status'] === 'pending_verification'): ?>
                                        <button class="btn-small green waves-effect waves-light" 
                                                onclick="verifyPayment(<?php echo $subscription['id']; ?>)">
                                            <i class="material-icons">check</i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="10" class="center-align">No subscriptions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Subscription Modal -->
<div id="viewModal" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>Subscription Details</h4>
        <div class="row">
            <div class="col s12">
                <ul class="collection">
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Member:</strong>
                                <p id="memberName"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Plan:</strong>
                                <p id="planName"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Duration:</strong>
                                <p id="duration"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Amount:</strong>
                                <p id="amount"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Start Date:</strong>
                                <p id="startDate"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>End Date:</strong>
                                <p id="endDate"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Payment Method:</strong>
                                <p id="paymentMethod"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Payment Status:</strong>
                                <p id="paymentStatus"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item payment-proof-section" style="display: none;">
                        <div class="row mb-0">
                            <div class="col s12">
                                <strong>Payment Proof:</strong>
                                <div class="payment-proof-container">
                                    <img id="paymentProofImage" class="materialboxed responsive-img" src="" alt="Payment Proof">
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item verification-section" style="display: none;">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Verified By:</strong>
                                <p id="verifiedBy"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Verified At:</strong>
                                <p id="verifiedAt"></p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="modal-close waves-effect waves-light btn-flat">Close</button>
        <button id="verifyButton" class="waves-effect waves-light btn green" style="display: none;" onclick="verifyPayment()">
            Verify Payment
        </button>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Materialize components
    M.AutoInit();
    
    // Initialize materialbox for payment proof images
    var elems = document.querySelectorAll('.materialboxed');
    M.Materialbox.init(elems);
});

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const payment = document.getElementById('paymentFilter').value;
    
    let url = new URL(window.location.href);
    url.searchParams.set('status', status);
    url.searchParams.set('payment', payment);
    
    window.location.href = url.toString();
}

async function viewSubscription(id) {
    try {
        const response = await fetch(`ajax/get_subscription.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const sub = data.subscription;
            
            // Update modal content
            document.getElementById('memberName').textContent = sub.full_name;
            document.getElementById('planName').textContent = sub.plan_name;
            document.getElementById('duration').textContent = `${sub.duration_months} ${sub.duration_months > 1 ? 'months' : 'month'}`;
            document.getElementById('amount').textContent = `₱${parseFloat(sub.amount).toFixed(2)}`;
            document.getElementById('startDate').textContent = formatDate(sub.start_date);
            document.getElementById('endDate').textContent = formatDate(sub.end_date);
            document.getElementById('paymentMethod').textContent = sub.payment_method ? sub.payment_method.charAt(0).toUpperCase() + sub.payment_method.slice(1) : 'N/A';
            document.getElementById('paymentStatus').textContent = sub.payment_status ? sub.payment_status.replace('_', ' ').charAt(0).toUpperCase() + sub.payment_status.slice(1) : 'Pending';
            
            // Handle payment proof
            const proofSection = document.querySelector('.payment-proof-section');
            if (sub.payment_proof) {
                document.getElementById('paymentProofImage').src = `../uploads/payments/${sub.payment_proof}`;
                proofSection.style.display = 'block';
            } else {
                proofSection.style.display = 'none';
            }
            
            // Handle verification info
            const verificationSection = document.querySelector('.verification-section');
            if (sub.verified_by) {
                document.getElementById('verifiedBy').textContent = sub.verifier_name;
                document.getElementById('verifiedAt').textContent = formatDate(sub.verified_at);
                verificationSection.style.display = 'block';
            } else {
                verificationSection.style.display = 'none';
            }
            
            // Show verify button if payment is pending verification
            const verifyButton = document.getElementById('verifyButton');
            verifyButton.style.display = sub.payment_status === 'pending_verification' ? 'inline-block' : 'none';
            verifyButton.onclick = () => verifyPayment(sub.id);
            
            // Open modal
            const modal = M.Modal.getInstance(document.getElementById('viewModal'));
            modal.open();
        } else {
            M.toast({html: data.message || 'Failed to load subscription details'});
        }
    } catch (error) {
        console.error('Error:', error);
        M.toast({html: 'An error occurred while loading subscription details'});
    }
}

async function verifyPayment(id) {
    if (!confirm('Are you sure you want to verify this payment?')) return;
    
    try {
        const response = await fetch('ajax/verify_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ subscription_id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            M.toast({html: 'Payment verified successfully'});
            setTimeout(() => window.location.reload(), 1000);
        } else {
            M.toast({html: data.message || 'Failed to verify payment'});
        }
    } catch (error) {
        console.error('Error:', error);
        M.toast({html: 'An error occurred while verifying payment'});
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
}
</script>

<style>
.payment-proof-container {
    max-width: 100%;
    margin: 10px 0;
}
.payment-proof-container img {
    max-width: 300px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
}
.status-badge {
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 0.9em;
}
.status-active { background-color: #4CAF50; color: white; }
.status-pending { background-color: #FFC107; color: black; }
.status-expired { background-color: #9E9E9E; color: white; }
.status-cancelled { background-color: #F44336; color: white; }
.payment-paid { background-color: #4CAF50; color: white; }
.payment-pending { background-color: #FFC107; color: black; }
.payment-failed { background-color: #F44336; color: white; }
</style>