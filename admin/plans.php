<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin/staff
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    redirect('/login.php');
}

$current_page = 'plans';
$page_title = 'Manage Plans';

// Get all plans including deleted ones for admin
$plans = getActivePlans(true);

include 'includes/header.php';
?>

<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <div class="card-title flex-row">
                    <span>Membership Plans</span>
                    <?php if (hasRole('admin')): ?>
                        <a href="#addPlanModal" class="btn blue waves-effect waves-light modal-trigger">
                            <i class="material-icons left">add</i>
                            Add New Plan
                        </a>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <div class="flex-right">
                            <div class="input-field status-filter">
                                <select id="statusFilter">
                                    <option value="all">All Plans</option>
                                    <option value="active">Active Plans</option>
                                    <option value="inactive">Inactive Plans</option>
                                </select>
                                <label>Filter by Status</label>
                            </div>
                        </div>
                    </div>
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Duration</th>
                                <th>Price</th>
                                <th>Features</th>
                                <th>Active Subscribers</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($plans)):
                                foreach ($plans as $plan): 
                                    // Format duration text
                                    $duration_text = $plan['duration_months'] . ' ' . ($plan['duration_months'] == 1 ? 'month' : 'months');
                            ?>
                                <tr class="plan-row <?php echo $plan['deleted_at'] ? 'inactive-plan' : 'active-plan'; ?>">
                                    <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                    <td><?php echo $duration_text; ?></td>
                                    <td><?php echo formatPrice($plan['price']); ?></td>
                                    <td>
                                        <?php if (!empty($plan['features'])): ?>
                                            <a href="#!" class="view-features blue-text" 
                                               data-features='<?php echo htmlspecialchars(json_encode($plan['features'])); ?>'>
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
                                    <td><?php echo htmlspecialchars($plan['created_by_name']); ?></td>
                                    <td>
                                        <span class="chip <?php echo $plan['deleted_at'] ? 'red' : 'green'; ?> white-text">
                                            <?php echo $plan['deleted_at'] ? 'Inactive' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (hasRole('admin')): ?>
                                                <a href="#editPlanModal" class="btn-floating btn-small blue modal-trigger edit-plan tooltipped"
                                                   data-position="top" data-tooltip="Edit Plan"
                                                   data-id="<?php echo $plan['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                                   data-duration="<?php echo $plan['duration_months']; ?>"
                                                   data-price="<?php echo $plan['price']; ?>"
                                                   data-features='<?php echo htmlspecialchars(json_encode($plan['features'])); ?>'>
                                                    <i class="material-icons">edit</i>
                                                </a>
                                                
                                                <?php if ($plan['active_subscribers'] == 0): ?>
                                                    <button class="btn-floating btn-small <?php echo $plan['deleted_at'] ? 'green activate-plan' : 'orange deactivate-plan'; ?> tooltipped"
                                                            data-position="top" 
                                                            data-tooltip="<?php echo $plan['deleted_at'] ? 'Activate Plan' : 'Deactivate Plan'; ?>"
                                                            data-id="<?php echo $plan['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($plan['name']); ?>">
                                                        <i class="material-icons"><?php echo $plan['deleted_at'] ? 'check_circle' : 'pause_circle_filled'; ?></i>
                                                    </button>
                                                    
                                                    <button class="btn-floating btn-small red delete-plan tooltipped"
                                                            data-position="top" data-tooltip="Delete Plan"
                                                            data-id="<?php echo $plan['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($plan['name']); ?>">
                                                        <i class="material-icons">delete_forever</i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="8" class="center-align">No plans found</td>
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
                    <input type="text" id="name" name="name" required>
                    <label for="name">Plan Name</label>
                </div>
                <div class="input-field col s12 m6">
                    <input type="number" id="duration_months" name="duration_months" required min="1" max="36">
                    <label for="duration_months">Duration (Months)</label>
                    <span class="helper-text">Enter number of months (1-36)</span>
                </div>
                <div class="input-field col s12 m6">
                    <input type="number" id="price" name="price" required min="0" step="0.01">
                    <label for="price">Price</label>
                    <span class="helper-text">Enter price in PHP</span>
                </div>
                <div class="col s12">
                    <label>Features (one per line)</label>
                    <textarea id="features" name="features" class="materialize-textarea" rows="5"></textarea>
                    <span class="helper-text">Enter each feature on a new line</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
            <button type="submit" class="waves-effect waves-green btn blue">Add Plan</button>
        </div>
    </form>
</div>

<!-- Edit Plan Modal -->
<div id="editPlanModal" class="modal">
    <form id="editPlanForm">
        <div class="modal-content">
            <h4>Edit Plan</h4>
            <div class="row">
                <input type="hidden" id="edit_id" name="id">
                <div class="input-field col s12">
                    <input type="text" id="edit_name" name="name" required>
                    <label for="edit_name">Plan Name</label>
                </div>
                <div class="input-field col s12 m6">
                    <input type="number" id="edit_duration" name="duration_months" min="1" max="36" required>
                    <label for="edit_duration">Duration (Months)</label>
                    <span class="helper-text">Enter number of months (1-36)</span>
                </div>
                <div class="input-field col s12 m6">
                    <input type="number" id="edit_price" name="price" required min="0" step="0.01">
                    <label for="edit_price">Price</label>
                    <span class="helper-text">Enter price in PHP</span>
                </div>
                <div class="col s12">
                    <label>Features (one per line)</label>
                    <textarea id="edit_features" name="features" class="materialize-textarea" rows="5"></textarea>
                    <span class="helper-text">Enter each feature on a new line</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
            <button type="submit" class="waves-effect waves-green btn blue">Update Plan</button>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Materialize components
    M.AutoInit();

    // View Features
    document.querySelectorAll('.view-features').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const features = JSON.parse(this.dataset.features);
            const featuresList = document.querySelector('#featuresModal .features-list');
            featuresList.innerHTML = '';
            
            features.forEach(feature => {
                featuresList.innerHTML += `
                    <li class="collection-item">
                        <i class="material-icons tiny blue-text">check</i>
                        ${feature}
                    </li>`;
            });
            
            M.Modal.getInstance(document.querySelector('#featuresModal')).open();
        });
    });

    // Add Plan Form
    document.getElementById('addPlanForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const features = this.features.value.split('\n').filter(f => f.trim());
        
        fetch('ajax/plan_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                name: this.name.value,
                duration_months: parseInt(this.duration_months.value),
                price: parseFloat(this.price.value),
                features: features
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                M.toast({html: 'Plan added successfully'});
                setTimeout(() => window.location.reload(), 1000);
            } else {
                M.toast({html: data.message || 'Failed to add plan', classes: 'red'});
            }
        })
        .catch(error => {
            console.error('Error:', error);
            M.toast({html: 'An error occurred', classes: 'red'});
        });
    });

    // Edit Plan Form
    document.getElementById('editPlanForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const features = this.features.value.split('\n').filter(f => f.trim());
        
        fetch('ajax/plan_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'edit',
                id: this.id.value,
                name: this.name.value,
                duration_months: parseInt(this.duration_months.value),
                price: parseFloat(this.price.value),
                features: features
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                M.toast({html: 'Plan updated successfully'});
                setTimeout(() => window.location.reload(), 1000);
            } else {
                M.toast({html: data.message || 'Failed to update plan', classes: 'red'});
            }
        })
        .catch(error => {
            console.error('Error:', error);
            M.toast({html: 'An error occurred', classes: 'red'});
        });
    });

    // Edit Plan Button Click
    document.querySelectorAll('.edit-plan').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_duration').value = this.dataset.duration;
            document.getElementById('edit_price').value = this.dataset.price;
            document.getElementById('edit_features').value = JSON.parse(this.dataset.features).join('\n');
            
            M.updateTextFields();
            M.textareaAutoResize(document.getElementById('edit_features'));
        });
    });

    // Activate/Deactivate Plan
    document.querySelectorAll('.activate-plan, .deactivate-plan').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.classList.contains('activate-plan') ? 'activate' : 'deactivate';
            const name = this.dataset.name;
            
            if (!confirm(`Are you sure you want to ${action} the plan "${name}"?`)) {
                return;
            }
            
            fetch('ajax/plan_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    id: this.dataset.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: `Plan ${action}d successfully`});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    M.toast({html: data.message || `Failed to ${action} plan`, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'An error occurred', classes: 'red'});
            });
        });
    });

    // Delete Plan
    document.querySelectorAll('.delete-plan').forEach(button => {
        button.addEventListener('click', function() {
            const name = this.dataset.name;
            
            if (!confirm(`Are you sure you want to permanently delete the plan "${name}"?\nThis action cannot be undone!`)) {
                return;
            }
            
            fetch('ajax/plan_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: this.dataset.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    M.toast({html: 'Plan deleted successfully'});
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    M.toast({html: data.message || 'Failed to delete plan', classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'An error occurred', classes: 'red'});
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>