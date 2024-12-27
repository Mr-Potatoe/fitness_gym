<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

// Helper functions for status classes
function getStatusClass($status) {
    $statusMap = [
        'paid' => 'status-success',
        'pending' => 'status-warning',
        'rejected' => 'status-danger'
    ];
    return $statusMap[strtolower($status)] ?? 'status-warning';
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

$current_page = 'payments';
$page_title = 'Payment Management';

// Get filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Base query
$query = "SELECT p.*, 
          u.username, u.full_name,
          s.start_date, s.end_date,
          pl.name as plan_name, pl.duration_months, pl.price as amount,
          v.username as verifier_name
          FROM payments p
          JOIN subscriptions s ON p.subscription_id = s.id
          JOIN users u ON p.user_id = u.id
          JOIN plans pl ON s.plan_id = pl.id
          LEFT JOIN users v ON p.verified_by = v.id
          WHERE 1=1";

// Add filters if selected
if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
}
if (!empty($payment_method_filter)) {
    $query .= " AND p.payment_method = ?";
}

// Add ordering
$query .= " ORDER BY 
              CASE p.status 
                  WHEN 'pending' THEN 1
                  WHEN 'paid' THEN 2
                  WHEN 'rejected' THEN 3
                  ELSE 4
              END,
              p.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($status_filter) && !empty($payment_method_filter)) {
    $stmt->bind_param('ss', $status_filter, $payment_method_filter);
} elseif (!empty($status_filter)) {
    $stmt->bind_param('s', $status_filter);
} elseif (!empty($payment_method_filter)) {
    $stmt->bind_param('s', $payment_method_filter);
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
                    <span>Payment Management</span>
                    <div class="flex-right">
                        <div class="input-field inline status-filter">
                            <select id="statusFilter" onchange="applyFilters()">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <label>Filter by Status</label>
                        </div>
                        <div class="input-field inline payment-method-filter">
                            <select id="paymentMethodFilter" onchange="applyFilters()">
                                <option value="">All Methods</option>
                                <option value="cash" <?php echo $payment_method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank" <?php echo $payment_method_filter === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="gcash" <?php echo $payment_method_filter === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                                <option value="maya" <?php echo $payment_method_filter === 'maya' ? 'selected' : ''; ?>>Maya</option>
                            </select>
                            <label>Filter by Method</label>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Verified By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0):
                                while($payment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['plan_name']); ?></td>
                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($payment['status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $payment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($payment['created_at']); ?></td>
                                    <td>
                                        <?php if ($payment['verified_by']): ?>
                                            <?php echo htmlspecialchars($payment['verifier_name']); ?>
                                            <br><small><?php echo formatDate($payment['verified_at']); ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-small blue waves-effect waves-light tooltipped" 
                                                    onclick="viewPayment(<?php echo $payment['id']; ?>)"
                                                    data-position="top" 
                                                    data-tooltip="View Details">
                                                <i class="material-icons">visibility</i>
                                            </button>
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <button class="btn-small green waves-effect waves-light tooltipped" 
                                                        onclick="verifyPayment(<?php echo $payment['id']; ?>, 'approve')"
                                                        data-position="top" 
                                                        data-tooltip="Approve Payment">
                                                    <i class="material-icons">check_circle</i>
                                                </button>
                                                <button class="btn-small red waves-effect waves-light tooltipped" 
                                                        onclick="verifyPayment(<?php echo $payment['id']; ?>, 'reject')"
                                                        data-position="top" 
                                                        data-tooltip="Reject Payment">
                                                    <i class="material-icons">cancel</i>
                                                </button>
                                            <?php elseif ($payment['status'] === 'paid'): ?>
                                                <span class="btn-small green disabled tooltipped"
                                                      data-position="top" 
                                                      data-tooltip="Payment Verified">
                                                    <i class="material-icons">verified</i>
                                                </span>
                                            <?php elseif ($payment['status'] === 'rejected'): ?>
                                                <span class="btn-small red disabled tooltipped"
                                                      data-position="top" 
                                                      data-tooltip="Payment Rejected">
                                                    <i class="material-icons">block</i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="8" class="center-align">No payments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div id="viewModal" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>Payment Details</h4>
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
                                <strong>Amount:</strong>
                                <p id="amount"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Payment Method:</strong>
                                <p id="paymentMethod"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Status:</strong>
                                <p id="status"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Created At:</strong>
                                <p id="createdAt"></p>
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
        <div id="modalActions">
            <button class="waves-effect waves-light btn red" onclick="verifyPayment(currentPaymentId, 'reject')">
                Reject Payment
            </button>
            <button class="waves-effect waves-light btn green" onclick="verifyPayment(currentPaymentId, 'approve')">
                Approve Payment
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Materialize components
    M.AutoInit();
    
    // Initialize tooltips
    var tooltips = document.querySelectorAll('.tooltipped');
    M.Tooltip.init(tooltips);
    
    // Initialize materialbox for payment proof images
    var elems = document.querySelectorAll('.materialboxed');
    M.Materialbox.init(elems);
});

let currentPaymentId = null;

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const method = document.getElementById('paymentMethodFilter').value;
    
    let url = new URL(window.location.href);
    url.searchParams.set('status', status);
    url.searchParams.set('payment_method', method);
    
    window.location.href = url.toString();
}

async function viewPayment(id) {
    currentPaymentId = id;
    try {
        const response = await fetch(`ajax/get_payment.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const payment = data.payment;
            
            // Update modal content
            document.getElementById('memberName').textContent = payment.full_name;
            document.getElementById('planName').textContent = payment.plan_name;
            document.getElementById('amount').textContent = `₱${parseFloat(payment.amount).toFixed(2)}`;
            document.getElementById('paymentMethod').textContent = payment.payment_method.charAt(0).toUpperCase() + payment.payment_method.slice(1);
            document.getElementById('status').textContent = payment.status.replace('_', ' ').charAt(0).toUpperCase() + payment.status.slice(1);
            document.getElementById('createdAt').textContent = formatDate(payment.created_at);
            
            // Handle payment proof
            const proofSection = document.querySelector('.payment-proof-section');
            if (payment.payment_proof) {
                document.getElementById('paymentProofImage').src = `../uploads/payments/${payment.payment_proof}`;
                proofSection.style.display = 'block';
            } else {
                proofSection.style.display = 'none';
            }
            
            // Handle verification info
            const verificationSection = document.querySelector('.verification-section');
            if (payment.verified_by) {
                document.getElementById('verifiedBy').textContent = payment.verifier_name;
                document.getElementById('verifiedAt').textContent = formatDate(payment.verified_at);
                verificationSection.style.display = 'block';
            } else {
                verificationSection.style.display = 'none';
            }
            
            // Show/hide action buttons based on payment status
            const modalActions = document.getElementById('modalActions');
            modalActions.style.display = payment.status === 'pending' ? 'block' : 'none';
            
            // Open modal
            const modal = M.Modal.getInstance(document.getElementById('viewModal'));
            modal.open();
        } else {
            M.toast({html: data.message || 'Failed to load payment details'});
        }
    } catch (error) {
        console.error('Error:', error);
        M.toast({html: 'An error occurred while loading payment details'});
    }
}

async function verifyPayment(id, action) {
    const notes = action === 'reject' ? prompt('Please provide a reason for rejection:') : '';
    if (action === 'reject' && !notes) {
        M.toast({html: 'Please provide a reason for rejection'});
        return;
    }

    const confirmMessage = action === 'approve' 
        ? 'Are you sure you want to approve this payment?' 
        : 'Are you sure you want to reject this payment?';
        
    if (!confirm(confirmMessage)) return;
    
    try {
        const response = await fetch('ajax/verify_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                payment_id: id,
                action: action,
                notes: notes
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            M.toast({html: data.message, classes: 'green'});
            // Close modal if open
            const modal = M.Modal.getInstance(document.getElementById('viewModal'));
            if (modal) modal.close();
            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Failed to process payment verification');
        }
    } catch (error) {
        console.error('Error:', error);
        M.toast({html: error.message || 'An error occurred while processing payment verification', classes: 'red'});
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
}
</script>

<style>
.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: flex-start;
    align-items: center;
}
.action-buttons .btn-small {
    padding: 0 8px;
    height: 24px;
    line-height: 24px;
}
.action-buttons .btn-small i {
    font-size: 16px;
    line-height: 24px;
}
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
.status-success { background-color: #4CAF50; color: white; }
.status-warning { background-color: #FFC107; color: black; }
.status-danger { background-color: #F44336; color: white; }
</style>