<?php
// Notification Scripts - Properly formatted as PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<script>
// Helper function to get the correct API path based on current page location
function getApiPath(endpoint) {
    const currentPath = window.location.pathname;
    // If we're in a subdirectory (path contains multiple /)
    if ((currentPath.match(/\//g) || []).length > 1) {
        return '../api/' + endpoint;
    }
    return 'api/' + endpoint;
}

// Notifications functionality
function fetchNotifications() {
    $.ajax({
        url: getApiPath('get_notifications.php'),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateNotificationUI(response.data, response.unread_count);
            } else {
                console.error('Error fetching notifications:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

function updateNotificationUI(notifications, unreadCount) {
    const container = $('#notifications-container');
    const countBadge = $('#notification-count');
    
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
                <p class="mb-0 small text-truncate">${notification.content ? notification.content.substring(0, 60) + '...' : (notification.type === 'announcement' ? 'New announcement' : notification.type)}</p>
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

// Get the notification page path based on current location
function getNotificationPagePath(notificationId) {
    const currentPath = window.location.pathname;
    const pathParts = currentPath.split('/');
    
    // If we're in a subdirectory (more than 2 parts after splitting)
    if (pathParts.length > 2) {
        return '../view_notification.php?id=' + notificationId;
    }
    return 'view_notification.php?id=' + notificationId;
}

// Get the path to view all notifications
function getViewAllNotificationsPath() {
    const currentPath = window.location.pathname;
    const pathParts = currentPath.split('/');
    
    // If we're in a subdirectory (more than 2 parts after splitting)
    if (pathParts.length > 2) {
        return '../view_all_notifications.php';
    }
    return 'view_all_notifications.php';
}

// Mark notification as read/unread
$(document).on('click', '.mark-read', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const notificationId = $(this).data('id');
    const currentStatus = $(this).text().includes('Mark as read') ? 0 : 1;
    const newStatus = currentStatus === 0 ? 1 : 0;
    
    $.ajax({
        url: getApiPath('update_notification.php'),
        type: 'POST',
        data: {
            notification_id: notificationId,
            is_read: newStatus
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                fetchNotifications();
                
                // If we're on the all notifications page, also update the UI there
                const $notificationItem = $('.notification-item[data-id="' + notificationId + '"]');
                if ($notificationItem.length > 1) { // If found in both dropdown and main list
                    // Get the one in the main list (not in dropdown)
                    const $mainListItem = $notificationItem.not($('#notifications-container').find('.notification-item'));
                    
                    if ($mainListItem.length) {
                        const $markReadLink = $mainListItem.find('.mark-read');
                        
                        if (newStatus === 1) {
                            $mainListItem.removeClass('unread');
                            $markReadLink.html('<i class="fas fa-envelope me-1"></i> Mark as unread');
                            $mainListItem.find('.badge').removeClass('bg-danger').addClass('bg-secondary').text('Read');
                        } else {
                            $mainListItem.addClass('unread');
                            $markReadLink.html('<i class="fas fa-envelope-open me-1"></i> Mark as read');
                            $mainListItem.find('.badge').removeClass('bg-secondary').addClass('bg-danger').text('Unread');
                        }
                    }
                }
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
        url: getApiPath('mark_all_read.php'),
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                fetchNotifications();
                
                // If we're on the all notifications page, refresh it
                if ($('#notifications-list').length) {
                    window.location.reload();
                }
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
        window.location.href = getNotificationPagePath(notificationId);
    }
});

// Initially fetch notifications
$(document).ready(function() {
    fetchNotifications();
    
    // Check for new notifications every 10 seconds
    setInterval(fetchNotifications, 10000);
    
    // Update View All Notifications link
    $('.view-all-notifications-link').attr('href', getViewAllNotificationsPath());
    
    // Toggle notifications dropdown
    $(document).on('click', '#notification-bell', function(e) {
        e.preventDefault();
        $('#notifications-dropdown').toggleClass('show');
    });
    
    // Close notification dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#notification-bell, #notifications-dropdown').length) {
            $('#notifications-dropdown').removeClass('show');
        }
    });
});
</script> 