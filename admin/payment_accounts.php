<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'payment_accounts';
$page_title = 'Payment Accounts';

// Get all payment accounts with prepared statement
$stmt = $conn->prepare("SELECT * FROM payment_accounts WHERE deleted_at IS NULL ORDER BY account_type, created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Accounts - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .sidenav { width: 250px; }
        .sidenav li>a { display: flex; align-items: center; }
        .sidenav li>a>i { margin-right: 10px; }
        main { padding: 20px; }
        @media only screen and (min-width: 993px) {
            main { margin-left: 250px; }
        }
        .account-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        .status-active { color: #4CAF50; }
        .status-inactive { color: #F44336; }
        .btn-small {
            padding: 0 8px;
            height: 24px;
            line-height: 24px;
        }
        .btn-small i {
            font-size: 1.2rem;
            line-height: 24px;
        }
        table td {
            padding: 8px 5px;
        }
        .account-actions form {
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <div class="row">
                            <div class="col s6">
                                <span class="card-title">Payment Accounts</span>
                            </div>
                            <div class="col s6 right-align">
                                <button class="btn blue waves-effect waves-light modal-trigger" data-target="add-account-modal">
                                    <i class="material-icons left">add</i>
                                    Add Account
                                </button>
                            </div>
                        </div>
                        
                        <table class="striped responsive-table">
                            <thead>
                                <tr>
                                    <th>Payment Type</th>
                                    <th>Account Name</th>
                                    <th>Account Number</th>
                                    <th class="center-align">Status</th>
                                    <th class="center-align">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($account = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($account['account_type']); ?></td>
                                            <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                                            <td><?php echo htmlspecialchars($account['account_number']); ?></td>
                                            <td class="center-align">
                                                <span class="status-<?php echo $account['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="center-align">
                                                <div class="account-actions">
                                                    <button class="btn-small blue waves-effect waves-light edit-btn" 
                                                            data-id="<?php echo $account['id']; ?>"
                                                            data-type="<?php echo htmlspecialchars($account['account_type']); ?>"
                                                            data-name="<?php echo htmlspecialchars($account['account_name']); ?>"
                                                            data-number="<?php echo htmlspecialchars($account['account_number']); ?>">
                                                        <i class="material-icons">edit</i>
                                                    </button>
                                                    <button class="btn-small <?php echo $account['is_active'] ? 'red' : 'green'; ?> waves-effect waves-light toggle-btn"
                                                            onclick="toggleAccountStatus(<?php echo $account['id']; ?>, <?php echo $account['is_active'] ? 'false' : 'true'; ?>)">
                                                        <i class="material-icons"><?php echo $account['is_active'] ? 'clear' : 'check'; ?></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="center-align">No payment accounts found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Account Modal -->
    <div id="add-account-modal" class="modal">
        <form id="add-account-form">
            <div class="modal-content">
                <h4>Add Payment Account</h4>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" id="account_type" name="account_type" required>
                        <label for="account_type">Payment Type (e.g., GCash, Bank Transfer)</label>
                    </div>
                    <div class="input-field col s12">
                        <input type="text" id="account_name" name="account_name" required>
                        <label for="account_name">Account Name</label>
                    </div>
                    <div class="input-field col s12">
                        <input type="text" id="account_number" name="account_number" required>
                        <label for="account_number">Account Number</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-close waves-effect waves-light btn-flat">Cancel</button>
                <button type="submit" class="waves-effect waves-light btn blue">Add Account</button>
            </div>
        </form>
    </div>

    <!-- Edit Account Modal -->
    <div id="edit-account-modal" class="modal">
        <form id="edit-account-form">
            <div class="modal-content">
                <h4>Edit Payment Account</h4>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" id="edit_account_type" name="account_type" required>
                        <label for="edit_account_type">Payment Type</label>
                    </div>
                    <div class="input-field col s12">
                        <input type="text" id="edit_account_name" name="account_name" required>
                        <label for="edit_account_name">Account Name</label>
                    </div>
                    <div class="input-field col s12">
                        <input type="text" id="edit_account_number" name="account_number" required>
                        <label for="edit_account_number">Account Number</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="edit_account_id" name="id">
                <button type="button" class="modal-close waves-effect waves-light btn-flat">Cancel</button>
                <button type="submit" class="waves-effect waves-light btn blue">Save Changes</button>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        M.AutoInit();

        // Add Account Form Handler
        document.getElementById('add-account-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                const response = await fetch('ajax/add_payment_account.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    M.toast({html: result.message, classes: 'green'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                M.toast({html: error.message, classes: 'red'});
            }
        });

        // Edit Account Button Handler
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const type = this.dataset.type;
                const name = this.dataset.name;
                const number = this.dataset.number;
                
                document.getElementById('edit_account_id').value = id;
                document.getElementById('edit_account_type').value = type;
                document.getElementById('edit_account_name').value = name;
                document.getElementById('edit_account_number').value = number;
                
                M.updateTextFields();
                
                const modal = M.Modal.getInstance(document.getElementById('edit-account-modal'));
                modal.open();
            });
        });

        // Edit Account Form Handler
        document.getElementById('edit-account-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                const response = await fetch('ajax/edit_payment_account.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    M.toast({html: result.message, classes: 'green'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                M.toast({html: error.message, classes: 'red'});
            }
        });
    });

    // Toggle Account Status
    async function toggleAccountStatus(id, newStatus) {
        if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this account?`)) {
            return;
        }
        
        try {
            const response = await fetch('ajax/toggle_payment_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, status: newStatus })
            });
            
            const result = await response.json();
            
            if (result.success) {
                M.toast({html: result.message, classes: 'green'});
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            M.toast({html: error.message, classes: 'red'});
        }
    }
    </script>
</body>
</html>