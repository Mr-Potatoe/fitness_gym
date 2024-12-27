<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'members';
$page_title = 'Manage Members';

// Get search parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';

// Build query
$query = "SELECT u.*, 
          CASE 
            WHEN s.status = 'active' AND s.start_date <= CURDATE() AND s.end_date >= CURDATE() THEN 'Active'
            WHEN s.status = 'pending' THEN 'Pending'
            WHEN s.status = 'active' AND s.end_date < CURDATE() THEN 'Expired'
            ELSE 'Inactive'
          END as subscription_status,
          p.name as plan_name,
          p.duration_months,
          s.start_date as subscription_start,
          s.end_date as subscription_end,
          s.id as subscription_id,
          COALESCE(py.status, 'pending') as payment_status,
          py.amount as payment_amount,
          py.payment_date,
          u.created_at as member_since,
          u.verified,
          u.verified_at,
          CONCAT(admin.full_name) as verified_by_name,
          u.staff_notes,
          (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id) as total_subscriptions
          FROM users u 
          LEFT JOIN (
              SELECT * FROM subscriptions 
              WHERE (status = 'active' AND end_date >= CURDATE()) 
              OR status = 'pending'
              ORDER BY created_at DESC 
              LIMIT 1
          ) s ON u.id = s.user_id
          LEFT JOIN plans p ON s.plan_id = p.id 
          LEFT JOIN payments py ON s.id = py.subscription_id
          LEFT JOIN users admin ON u.verified_by = admin.id
          WHERE u.role = 'member' 
          AND u.permanently_deleted = 0";

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
}

if ($status !== 'all') {
    if ($status === 'active') {
        $query .= " AND s.status = 'active' AND s.start_date <= CURDATE() AND s.end_date >= CURDATE()";
    } else if ($status === 'inactive') {
        $query .= " AND (s.status IS NULL OR s.status != 'active' OR s.end_date < CURDATE())";
    } else if ($status === 'pending') {
        $query .= " AND s.status = 'pending'";
    } else if ($status === 'expired') {
        $query .= " AND s.status = 'active' AND s.end_date < CURDATE()";
    }
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);

if ($search) {
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();
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
                        <!-- Search and Filter -->
                        <div class="row">
                            <div class="col s12 m4">
                                <div class="input-field">
                                    <i class="material-icons prefix">search</i>
                                    <input type="text" id="search" placeholder="Search members..." 
                                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="updateFilters()">
                                </div>
                            </div>
                            <div class="col s12 m4">
                                <div class="input-field">
                                    <select id="status-filter" onchange="updateFilters()">
                                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                    <label>Filter by Status</label>
                                </div>
                            </div>
                            <div class="col s12 m4">
                                <button class="btn blue waves-effect waves-light right" onclick="addMember()">
                                    <i class="material-icons left">person_add</i>
                                    Add Member
                                </button>
                            </div>
                        </div>

                        <!-- Members Table -->
                        <div class="responsive-table">
                            <table class="striped highlight" id="members-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Current Plan</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Valid Until</th>
                                        <th>Member Since</th>
                                        <th>Verified</th>
                                        <th class="center-align">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while($member = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                                    <?php if ($member['total_subscriptions'] > 0): ?>
                                                        <br><small class="grey-text"><?php echo $member['total_subscriptions']; ?> subscription(s)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($member['email']); ?>
                                                    <br><small class="grey-text"><?php echo htmlspecialchars($member['username']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($member['plan_name']): ?>
                                                        <?php echo htmlspecialchars($member['plan_name']); ?>
                                                        <br><small class="grey-text"><?php echo $member['duration_months']; ?> months</small>
                                                    <?php else: ?>
                                                        <span class="grey-text">No Plan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($member['subscription_status']); ?>">
                                                        <?php echo $member['subscription_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($member['subscription_status'] !== 'Inactive'): ?>
                                                        <span class="status-badge status-<?php echo $member['payment_status']; ?>">
                                                            <?php echo ucfirst($member['payment_status']); ?>
                                                        </span>
                                                        <?php if ($member['payment_amount']): ?>
                                                            <br><small class="grey-text">₱<?php echo number_format($member['payment_amount'], 2); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="grey-text">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($member['subscription_end']): ?>
                                                        <?php 
                                                        $end_date = new DateTime($member['subscription_end']);
                                                        $now = new DateTime();
                                                        $days_left = $now->diff($end_date)->days;
                                                        
                                                        echo date('M d, Y', strtotime($member['subscription_end']));
                                                        
                                                        if ($end_date > $now) {
                                                            echo "<br><small class='grey-text'>{$days_left} days left</small>";
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="grey-text">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($member['member_since'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($member['verified']): ?>
                                                        <span class="status-badge status-verified tooltipped" 
                                                              data-position="top" 
                                                              data-tooltip="Verified by <?php echo $member['verified_by_name']; ?> on <?php echo date('M d, Y', strtotime($member['verified_at'])); ?>">
                                                            <i class="material-icons tiny">verified</i> Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-pending">
                                                            <i class="material-icons tiny">pending</i> Unverified
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="center-align">
                                                    <div class="action-buttons">
                                                        <button onclick="viewMemberDetails(<?php echo $member['id']; ?>)" 
                                                                class="btn-floating btn-small blue tooltipped"
                                                                data-position="top" data-tooltip="View Details">
                                                            <i class="material-icons">visibility</i>
                                                        </button>
                                                        <?php if (!$member['verified']): ?>
                                                            <button onclick="verifyMember(<?php echo $member['id']; ?>)"
                                                                    class="btn-floating btn-small green tooltipped"
                                                                    data-position="top" data-tooltip="Verify Member">
                                                                <i class="material-icons">verified_user</i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="editMember(<?php echo $member['id']; ?>)"
                                                                class="btn-floating btn-small orange tooltipped"
                                                                data-position="top" data-tooltip="Edit Member">
                                                            <i class="material-icons">edit</i>
                                                        </button>
                                                        <?php if ($member['subscription_status'] === 'Expired'): ?>
                                                            <button onclick="renewMembership(<?php echo $member['id']; ?>)"
                                                                    class="btn-floating btn-small purple tooltipped"
                                                                    data-position="top" data-tooltip="Renew Membership">
                                                                <i class="material-icons">autorenew</i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($member['subscription_status'] === 'Inactive'): ?>
                                                            <button onclick="deleteMember(<?php echo $member['id']; ?>)"
                                                                    class="btn-floating btn-small red tooltipped"
                                                                    data-position="top" data-tooltip="Delete Member">
                                                                <i class="material-icons">delete</i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="center-align">No members found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Member Modal -->
    <div id="edit-member-modal" class="modal">
        <div class="modal-content">
            <h4>Edit Member</h4>
            <form id="edit-member-form">
                <input type="hidden" id="edit_member_id" name="id">
                <div class="row">
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="edit_full_name" name="full_name" required>
                            <label for="edit_full_name">Full Name</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="edit_username" name="username" required>
                            <label for="edit_username">Username</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <input type="email" id="edit_email" name="email" required>
                            <label for="edit_email">Email</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
                    <button type="submit" class="waves-effect waves-green btn blue">Update Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div id="member-details-modal" class="modal">
        <div class="modal-content">
            <h4>Member Details</h4>
            <div id="member-details-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-blue btn-flat">Close</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        // Define SITE_URL for JavaScript
        const SITE_URL = '<?php echo SITE_URL; ?>';
    </script>

    <!-- Add the JavaScript for member operations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all Materialize components
            M.AutoInit();
            
            // Initialize modals with specific options
            var elems = document.querySelectorAll('.modal');
            var instances = M.Modal.init(elems, {
                dismissible: true,
                opacity: 0.5,
                inDuration: 300,
                outDuration: 200,
                startingTop: '4%',
                endingTop: '10%'
            });

            // Initialize form labels
            M.updateTextFields();

            // Edit Member Form Submit
            const editMemberForm = document.getElementById('edit-member-form');
            if (editMemberForm) {
                editMemberForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = {
                        action: 'edit',
                        id: document.getElementById('edit_member_id').value,
                        username: document.getElementById('edit_username').value,
                        email: document.getElementById('edit_email').value,
                        full_name: document.getElementById('edit_full_name').value
                    };
                    
                    fetch(`${SITE_URL}/admin/ajax/member_operations.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close modal
                            var modal = M.Modal.getInstance(document.getElementById('edit-member-modal'));
                            modal.close();
                            
                            // Show success message
                            M.toast({html: data.message, classes: 'green'});
                            
                            // Reload page after delay
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            M.toast({html: data.message, classes: 'red'});
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        M.toast({html: 'Error updating member', classes: 'red'});
                    });
                });
            }

            // Initialize all modals
            var modals = document.querySelectorAll('.modal');
            M.Modal.init(modals, {
                dismissible: true,
                opacity: 0.5,
                inDuration: 300,
                outDuration: 200,
                startingTop: '4%',
                endingTop: '10%'
            });

            // Initialize tooltips
            var tooltips = document.querySelectorAll('.tooltipped');
            M.Tooltip.init(tooltips);

            // Delete Member
            document.querySelectorAll('.delete-member').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    
                    if (confirm(`WARNING: Are you sure you want to permanently delete "${name}"?\n\nThis action will:\n- Delete all member data\n- Remove subscription history\n- Remove payment records\n\nThis action CANNOT be undone!`)) {
                        fetch('ajax/member_operations.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'delete',
                                id: id
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                M.toast({html: 'Member deleted successfully'});
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                M.toast({html: data.message || 'Error deleting member', classes: 'red'});
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            M.toast({html: 'Error deleting member', classes: 'red'});
                        });
                    }
                });
            });
        });

        function editMember(memberId) {
            fetch(`${SITE_URL}/admin/ajax/member_operations.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get',
                    id: memberId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const member = data.data;
                    document.getElementById('edit_member_id').value = member.id;
                    document.getElementById('edit_username').value = member.username;
                    document.getElementById('edit_email').value = member.email;
                    document.getElementById('edit_full_name').value = member.full_name;
                    
                    // Reinitialize Materialize labels
                    M.updateTextFields();
                    
                    // Open modal
                    const modal = M.Modal.getInstance(document.getElementById('edit-member-modal'));
                    modal.open();
                } else {
                    M.toast({html: data.message, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error getting member details', classes: 'red'});
            });
        }

        function toggleMemberStatus(memberId, currentStatus) {
            if (confirm(`Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this member?`)) {
                fetch(`${SITE_URL}/admin/ajax/member_operations.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        id: memberId,
                        status: currentStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        M.toast({html: data.message, classes: 'green'});
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        M.toast({html: data.message, classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error updating member status', classes: 'red'});
                });
            }
        }

        function deleteMember(memberId, permanent = false) {
            const confirmMsg = permanent ? 
                'Are you sure you want to permanently delete this member? This cannot be undone!' : 
                'Are you sure you want to deactivate this member?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            fetch(`${SITE_URL}/admin/ajax/delete_member.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    member_id: memberId,
                    permanent: permanent
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: 'Member deleted successfully'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    M.toast({html: data.message || 'Error deleting member', classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error deleting member', classes: 'red'});
            });
        }

        function reactivateMember(memberId) {
            if (!confirm('Are you sure you want to reactivate this member?')) {
                return;
            }
            
            fetch(`${SITE_URL}/admin/ajax/reactivate_member.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    member_id: memberId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: 'Member reactivated successfully'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    M.toast({html: data.message || 'Error reactivating member', classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error reactivating member', classes: 'red'});
            });
        }

        function updateFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status-filter').value;
            window.location.href = `members.php?search=${encodeURIComponent(search)}&status=${status}`;
        }

        function viewMemberDetails(memberId) {
            // Initialize the modal first
            const modal = document.getElementById('member-details-modal');
            const instance = M.Modal.getInstance(modal);

            fetch(`${SITE_URL}/admin/ajax/get_member_details.php?id=${memberId}`)
                .then(response => response.text())
                .then(text => {
                    try {
                        // Log the raw response for debugging
                        console.log('Server response:', text);
                        const data = JSON.parse(text);
                        
                        if (data.success) {
                            document.getElementById('member-details-content').innerHTML = data.html;
                            instance.open();
                        } else {
                            M.toast({html: data.message || 'Failed to load member details', classes: 'red'});
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        M.toast({html: 'Error loading member details', classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    M.toast({html: 'Error connecting to server', classes: 'red'});
                });
        }

        function verifyMember(memberId) {
            if (!confirm('Are you sure you want to verify this member?')) {
                return;
            }
            
            fetch(`${SITE_URL}/admin/ajax/verify_member.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    member_id: memberId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: 'Member verified successfully'});
                    setTimeout(() => location.reload(), 1000);
                } else {
                    M.toast({html: data.message || 'Error verifying member', classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error verifying member', classes: 'red'});
            });
        }
    </script>

    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .status-badge i {
            font-size: 14px;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-pending {
            background-color: #FFC107;
            color: black;
        }
        .status-expired {
            background-color: #FF5722;
            color: white;
        }
        .status-inactive {
            background-color: #9E9E9E;
            color: white;
        }
        .status-verified {
            background-color: #2196F3;
            color: white;
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
            font-size: 1.1rem;
        }
        td small {
            display: block;
            line-height: 1.2;
        }
        .input-field .prefix {
            font-size: 1.5rem;
            top: 0.5rem;
        }
        .input-field input[type=text] {
            padding-left: 3rem;
        }
        @media only screen and (max-width: 992px) {
            .action-buttons {
                flex-wrap: wrap;
            }
            .status-badge {
                font-size: 0.75rem;
                padding: 3px 6px;
            }
        }
    </style>
</body>
</html>