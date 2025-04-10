document.addEventListener("DOMContentLoaded", function () {
    // Simple sidebar toggle functionality
    const menuToggle = document.getElementById('menu-toggle');
    const sidebarWrapper = document.getElementById('sidebar-wrapper');
    
    // Toggle sidebar when menu button is clicked
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebarWrapper.classList.toggle('toggled');
        });
    }

    // Close sidebar when clicking menu items on mobile
    const mediaQuery = window.matchMedia('(max-width: 992px)');
    if (mediaQuery.matches) {
        document.querySelectorAll('.sidebar .list-group-item').forEach(item => {
            item.addEventListener('click', function() {
                sidebarWrapper.classList.add('toggled');
            });
        });
    }
});
