<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'staff';
$page_title = 'Manage Staff';

// Get search parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';

// Build query
$query = "SELECT * FROM users WHERE role = 'staff' AND permanently_deleted = 0";

if ($search) {
    $query .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
}

if ($status !== 'all') {
    if ($status === 'active') {
        $query .= " AND status = 'active'";
    } else if ($status === 'inactive') {
        $query .= " AND status = 'inactive'";
    }
}

$result = $conn->query($query);
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
                        <div class="row">
                            <div class="col s12">
                                <div class="right-align">
                                    <a href="#add-staff-modal" class="btn-floating btn-large blue waves-effect waves-light modal-trigger">
                                        <i class="material-icons">add</i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="row">
                            <div class="col s12 m6">
                                <input type="text" id="search" placeholder="Search staff..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col s12 m6">
                                <select id="status-filter" onchange="updateFilters()">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Staff Table -->
                        <table class="striped responsive-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="center-align">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($staff = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td>
                                                <span class="status-<?php echo $staff['status']; ?>">
                                                    <?php echo ucfirst($staff['status']); ?>
                                                </span>
                                            </td>
                                            <td class="center-align">
                                                <div class="staff-actions">
                                                    <button type="button" class="btn-small blue waves-effect waves-light" 
                                                            onclick="editStaff(<?php echo $staff['id']; ?>)">
                                                        <i class="material-icons">edit</i>
                                                    </button>
                                                    <button type="button" class="btn-small <?php echo $staff['status'] === 'active' ? 'orange' : 'green'; ?> waves-effect waves-light"
                                                            onclick="toggleStaffStatus(<?php echo $staff['id']; ?>, '<?php echo $staff['status']; ?>')">
                                                        <i class="material-icons"><?php echo $staff['status'] === 'active' ? 'block' : 'check'; ?></i>
                                                    </button>
                                                    <button type="button" class="btn-small red waves-effect waves-light" 
                                                            onclick="deletePermanently(<?php echo $staff['id']; ?>, '<?php echo addslashes($staff['username']); ?>')">
                                                        <i class="material-icons">delete_forever</i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="center-align">No staff found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        // Define SITE_URL for JavaScript
        const SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
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

            // Add Staff Form Submit
            const addStaffForm = document.getElementById('add-staff-form');
            if (addStaffForm) {
                addStaffForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form values
                    const formData = {
                        action: 'add',
                        username: document.getElementById('add_username').value,
                        email: document.getElementById('add_email').value,
                        password: document.getElementById('add_password').value,
                        full_name: document.getElementById('add_full_name').value
                    };

                    // Send request to server
                    fetch(`${SITE_URL}/admin/ajax/staff_operations.php`, {
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
                            var modal = M.Modal.getInstance(document.getElementById('add-staff-modal'));
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
                        M.toast({html: 'Error adding staff', classes: 'red'});
                    });
                });
            }

            // Edit Staff Form Submit - Moved inside DOMContentLoaded
            const editStaffForm = document.getElementById('edit-staff-form');
            if (editStaffForm) {
                editStaffForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = {
                        action: 'edit',
                        id: document.getElementById('edit_staff_id').value,
                        username: document.getElementById('edit_username').value,
                        email: document.getElementById('edit_email').value,
                        full_name: document.getElementById('edit_full_name').value
                    };
                    
                    fetch(`${SITE_URL}/admin/ajax/staff_operations.php`, {
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
                            var modal = M.Modal.getInstance(document.getElementById('edit-staff-modal'));
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
                        M.toast({html: 'Error updating staff', classes: 'red'});
                    });
                });
            }
        });

        function editStaff(staffId) {
            fetch(`${SITE_URL}/admin/ajax/staff_operations.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get',
                    id: staffId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const staff = data.data;
                    document.getElementById('edit_staff_id').value = staff.id;
                    document.getElementById('edit_username').value = staff.username;
                    document.getElementById('edit_email').value = staff.email;
                    document.getElementById('edit_full_name').value = staff.full_name;
                    
                    // Reinitialize Materialize labels
                    M.updateTextFields();
                    
                    // Open modal
                    const modal = M.Modal.getInstance(document.getElementById('edit-staff-modal'));
                    modal.open();
                } else {
                    M.toast({html: data.message, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error getting staff details', classes: 'red'});
            });
        }

        function toggleStaffStatus(staffId, currentStatus) {
            if (confirm(`Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this staff member?`)) {
                fetch(`${SITE_URL}/admin/ajax/staff_operations.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        id: staffId,
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
                    M.toast({html: 'Error updating staff status', classes: 'red'});
                });
            }
        }

        function deletePermanently(staffId, username) {
            if (confirm(`Are you sure you want to permanently delete staff member "${username}"?`)) {
                fetch(`${SITE_URL}/admin/ajax/staff_operations.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: staffId
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
                    M.toast({html: 'Error deleting staff', classes: 'red'});
                });
            }
        }

        function updateFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status-filter').value;
            window.location.href = `staff.php?search=${encodeURIComponent(search)}&status=${status}`;
        }
    </script>
    <style>
        /* Table styles */
        table td, table th {
            padding: 8px 16px !important;
        }
        
        .staff-actions {
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
        .staff-actions form {
            margin: 0;
        }
        .modal {
            max-width: 500px;
            border-radius: 8px;
        }
        .modal .modal-content {
            padding: 24px;
        }
        .modal .modal-content h4 {
            margin-top: 0;
            margin-bottom: 24px;
            font-size: 1.8rem;
            color: #1565C0;
        }
        .modal .input-field {
            margin-bottom: 16px;
        }
        .modal .input-field input {
            padding-left: 8px;
        }
        .modal .input-field label {
            left: 8px;
        }
        .modal .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #ddd;
            background-color: #f5f5f5;
        }
        .modal .btn {
            margin-left: 8px;
        }
        .modal .btn-flat {
            color: #757575;
        }
        .modal form {
            margin-bottom: 0;
        }
    </style>

    <!-- Add Staff Modal -->
    <div id="add-staff-modal" class="modal">
        <div class="modal-content">
            <h4>Add Staff</h4>
            <form id="add-staff-form" method="POST">
                <div class="row">
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="add_full_name" name="full_name" required>
                            <label for="add_full_name">Full Name</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="add_username" name="username" required>
                            <label for="add_username">Username</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <input type="email" id="add_email" name="email" required>
                            <label for="add_email">Email</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <input type="password" id="add_password" name="password" required>
                            <label for="add_password">Password</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
                    <button type="submit" class="waves-effect waves-green btn blue">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="edit-staff-modal" class="modal">
        <div class="modal-content">
            <h4>Edit Staff</h4>
            <form id="edit-staff-form">
                <input type="hidden" id="edit_staff_id" name="id">
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
                    <button type="submit" class="waves-effect waves-green btn blue">Update Staff</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 