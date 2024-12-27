<?php
require_once '../config/config.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('/login.php');
}

$current_page = 'announcements';
$page_title = 'Manage Announcements';

// Get all announcements
$query = "SELECT a.*, u.full_name as author 
          FROM announcements a 
          JOIN users u ON a.created_by = u.id 
          ORDER BY a.created_at DESC";
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
    <?php include '../includes/staff_nav.php'; ?>

    <main>
        <div class="row">
            <div class="col s12">
                <div class="card">
                    <div class="card-content">
                        <div class="row">
                            <div class="col s12">
                                <div class="right-align">
                                    <a href="#add-announcement-modal" class="btn-floating btn-large blue waves-effect waves-light modal-trigger">
                                        <i class="material-icons">add</i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <table class="striped responsive-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Content</th>
                                    <th>Author</th>
                                    <th>Date</th>
                                    <th class="center-align">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($announcement = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . '...'; ?></td>
                                            <td><?php echo htmlspecialchars($announcement['author']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                                            <td class="center-align">
                                                <div class="announcement-actions">
                                                    <?php if ($announcement['created_by'] == $_SESSION['user_id']): ?>
                                                        <button class="btn-small blue waves-effect waves-light" 
                                                                onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                            <i class="material-icons">edit</i>
                                                        </button>
                                                        <button class="btn-small red waves-effect waves-light" 
                                                                onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)">
                                                            <i class="material-icons">delete</i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-small blue waves-effect waves-light" 
                                                                onclick="viewAnnouncement(<?php echo $announcement['id']; ?>)">
                                                            <i class="material-icons">visibility</i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="center-align">No announcements found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Announcement Modal -->
    <div id="add-announcement-modal" class="modal">
        <div class="modal-content">
            <h4>Add Announcement</h4>
            <form id="add-announcement-form" method="POST">
                <div class="row">
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="add_title" name="title" required>
                            <label for="add_title">Title</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <textarea id="add_content" name="content" class="materialize-textarea" required></textarea>
                            <label for="add_content">Content</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
                    <button type="submit" class="waves-effect waves-green btn blue">Add Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div id="edit-announcement-modal" class="modal">
        <div class="modal-content">
            <h4>Edit Announcement</h4>
            <form id="edit-announcement-form">
                <input type="hidden" id="edit_announcement_id" name="id">
                <div class="row">
                    <div class="col s12">
                        <div class="input-field">
                            <input type="text" id="edit_title" name="title" required>
                            <label for="edit_title">Title</label>
                        </div>
                    </div>
                    <div class="col s12">
                        <div class="input-field">
                            <textarea id="edit_content" name="content" class="materialize-textarea" required></textarea>
                            <label for="edit_content">Content</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-close waves-effect waves-red btn-flat">Cancel</button>
                    <button type="submit" class="waves-effect waves-green btn blue">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Announcement Modal -->
    <div id="view-announcement-modal" class="modal">
        <div class="modal-content">
            <h4 id="view_title"></h4>
            <p id="view_content"></p>
            <p class="grey-text">
                Posted by <span id="view_author"></span> on <span id="view_date"></span>
            </p>
        </div>
        <div class="modal-footer">
            <button class="modal-close waves-effect waves-blue btn-flat">Close</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all Materialize components
            M.AutoInit();
            
            // Add Announcement Form Submit
            const addAnnouncementForm = document.getElementById('add-announcement-form');
            if (addAnnouncementForm) {
                addAnnouncementForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = {
                        action: 'add',
                        title: document.getElementById('add_title').value,
                        content: document.getElementById('add_content').value
                    };

                    fetch('ajax/announcement_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var modal = M.Modal.getInstance(document.getElementById('add-announcement-modal'));
                            modal.close();
                            M.toast({html: data.message, classes: 'green'});
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            M.toast({html: data.message, classes: 'red'});
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        M.toast({html: 'Error adding announcement', classes: 'red'});
                    });
                });
            }

            // Edit Announcement Form Submit
            const editAnnouncementForm = document.getElementById('edit-announcement-form');
            if (editAnnouncementForm) {
                editAnnouncementForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = {
                        action: 'edit',
                        id: document.getElementById('edit_announcement_id').value,
                        title: document.getElementById('edit_title').value,
                        content: document.getElementById('edit_content').value
                    };
                    
                    fetch('ajax/announcement_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var modal = M.Modal.getInstance(document.getElementById('edit-announcement-modal'));
                            modal.close();
                            M.toast({html: data.message, classes: 'green'});
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            M.toast({html: data.message, classes: 'red'});
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        M.toast({html: 'Error updating announcement', classes: 'red'});
                    });
                });
            }
        });

        function editAnnouncement(announcementId) {
            fetch('ajax/announcement_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get',
                    id: announcementId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const announcement = data.data;
                    document.getElementById('edit_announcement_id').value = announcement.id;
                    document.getElementById('edit_title').value = announcement.title;
                    document.getElementById('edit_content').value = announcement.content;
                    
                    // Reinitialize Materialize labels and textarea
                    M.updateTextFields();
                    M.textareaAutoResize(document.getElementById('edit_content'));
                    
                    // Open modal
                    const modal = M.Modal.getInstance(document.getElementById('edit-announcement-modal'));
                    modal.open();
                } else {
                    M.toast({html: data.message, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error getting announcement details', classes: 'red'});
            });
        }

        function deleteAnnouncement(announcementId) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                fetch('ajax/announcement_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: announcementId
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
                    M.toast({html: 'Error deleting announcement', classes: 'red'});
                });
            }
        }

        function viewAnnouncement(announcementId) {
            fetch('ajax/announcement_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get',
                    id: announcementId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const announcement = data.data;
                    document.getElementById('view_title').textContent = announcement.title;
                    document.getElementById('view_content').textContent = announcement.content;
                    document.getElementById('view_author').textContent = announcement.author;
                    document.getElementById('view_date').textContent = new Date(announcement.created_at).toLocaleDateString();
                    
                    const modal = M.Modal.getInstance(document.getElementById('view-announcement-modal'));
                    modal.open();
                } else {
                    M.toast({html: data.message, classes: 'red'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                M.toast({html: 'Error getting announcement details', classes: 'red'});
            });
        }
    </script>

    <style>
        .announcement-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        .btn-small {
            padding: 0 8px;
            height: 24px;
            line-height: 24px;
        }
        .btn-small i {
            font-size: 1.2rem;
            line-height: 24px;
        }
        .modal {
            max-width: 600px;
            border-radius: 8px;
        }
        .modal .modal-content {
            padding: 24px;
        }
        .modal h4 {
            margin-top: 0;
            margin-bottom: 24px;
            font-size: 1.8rem;
            color: #1565C0;
        }
        .modal .input-field {
            margin-bottom: 16px;
        }
        .modal .input-field input,
        .modal .input-field textarea {
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
</body>
</html> 