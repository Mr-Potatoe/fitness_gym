<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'subscriptions';
$page_title = 'Manage Subscriptions';

// Get all subscriptions with related information
$query = "SELECT s.*, 
          u.username, u.full_name, 
          p.name as plan_name, p.duration_months, p.price as amount,
          COALESCE(py.status, 'pending') as payment_status,
          DATEDIFF(s.end_date, CURRENT_DATE()) as days_remaining
          FROM subscriptions s 
          JOIN users u ON s.user_id = u.id 
          JOIN plans p ON s.plan_id = p.id 
          LEFT JOIN (
              SELECT subscription_id, status
              FROM payments
              WHERE id IN (
                  SELECT MAX(id)
                  FROM payments
                  GROUP BY subscription_id
              )
          ) py ON s.id = py.subscription_id
          ORDER BY 
              CASE s.status 
                  WHEN 'pending' THEN 1
                  WHEN 'active' THEN 2
                  WHEN 'expired' THEN 3
                  ELSE 4
              END,
              s.created_at DESC";

$result = $conn->query($query);

include 'includes/header.php';
?>

<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <div class="card-title flex-row">
                    <span>Subscription Management</span>
                    <div class="flex-right">
                        <a href="plans.php" class="btn blue waves-effect waves-light">
                            <i class="material-icons left">view_list</i>
                            Manage Plans
                        </a>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="row">
                    <div class="col s12 m4">
                        <div class="input-field">
                            <i class="material-icons prefix">search</i>
                            <input type="text" id="search-input" class="search-input">
                            <label for="search-input">Search by member name or plan...</label>
                        </div>
                    </div>
                    <div class="col s12 m4">
                        <div class="input-field">
                            <select id="status-filter" class="status-filter">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                            </select>
                            <label>Filter by Status</label>
                        </div>
                    </div>
                    <div class="col s12 m4">
                        <div class="input-field">
                            <select id="payment-filter" class="payment-filter">
                                <option value="">All Payments</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <label>Filter by Payment</label>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Duration</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Remaining</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th class="center-align">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0):
                                while($subscription = $result->fetch_assoc()): 
                                    // Format duration text
                                    $duration_text = $subscription['duration_months'] . ' ' . 
                                        ($subscription['duration_months'] == 1 ? 'month' : 'months');
                                    
                                    // Calculate remaining time
                                    $days_remaining = max(0, $subscription['days_remaining']);
                                    $remaining_text = $days_remaining > 0 ? 
                                        floor($days_remaining / 30) . 'm ' . ($days_remaining % 30) . 'd' : 
                                        'Expired';
                            ?>
                                <tr class="subscription-row" 
                                    data-status="<?php echo $subscription['status']; ?>"
                                    data-payment="<?php echo $subscription['payment_status']; ?>">
                                    <td>
                                        <div class="member-info">
                                            <span class="member-name"><?php echo htmlspecialchars($subscription['full_name']); ?></span>
                                            <small class="grey-text"><?php echo htmlspecialchars($subscription['username']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                    <td><?php echo $duration_text; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($subscription['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></td>
                                    <td>
                                        <span class="chip <?php echo $days_remaining > 0 ? 'green' : 'red'; ?> white-text">
                                            <?php echo $remaining_text; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatPrice($subscription['amount']); ?></td>
                                    <td>
                                        <span class="chip <?php 
                                            echo $subscription['status'] === 'active' ? 'green' : 
                                                ($subscription['status'] === 'pending' ? 'orange' : 'grey'); 
                                            ?> white-text">
                                            <?php echo ucfirst($subscription['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="chip <?php 
                                            echo $subscription['payment_status'] === 'paid' ? 'green' : 
                                                ($subscription['payment_status'] === 'rejected' ? 'red' : 'orange'); 
                                            ?> white-text">
                                            <?php echo ucfirst($subscription['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="center-align">
                                        <div class="action-buttons">
                                            <a href="#viewModal" class="btn-floating btn-small blue modal-trigger tooltipped view-subscription"
                                               data-position="top" data-tooltip="View Details"
                                               data-id="<?php echo $subscription['id']; ?>"
                                               data-member="<?php echo htmlspecialchars($subscription['full_name']); ?>"
                                               data-plan="<?php echo htmlspecialchars($subscription['plan_name']); ?>"
                                               data-duration="<?php echo $duration_text; ?>"
                                               data-start="<?php echo date('M d, Y', strtotime($subscription['start_date'])); ?>"
                                               data-end="<?php echo date('M d, Y', strtotime($subscription['end_date'])); ?>"
                                               data-amount="<?php echo formatPrice($subscription['amount']); ?>"
                                               data-status="<?php echo $subscription['status']; ?>"
                                               data-payment="<?php echo $subscription['payment_status']; ?>">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                            
                                            <?php if ($subscription['status'] === 'pending'): ?>
                                                <button class="btn-floating btn-small green tooltipped verify-subscription"
                                                        data-position="top" data-tooltip="Verify Subscription"
                                                        data-id="<?php echo $subscription['id']; ?>">
                                                    <i class="material-icons">check_circle</i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($subscription['status'] !== 'active'): ?>
                                                <button class="btn-floating btn-small red tooltipped delete-subscription"
                                                        data-position="top" data-tooltip="Delete Subscription"
                                                        data-id="<?php echo $subscription['id']; ?>"
                                                        data-member="<?php echo htmlspecialchars($subscription['full_name']); ?>">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else: ?>
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
<div id="viewModal" class="modal">
    <div class="modal-content">
        <h4>Subscription Details</h4>
        <div class="row">
            <div class="col s12">
                <ul class="collection">
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Member:</strong>
                                <p class="member-name"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Plan:</strong>
                                <p class="plan-name"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Duration:</strong>
                                <p class="duration"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Amount:</strong>
                                <p class="amount"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Start Date:</strong>
                                <p class="start-date"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>End Date:</strong>
                                <p class="end-date"></p>
                            </div>
                        </div>
                    </li>
                    <li class="collection-item">
                        <div class="row mb-0">
                            <div class="col s12 m6">
                                <strong>Status:</strong>
                                <p class="status"></p>
                            </div>
                            <div class="col s12 m6">
                                <strong>Payment Status:</strong>
                                <p class="payment-status"></p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="modal-close waves-effect waves-blue btn-flat">Close</button>
    </div>
</div>

<style>
.flex-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.flex-right {
    margin-left: auto;
}
.member-info {
    display: flex;
    flex-direction: column;
}
.member-info small {
    font-size: 0.8em;
}
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}
.btn-floating.btn-small {
    width: 30px;
    height: 30px;
    line-height: 30px;
}
.btn-floating.btn-small i {
    line-height: 30px;
}
.chip {
    height: 24px;
    line-height: 24px;
    padding: 0 12px;
    margin: 0;
}
.mb-0 {
    margin-bottom: 0 !important;
}
.collection .collection-item {
    padding: 15px;
}
.collection .collection-item p {
    margin: 5px 0 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Materialize components
    M.AutoInit();
    
    // View Subscription Details
    document.querySelectorAll('.view-subscription').forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.querySelector('#viewModal');
            modal.querySelector('.member-name').textContent = this.dataset.member;
            modal.querySelector('.plan-name').textContent = this.dataset.plan;
            modal.querySelector('.duration').textContent = this.dataset.duration;
            modal.querySelector('.amount').textContent = this.dataset.amount;
            modal.querySelector('.start-date').textContent = this.dataset.start;
            modal.querySelector('.end-date').textContent = this.dataset.end;
            modal.querySelector('.status').innerHTML = `
                <span class="chip ${this.dataset.status === 'active' ? 'green' : 
                    (this.dataset.status === 'pending' ? 'orange' : 'grey')} white-text">
                    ${this.dataset.status.charAt(0).toUpperCase() + this.dataset.status.slice(1)}
                </span>`;
            modal.querySelector('.payment-status').innerHTML = `
                <span class="chip ${this.dataset.payment === 'paid' ? 'green' : 
                    (this.dataset.payment === 'rejected' ? 'red' : 'orange')} white-text">
                    ${this.dataset.payment.charAt(0).toUpperCase() + this.dataset.payment.slice(1)}
                </span>`;
        });
    });

    // Verify Subscription
    document.querySelectorAll('.verify-subscription').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to verify this subscription?')) {
                const id = this.dataset.id;
                processSubscription(id, 'verify');
            }
        });
    });

    // Delete Subscription
    document.querySelectorAll('.delete-subscription').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const member = this.dataset.member;
            if (confirm(`Are you sure you want to delete the subscription for ${member}?\nThis action cannot be undone!`)) {
                processSubscription(id, 'delete');
            }
        });
    });

    // Search and Filter
    const searchInput = document.querySelector('.search-input');
    const statusFilter = document.querySelector('.status-filter');
    const paymentFilter = document.querySelector('.payment-filter');

    function filterSubscriptions() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        const paymentValue = paymentFilter.value.toLowerCase();

        document.querySelectorAll('.subscription-row').forEach(row => {
            const memberName = row.querySelector('.member-name').textContent.toLowerCase();
            const planName = row.cells[1].textContent.toLowerCase();
            const status = row.dataset.status.toLowerCase();
            const payment = row.dataset.payment.toLowerCase();

            const matchesSearch = memberName.includes(searchTerm) || planName.includes(searchTerm);
            const matchesStatus = !statusValue || status === statusValue;
            const matchesPayment = !paymentValue || payment === paymentValue;

            row.style.display = matchesSearch && matchesStatus && matchesPayment ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterSubscriptions);
    statusFilter.addEventListener('change', filterSubscriptions);
    paymentFilter.addEventListener('change', filterSubscriptions);

    function processSubscription(id, action) {
        fetch('ajax/subscription_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                subscription_id: id,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                M.toast({html: data.message, classes: 'green'});
                setTimeout(() => location.reload(), 1000);
            } else {
                M.toast({html: data.message || 'Operation failed', classes: 'red'});
            }
        })
        .catch(error => {
            console.error('Error:', error);
            M.toast({html: 'An error occurred', classes: 'red'});
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>