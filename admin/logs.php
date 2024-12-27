<?php
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/login.php');
}

$current_page = 'logs';
$page_title = 'Admin Logs';

// Get filter parameters
$filters = [];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if (!empty($_GET['admin_id'])) {
    $filters['admin_id'] = intval($_GET['admin_id']);
}
if (!empty($_GET['action_type'])) {
    $filters['action_type'] = $_GET['action_type'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get logs with filters
$logs = getAdminLogs($filters, $limit, $offset);

// Get all admins for filter dropdown
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique action types for filter dropdown
$stmt = $conn->prepare("SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type");
$stmt->execute();
$action_types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - VikingsFit Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .sidenav { width: 250px; }
        .sidenav li>a { display: flex; align-items: center; }
        .sidenav li>a>i { margin-right: 10px; }
        main { padding: 20px; margin-left: 250px; }
        @media only screen and (max-width: 992px) {
            main { margin-left: 0; }
        }
        .filter-section {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .log-entry {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-time {
            color: #757575;
            font-size: 0.9em;
        }
        .log-admin {
            font-weight: bold;
            color: #1565c0;
        }
        .log-type {
            background: #e3f2fd;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            color: #1565c0;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <h4><?php echo $page_title; ?></h4>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row">
                        <div class="input-field col s12 m3">
                            <select name="admin_id">
                                <option value="">All Admins</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>" <?php echo isset($filters['admin_id']) && $filters['admin_id'] == $admin['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Filter by Admin</label>
                        </div>
                        <div class="input-field col s12 m3">
                            <select name="action_type">
                                <option value="">All Actions</option>
                                <?php foreach ($action_types as $type): ?>
                                    <option value="<?php echo $type['action_type']; ?>" <?php echo isset($filters['action_type']) && $filters['action_type'] == $type['action_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['action_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Filter by Action</label>
                        </div>
                        <div class="input-field col s12 m2">
                            <input type="date" name="date_from" value="<?php echo $filters['date_from'] ?? ''; ?>">
                            <label>From Date</label>
                        </div>
                        <div class="input-field col s12 m2">
                            <input type="date" name="date_to" value="<?php echo $filters['date_to'] ?? ''; ?>">
                            <label>To Date</label>
                        </div>
                        <div class="input-field col s12 m2">
                            <button type="submit" class="btn waves-effect waves-light blue darken-3">
                                Filter
                                <i class="material-icons right">filter_list</i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Logs List -->
                <div class="card">
                    <div class="card-content">
                        <?php if (empty($logs)): ?>
                            <p class="center-align">No logs found.</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <div class="log-entry">
                                    <div class="log-time">
                                        <?php echo date('F j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <span class="log-admin"><?php echo htmlspecialchars($log['admin_username']); ?></span>
                                        <span class="log-type"><?php echo htmlspecialchars($log['action_type']); ?></span>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <?php echo htmlspecialchars($log['action_description']); ?>
                                    </div>
                                    <div class="grey-text" style="font-size: 0.9em; margin-top: 5px;">
                                        IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
        });
    </script>
</body>
</html>
