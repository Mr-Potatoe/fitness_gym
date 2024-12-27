<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

$current_page = 'members';
$page_title = 'Manage Members';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filters
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$plan_filter = filter_input(INPUT_GET, 'plan', FILTER_SANITIZE_STRING);

// Build query conditions
$where_conditions = ["u.role = 'member'", "u.permanently_deleted = 0"];
$params = [];
$types = "";

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if ($status_filter) {
    if ($status_filter === 'active') {
        $where_conditions[] = "s.status = 'active' AND s.start_date <= CURDATE() AND s.end_date >= CURDATE()";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "(s.id IS NULL OR s.status != 'active' OR s.end_date < CURDATE())";
    } elseif ($status_filter === 'pending') {
        $where_conditions[] = "s.status = 'pending'";
    } elseif ($status_filter === 'expired') {
        $where_conditions[] = "s.status = 'active' AND s.end_date < CURDATE()";
    }
}

if ($plan_filter) {
    $where_conditions[] = "p.id = ?";
    $params[] = $plan_filter;
    $types .= "i";
}

// Base query
$base_query = "FROM users u 
               LEFT JOIN (
                   SELECT * FROM subscriptions 
                   WHERE (status = 'active' AND end_date >= CURDATE()) 
                   OR status = 'pending'
                   ORDER BY created_at DESC 
                   LIMIT 1
               ) s ON u.id = s.user_id 
               LEFT JOIN plans p ON s.plan_id = p.id
               LEFT JOIN payments py ON s.id = py.subscription_id
               WHERE " . implode(" AND ", $where_conditions);

// Count total records
$count_query = "SELECT COUNT(DISTINCT u.id) as total " . $base_query;
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get members
$query = "SELECT DISTINCT u.*, 
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
          (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id) as total_subscriptions " . 
          $base_query . 
          " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members = $stmt->get_result();

// Get all plans for filter dropdown
$plans_query = "SELECT id, name FROM plans WHERE deleted_at IS NULL ORDER BY name";
$plans = $conn->query($plans_query);
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
        .search-filters { margin-bottom: 20px; }
        .pagination-container { margin: 20px 0; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-inactive { background: #ffebee; color: #c62828; }
        .status-expired { background: #ff9800; color: #fff; }
    </style>
</head>
<body>
    <?php include '../includes/staff_nav.php'; ?>

    <main>
        <!-- Search and Filter Section -->
        <div class="row search-filters">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <form method="GET" class="row">
                            <div class="input-field col s12 m4">
                                <i class="material-icons prefix">search</i>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                <label for="search">Search members...</label>
                            </div>
                            <div class="input-field col s12 m3">
                                <select name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                </select>
                                <label>Status Filter</label>
                            </div>
                            <div class="input-field col s12 m3">
                                <select name="plan">
                                    <option value="">All Plans</option>
                                    <?php while ($plan = $plans->fetch_assoc()): ?>
                                        <option value="<?php echo $plan['id']; ?>" <?php echo $plan_filter == $plan['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($plan['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <label>Plan Filter</label>
                            </div>
                            <div class="input-field col s12 m2">
                                <button type="submit" class="btn waves-effect waves-light blue">
                                    Filter
                                    <i class="material-icons right">filter_list</i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Table -->
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <?php if ($total_records === 0): ?>
                            <p class="center-align">No members found matching your criteria.</p>
                        <?php else: ?>
                            <table class="striped responsive-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Plan</th>
                                        <th>Status</th>
                                        <th>Member Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($member = $members->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td><?php echo htmlspecialchars($member['plan_name'] ?? 'No Plan'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($member['subscription_status']); ?>">
                                                    <?php echo $member['subscription_status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($member['member_since'])); ?></td>
                                            <td>
                                                <a href="#!" class="btn-small waves-effect waves-light blue" 
                                                   onclick="viewMemberDetails(<?php echo $member['id']; ?>)">
                                                    <i class="material-icons">visibility</i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-container center-align">
                                    <ul class="pagination">
                                        <li class="<?php echo $page <= 1 ? 'disabled' : 'waves-effect'; ?>">
                                            <a href="<?php echo $page <= 1 ? '#!' : '?page='.($page-1).$search_query; ?>">
                                                <i class="material-icons">chevron_left</i>
                                            </a>
                                        </li>
                                        
                                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="<?php echo $page == $i ? 'active blue' : 'waves-effect'; ?>">
                                                <a href="?page=<?php echo $i.$search_query; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="<?php echo $page >= $total_pages ? 'disabled' : 'waves-effect'; ?>">
                                            <a href="<?php echo $page >= $total_pages ? '#!' : '?page='.($page+1).$search_query; ?>">
                                                <i class="material-icons">chevron_right</i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Member Details Modal -->
    <div id="member-details-modal" class="modal">
        <div class="modal-content">
            <h4>Member Details</h4>
            <div id="member-details-content">
                Loading...
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-blue btn-flat">Close</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
        });

        function viewMemberDetails(memberId) {
            const modal = M.Modal.getInstance(document.getElementById('member-details-modal'));
            modal.open();
            
            fetch(`ajax/get_member_details.php?id=${memberId}&csrf_token=<?php echo generateCSRFToken(); ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('member-details-content').innerHTML = data.html;
                        M.updateTextFields();
                    } else {
                        M.toast({html: data.message || 'Error loading member details', classes: 'red'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    M.toast({html: 'Error loading member details', classes: 'red'});
                });
        }
    </script>
</body>
</html>