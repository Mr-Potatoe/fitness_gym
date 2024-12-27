<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

$current_page = 'plans';
$page_title = 'Manage Plans';

// Get all active plans with creator info
$admin_role = 'admin';
$user_id = $_SESSION['user_id'];

$query = "SELECT p.*, 
          CONCAT(u.full_name) as created_by_name,
          u.role as creator_role,
          COUNT(DISTINCT CASE 
              WHEN s.status = 'active' 
              AND s.start_date <= CURDATE() 
              AND s.end_date >= CURDATE() 
              THEN s.user_id 
          END) as active_subscribers
          FROM plans p
          LEFT JOIN users u ON p.created_by = u.id
          LEFT JOIN subscriptions s ON p.id = s.plan_id
          WHERE p.deleted_at IS NULL
          AND (u.role = ? OR p.created_by = ?)
          GROUP BY p.id
          ORDER BY CASE WHEN u.role = ? THEN 1 ELSE 2 END, p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('sis', $admin_role, $user_id, $admin_role);
$stmt->execute();
$plans = $stmt->get_result();

include 'includes/header.php';
?>

<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <div class="card-title flex-row">
                    <span>Membership Plans</span>
                    <a href="#addPlanModal" class="btn blue waves-effect waves-light modal-trigger">
                        <i class="material-icons left">add</i>
                        Add New Plan
                    </a>
                </div>

                <div class="table-container">
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Duration</th>
                                <th>Price</th>
                                <th>Features</th>
                                <th>Active Subscribers</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($plans && $plans->num_rows > 0):
                                while($plan = $plans->fetch_assoc()): 
                                    $duration_text = $plan['duration_months'] . ' ' . ($plan['duration_months'] == 1 ? 'month' : 'months');
                                    $features = !empty($plan['features']) ? json_decode($plan['features'], true) : [];
                                    $is_admin_plan = $plan['creator_role'] === 'admin';
                                    $can_modify = $plan['created_by'] == $_SESSION['user_id'];
                            ?>
                                <tr class="<?php echo $is_admin_plan ? 'admin-plan' : ''; ?>">
                                    <td>
                                        <?php echo htmlspecialchars($plan['name']); ?>
                                        <?php if ($is_admin_plan): ?>
                                            <span class="new badge blue" data-badge-caption="Admin Plan"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $duration_text; ?></td>
                                    <td><?php echo formatPrice($plan['price']); ?></td>
                                    <td>
                                        <?php if (!empty($features)): ?>
                                            <a href="#!" class="view-features blue-text" 
                                               data-features='<?php echo htmlspecialchars(json_encode($features)); ?>'>
                                                View Features
                                            </a>
                                        <?php else: ?>
                                            <span class="grey-text">No features</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="chip">
                                            <?php echo $plan['active_subscribers']; ?> active
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($plan['created_by_name']); ?>
                                        <?php if ($is_admin_plan): ?>
                                            <span class="grey-text">(Admin)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($can_modify): ?>
                                                <a href="#editPlanModal" class="btn-floating btn-small blue modal-trigger edit-plan tooltipped"
                                                   data-position="top" data-tooltip="Edit Plan"
                                                   data-id="<?php echo $plan['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                                   data-duration="<?php echo $plan['duration_months']; ?>"
                                                   data-price="<?php echo $plan['price']; ?>"
                                                   data-features='<?php echo htmlspecialchars(json_encode($features)); ?>'>
                                                    <i class="material-icons">edit</i>
                                                </a>
                                                <button class="btn-floating btn-small red tooltipped delete-plan"
                                                        data-position="top" data-tooltip="Delete Plan"
                                                        data-id="<?php echo $plan['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($plan['name']); ?>">
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
                                    <td colspan="7" class="center-align">No plans found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Plan Modal -->
<div id="addPlanModal" class="modal">
    <form id="addPlanForm">
        <div class="modal-content">
            <h4>Add New Plan</h4>
            <div class="row">
                <div class="input-field col s12">
                    <input type="text" id="addPlanName" name="name" required>
                    <label for="addPlanName">Plan Name</label>
                </div>
            </div>
            <div class="row">
                <div class="input-field col s6">
                    <input type="number" id="addPlanDuration" name="duration_months" min="1" max="36" required>
                    <label for="addPlanDuration">Duration (months)</label>
                </div>
                <div class="input-field col s6">
                    <input type="number" id="addPlanPrice" name="price" min="0" step="0.01" required>
                    <label for="addPlanPrice">Price</label>
                </div>
            </div>
            <div class="row">
                <div class="col s12">
                    <label>Features (one per line)</label>
                    <div class="features-container">
                        <div class="feature-inputs">
                            <div class="feature-input-row">
                                <div class="input-field">
                                    <input type="text" name="features[]" class="feature-input">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-flat add-feature waves-effect">
                            <i class="material-icons left">add</i>
                            Add Feature
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
            <button type="submit" class="waves-effect waves-light btn blue">
                <i class="material-icons left">add</i>
                Create Plan
            </button>
        </div>
    </form>
</div>

<!-- Features Modal -->
<div id="featuresModal" class="modal">
    <div class="modal-content">
        <h4>Plan Features</h4>
        <ul class="collection features-list">
        </ul>
    </div>
    <div class="modal-footer">
        <button type="button" class="modal-close waves-effect waves-blue btn-flat">Close</button>
    </div>
</div>

<!-- Edit Plan Modal -->
<div id="editPlanModal" class="modal">
    <form id="editPlanForm">
        <div class="modal-content">
            <h4>Edit Plan</h4>
            <input type="hidden" name="id" id="editPlanId">
            <div class="row">
                <div class="input-field col s12">
                    <input type="text" id="editPlanName" name="name" required>
                    <label for="editPlanName">Plan Name</label>
                </div>
            </div>
            <div class="row">
                <div class="input-field col s6">
                    <input type="number" id="editPlanDuration" name="duration_months" min="1" max="36" required>
                    <label for="editPlanDuration">Duration (months)</label>
                </div>
                <div class="input-field col s6">
                    <input type="number" id="editPlanPrice" name="price" min="0" step="0.01" required>
                    <label for="editPlanPrice">Price</label>
                </div>
            </div>
            <div class="row">
                <div class="col s12">
                    <label>Features (one per line)</label>
                    <div class="features-container">
                        <div class="feature-inputs">
                        </div>
                        <button type="button" class="btn-flat add-feature waves-effect">
                            <i class="material-icons left">add</i>
                            Add Feature
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
            <button type="submit" class="waves-effect waves-light btn blue">
                <i class="material-icons left">save</i>
                Save Changes
            </button>
        </div>
    </form>
</div>

<style>
.flex-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}
.features-container {
    margin-top: 10px;
}
.feature-input-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}
.feature-input-row .input-field {
    flex: 1;
    margin: 0;
}
.remove-feature {
    padding: 0;
    margin-top: 8px;
}
.chip {
    margin: 0;
}
.admin-plan {
    background-color: #f5f5f5;
}
.badge {
    float: none !important;
    margin-left: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    M.AutoInit();

    // View Features
    document.querySelectorAll('.view-features').forEach(button => {
        button.addEventListener('click', function() {
            const features = JSON.parse(this.dataset.features);
            const featuresList = document.querySelector('#featuresModal .features-list');
            featuresList.innerHTML = '';
            
            features.forEach(feature => {
                const li = document.createElement('li');
                li.className = 'collection-item';
                li.textContent = feature;
                featuresList.appendChild(li);
            });
            
            M.Modal.getInstance(document.getElementById('featuresModal')).open();
        });
    });

    // Add Plan Form Handling
    const addPlanForm = document.getElementById('addPlanForm');
    addPlanForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'add',
            name: document.getElementById('addPlanName').value,
            duration_months: document.getElementById('addPlanDuration').value,
            price: document.getElementById('addPlanPrice').value,
            features: Array.from(addPlanForm.querySelectorAll('.feature-input')).map(input => input.value.trim())
        };
        
        try {
            const response = await fetch('ajax/plan_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                M.toast({html: 'Plan created successfully'});
                setTimeout(() => window.location.reload(), 1000);
            } else {
                M.toast({html: result.message || 'Error creating plan', classes: 'red'});
            }
        } catch (error) {
            M.toast({html: 'Error creating plan', classes: 'red'});
        }
    });

    // Edit Plan
    const editPlanForm = document.getElementById('editPlanForm');
    const featureInputs = document.querySelector('.feature-inputs');
    
    document.querySelectorAll('.edit-plan').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('editPlanId').value = data.id;
            document.getElementById('editPlanName').value = data.name;
            document.getElementById('editPlanDuration').value = data.duration;
            document.getElementById('editPlanPrice').value = data.price;
            
            // Clear existing feature inputs
            featureInputs.innerHTML = '';
            
            // Add feature inputs
            const features = JSON.parse(data.features);
            features.forEach(addFeatureInput);
            
            // Update labels
            M.updateTextFields();
        });
    });
    
    // Add Feature Input
    document.querySelector('.add-feature').addEventListener('click', () => addFeatureInput());
    
    function addFeatureInput(value = '') {
        const row = document.createElement('div');
        row.className = 'feature-input-row';
        row.innerHTML = `
            <div class="input-field">
                <input type="text" name="features[]" value="${value}" class="feature-input">
            </div>
            <button type="button" class="btn-flat remove-feature waves-effect waves-red">
                <i class="material-icons red-text">remove_circle</i>
            </button>
        `;
        
        row.querySelector('.remove-feature').addEventListener('click', function() {
            row.remove();
        });
        
        featureInputs.appendChild(row);
    }
    
    // Handle form submission
    editPlanForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'edit',
            id: document.getElementById('editPlanId').value,
            name: document.getElementById('editPlanName').value,
            duration_months: document.getElementById('editPlanDuration').value,
            price: document.getElementById('editPlanPrice').value,
            features: Array.from(document.querySelectorAll('.feature-input')).map(input => input.value.trim())
        };
        
        try {
            const response = await fetch('ajax/plan_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                M.toast({html: 'Plan updated successfully'});
                setTimeout(() => window.location.reload(), 1000);
            } else {
                M.toast({html: result.message || 'Error updating plan', classes: 'red'});
            }
        } catch (error) {
            M.toast({html: 'Error updating plan', classes: 'red'});
        }
    });

    // Delete Plan
    document.querySelectorAll('.delete-plan').forEach(button => {
        button.addEventListener('click', async function() {
            const planId = this.dataset.id;
            const planName = this.dataset.name;
            
            if (!confirm(`Are you sure you want to delete the plan "${planName}"?`)) {
                return;
            }
            
            try {
                const response = await fetch('ajax/plan_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: planId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    M.toast({html: 'Plan deleted successfully'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    M.toast({html: result.message || 'Error deleting plan', classes: 'red'});
                }
            } catch (error) {
                console.error('Error:', error);
                M.toast({html: 'Error deleting plan', classes: 'red'});
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>