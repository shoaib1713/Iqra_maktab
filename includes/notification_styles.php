<style>
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