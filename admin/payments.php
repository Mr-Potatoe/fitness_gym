<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'payments';
$page_title = 'Payment Management';

// Get all payments with member and subscription details
$query = "SELECT p.*, 
          u.full_name as member_name,
          u.email as member_email,
          s.start_date,
          s.end_date,
          s.status as subscription_status,
          pl.name as plan_name,
          pl.price as plan_price,
          pl.duration_months,
          p.payment_proof,
          p.verified,
          p.verified_at,
          p.verified_by,
          CONCAT(admin.full_name) as verified_by_name
          FROM payments p 
          JOIN subscriptions s ON p.subscription_id = s.id 
          JOIN users u ON s.user_id = u.id 
          JOIN plans pl ON s.plan_id = pl.id
          LEFT JOIN users admin ON p.verified_by = admin.id
          WHERE p.status IN ('pending', 'verified', 'rejected')
          ORDER BY p.created_at DESC";

$payments = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <span class="card-title">Payment Records</span>
                        
                        <!-- Search and Filter Section -->
                        <div class="row">
                            <div class="col s12 m6">
                                <div class="input-field">
                                    <i class="material-icons prefix">search</i>
                                    <input type="text" id="search-input" onkeyup="filterPayments()">
                                    <label for="search-input">Search by member name or email...</label>
                                </div>
                            </div>
                            <div class="col s12 m6">
                                <div class="input-field">
                                    <select id="status-filter" onchange="filterPayments()">
                                        <option value="">All Status</option>
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                    <label>Filter by Status</label>
                                </div>
                            </div>
                        </div>

                        <!-- Payments Table -->
                        <table class="striped responsive-table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                    <th>Payment Proof</th>
                                    <th>Verified By</th>
                                    <th class="center-align">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments && $payments->num_rows > 0): ?>
                                    <?php while($payment = $payments->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($payment['member_name']); ?><br>
                                                <small class="grey-text"><?php echo htmlspecialchars($payment['member_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['plan_name']); ?>
                                                <br>
                                                <small class="grey-text"><?php echo $payment['duration_months']; ?> months</small>
                                            </td>
                                            <td>₱<?php echo number_format($payment['plan_price'], 2); ?></td>
                                            <td><?php echo $payment['payment_date'] !== 'N/A' ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-text <?php echo $payment['status'] === 'paid' ? 'status-paid' : 'status-pending'; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                            <td>
                                                <?php if ($payment['payment_method'] !== 'cash'): ?>
                                                    <?php if ($payment['payment_proof']): ?>
                                                        <button class="btn-small blue" onclick="viewPaymentProof(<?php echo $payment['id']; ?>)">
                                                            <i class="material-icons">receipt</i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="red-text">No proof uploaded</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="grey-text">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['verified_by_name']); ?></td>
                                            <td class="center-align">
                                                <?php if ($payment['payment_method'] !== 'cash' && !$payment['verified'] && $payment['payment_proof']): ?>
                                                    <button class="btn-small green verify-btn" onclick="verifyPayment(<?php echo $payment['id']; ?>, 'verify')">
                                                        <i class="material-icons">check</i>
                                                    </button>
                                                    <button class="btn-small red reject-btn" onclick="verifyPayment(<?php echo $payment['id']; ?>, 'reject')">
                                                        <i class="material-icons">close</i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-small blue-grey" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                    <i class="material-icons">info</i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
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
    </main>

    <div id="payment-details-modal" class="modal">
        <div class="modal-content">
            <h4>Payment Details</h4>
            <div id="payment-details-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-blue btn-flat">Close</a>
        </div>
    </div>

    <div id="payment-proof-modal" class="modal">
        <div class="modal-content">
            <h4>Payment Proof</h4>
            <div class="payment-proof-container">
                <img id="payment-proof-image" src="" alt="Payment Proof" class="responsive-img materialboxed">
            </div>
            <div id="verification-buttons" class="verification-buttons center-align" style="margin-top: 20px;">
                <button class="btn green" onclick="verifyPayment(currentPaymentId, 'verify')">
                    <i class="material-icons left">check</i>Verify Payment
                </button>
                <button class="btn red" onclick="verifyPayment(currentPaymentId, 'reject')">
                    <i class="material-icons left">close</i>Reject Payment
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-close waves-effect waves-grey btn-flat">Close</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        let currentPaymentId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            var selects = document.querySelectorAll('select');
            M.FormSelect.init(selects);

            var modals = document.querySelectorAll('.modal');
            M.Modal.init(modals);

            var materialboxed = document.querySelectorAll('.materialboxed');
            M.Materialbox.init(materialboxed);
        });

        function filterPayments() {
            const searchInput = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const memberName = row.querySelector('td:first-child').textContent.toLowerCase();
                const status = row.querySelector('.status-text').textContent.toLowerCase();
                const matchesSearch = memberName.includes(searchInput);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        function viewPaymentDetails(paymentId) {
            fetch(`${SITE_URL}/admin/ajax/get_payment_details.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('payment-details-modal');
                        const content = document.getElementById('payment-details-content');
                        
                        content.innerHTML = `
                            <div class="row">
                                <div class="col s12">
                                    <table class="striped">
                                        <tr>
                                            <th>Member</th>
                                            <td>${data.payment.member_name}<br>
                                                <small class="grey-text">${data.payment.member_email}</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Plan</th>
                                            <td>${data.payment.plan_name}<br>
                                                <small class="grey-text">${data.payment.duration_months} months</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Amount</th>
                                            <td>₱${parseFloat(data.payment.amount).toFixed(2)}</td>
                                        </tr>
                                        <tr>
                                            <th>Payment Date</th>
                                            <td>${data.payment.payment_date}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><span class="status-text status-${data.payment.status.toLowerCase()}">${data.payment.status}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Method</th>
                                            <td>${data.payment.payment_method}</td>
                                        </tr>
                                        <tr>
                                            <th>Reference Number</th>
                                            <td>${data.payment.reference_number || 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <th>Verified By</th>
                                            <td>${data.payment.verified_by_name || 'Not verified'}</td>
                                        </tr>
                                        <tr>
                                            <th>Verified At</th>
                                            <td>${data.payment.verified_at || 'Not verified'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        M.Modal.getInstance(modal).open();
                    } else {
                        M.toast({html: 'Error loading payment details', classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error loading payment details', classes: 'red'});
                });
        }

        function viewPaymentProof(paymentId) {
            currentPaymentId = paymentId;
            fetch(`${SITE_URL}/admin/ajax/get_payment_details.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.payment.payment_proof) {
                        const modal = document.getElementById('payment-proof-modal');
                        const img = document.getElementById('payment-proof-image');
                        const verificationButtons = document.getElementById('verification-buttons');
                        
                        img.src = `${SITE_URL}/uploads/payment_proofs/${data.payment.payment_proof}`;
                        verificationButtons.style.display = data.payment.status === 'pending' ? 'block' : 'none';
                        
                        M.Modal.getInstance(modal).open();
                    } else {
                        M.toast({html: 'No payment proof available', classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error loading payment proof', classes: 'red'});
                });
        }

        function verifyPayment(paymentId, action) {
            const confirmMessage = action === 'verify' ? 
                'Are you sure you want to verify this payment?' : 
                'Are you sure you want to reject this payment?';
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            const data = {
                payment_id: paymentId,
                action: action
            };
            
            if (action === 'reject') {
                const reason = prompt('Please provide a reason for rejection:');
                if (reason === null) return; // User canceled
                data.rejection_reason = reason;
            }
            
            fetch(`${SITE_URL}/admin/ajax/payment_operations.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: data.message, classes: 'green'});
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    M.toast({html: data.message, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error processing payment', classes: 'red'});
            });
        }
    </script>

    <style>
        .status-text {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .status-pending {
            background-color: #ffd54f;
            color: #000;
        }
        .status-verified {
            background-color: #81c784;
            color: #fff;
        }
        .status-rejected {
            background-color: #e57373;
            color: #fff;
        }
        .payment-proof-container {
            text-align: center;
            margin: 20px 0;
        }
        .payment-proof-container img {
            max-width: 100%;
            max-height: 500px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .verification-buttons {
            margin-top: 20px;
        }
        .verification-buttons button {
            margin: 0 10px;
        }
        .btn-small {
            padding: 0 8px;
            margin: 0 4px;
        }
        .btn-small i {
            font-size: 1.2rem;
        }
        .modal {
            max-height: 90%;
            width: 90%;
            max-width: 800px;
        }
        @media only screen and (max-width: 600px) {
            .modal {
                width: 95%;
            }
        }
    </style>
</body>
</html>