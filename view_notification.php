<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'];

// Check if notification ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$notification_id = (int)$_GET['id'];
$notification = null;
$notificationType = '';
$referenceData = null;

// Fetch notification details
$stmt = $conn->prepare("
    SELECT n.*, 
           a.title as announcement_title, 
           a.content as announcement_content,
           a.created_at as announcement_date,
           u.name as announcement_creator
    FROM notifications n
    LEFT JOIN announcements a ON n.type = 'announcement' AND n.reference_id = a.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE n.id = ? AND n.user_id = ?
");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $notification = $result->fetch_assoc();
    $notificationType = $notification['type'];
    
    // Mark notification as read
    $updateStmt = $conn->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $notification_id);
    $updateStmt->execute();
    
    // Get specific data based on notification type
    if ($notificationType === 'announcement') {
        $referenceData = [
            'title' => $notification['announcement_title'],
            'content' => $notification['announcement_content'],
            'date' => $notification['announcement_date'],
            'creator' => $notification['announcement_creator']
        ];
    }
    // Add other notification types as needed
} else {
    // Notification not found or doesn't belong to user
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Details - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        .notification-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }
        .notification-dropdown {
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            border-left: 3px solid #0d6efd;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.1);
        }
        .notification-header {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand ms-2">Notification Details</span>
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="dropdown me-3 position-relative">
                            <a class="btn btn-outline-secondary position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" id="notification-count">
                                    0
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow notification-dropdown p-0" aria-labelledby="notificationDropdown">
                                <div class="notification-header d-flex justify-content-between align-items-center p-3 border-bottom">
                                    <h6 class="mb-0 fw-bold">Notifications</h6>
                                    <a href="#" class="text-decoration-none small mark-all-read">Mark all as read</a>
                                </div>
                                <div class="notifications-list p-0" id="notifications-container">
                                    <div class="text-center p-3 text-muted">
                                        <i class="fas fa-bell-slash me-2"></i> No notifications
                                    </div>
                                </div>
                                <div class="p-2 border-top text-center">
                                    <a href="view_all_notifications.php" class="text-decoration-none small">View all notifications</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <?php echo $user_name; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                                <li><a class="dropdown-item" href="modules/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <div class="card shadow-sm mb-4 notification-card">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <?php if ($notificationType === 'announcement'): ?>
                                        <i class="fas fa-bullhorn me-2"></i> Announcement
                                    <?php else: ?>
                                        <i class="fas fa-bell me-2"></i> Notification
                                    <?php endif; ?>
                                </h5>
                                <div>
                                    <a href="<?php echo $user_role === 'admin' ? 'dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                                    </a>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <?php if ($notificationType === 'announcement' && $referenceData): ?>
                                    <div class="mb-4">
                                        <h3><?php echo htmlspecialchars($referenceData['title']); ?></h3>
                                        <div class="text-muted small mb-3">
                                            <i class="far fa-calendar me-1"></i> <?php echo date('F j, Y', strtotime($referenceData['date'])); ?> 
                                            <i class="fas fa-user ms-2 me-1"></i> <?php echo htmlspecialchars($referenceData['creator']); ?>
                                        </div>
                                        <div class="mt-3 px-3 py-2 border-start border-3 border-primary bg-light rounded">
                                            <p class="mb-0" style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars($referenceData['content'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($user_role === 'admin'): ?>
                                        <div class="mt-4 text-end">
                                            <a href="manage_announcements.php?edit=<?php echo $notification['reference_id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit This Announcement
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                <?php elseif ($notificationType !== 'announcement'): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> This notification type is not fully detailed yet.
                                    </div>
                                    <p>Type: <?php echo htmlspecialchars($notification['type']); ?></p>
                                    <p>Date: <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?></p>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i> The announcement referenced by this notification may have been deleted.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    wrapper.classList.toggle('toggled');
                });
            }
            
            // Notifications functionality
            function fetchNotifications() {
                $.ajax({
                    url: 'api/get_notifications.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            updateNotificationUI(response.data);
                        } else {
                            console.error('Error fetching notifications:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            function updateNotificationUI(notifications) {
                const container = $('#notifications-container');
                const countBadge = $('#notification-count');
                const unreadCount = notifications.filter(n => n.is_read === '0').length;
                
                // Update badge count
                countBadge.text(unreadCount);
                if (unreadCount > 0) {
                    countBadge.removeClass('d-none');
                } else {
                    countBadge.addClass('d-none');
                }
                
                // Clear container
                container.empty();
                
                // If no notifications
                if (notifications.length === 0) {
                    container.html(`<div class="text-center p-3 text-muted">
                                      <i class="fas fa-bell-slash me-2"></i> No notifications
                                    </div>`);
                    return;
                }
                
                // Add notifications to container
                notifications.slice(0, 5).forEach(function(notification) {
                    const isUnread = notification.is_read === '0' ? 'unread' : '';
                    const notificationTime = new Date(notification.created_at);
                    const timeAgo = formatTimeAgo(notificationTime);
                    
                    container.append(`
                        <div class="notification-item p-3 border-bottom ${isUnread}" data-id="${notification.id}">
                            <div class="d-flex justify-content-between">
                                <strong>${notification.title}</strong>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                            <p class="mb-0 small text-truncate">${notification.type === 'announcement' ? 'New announcement' : notification.type}</p>
                            <div class="mt-1">
                                <a href="#" class="mark-read small text-primary" data-id="${notification.id}">
                                    ${notification.is_read === '0' ? 'Mark as read' : 'Mark as unread'}
                                </a>
                            </div>
                        </div>
                    `);
                });
            }
            
            function formatTimeAgo(date) {
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);
                
                if (diffInSeconds < 60) {
                    return 'Just now';
                }
                
                const diffInMinutes = Math.floor(diffInSeconds / 60);
                if (diffInMinutes < 60) {
                    return `${diffInMinutes} min${diffInMinutes > 1 ? 's' : ''} ago`;
                }
                
                const diffInHours = Math.floor(diffInMinutes / 60);
                if (diffInHours < 24) {
                    return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
                }
                
                const diffInDays = Math.floor(diffInHours / 24);
                if (diffInDays < 30) {
                    return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
                }
                
                const diffInMonths = Math.floor(diffInDays / 30);
                return `${diffInMonths} month${diffInMonths > 1 ? 's' : ''} ago`;
            }
            
            // Mark notification as read/unread
            $(document).on('click', '.mark-read', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const notificationId = $(this).data('id');
                const currentStatus = $(this).text().includes('Mark as read') ? 0 : 1;
                const newStatus = currentStatus === 0 ? 1 : 0;
                
                $.ajax({
                    url: 'api/update_notification.php',
                    type: 'POST',
                    data: {
                        notification_id: notificationId,
                        is_read: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            fetchNotifications();
                        } else {
                            console.error('Error updating notification:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            });
            
            // Mark all notifications as read
            $(document).on('click', '.mark-all-read', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'api/mark_all_read.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            fetchNotifications();
                        } else {
                            console.error('Error marking all as read:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            });
            
            // Click on notification to view details
            $(document).on('click', '.notification-item', function(e) {
                if (!$(e.target).hasClass('mark-read')) {
                    const notificationId = $(this).data('id');
                    window.location.href = 'view_notification.php?id=' + notificationId;
                }
            });
            
            // Initially fetch notifications
            fetchNotifications();
        });
    </script>
</body>
</html> 