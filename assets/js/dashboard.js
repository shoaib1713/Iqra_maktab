// Dashboard.js
// This file will contain JavaScript functionality for the dashboard 

// Dashboard.js - Sidebar management
document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const sidebarWrapper = document.getElementById('sidebar-wrapper');
    const pageContentWrapper = document.getElementById('page-content-wrapper');
    const menuToggle = document.getElementById('menu-toggle');
    
    // Function to check window width and set sidebar state
    function handleWindowResize() {
        if (window.innerWidth < 992) {
            // Mobile view - sidebar should be hidden initially
            if (sidebarWrapper) {
                sidebarWrapper.classList.add('toggled');
            }
            if (pageContentWrapper) {
                pageContentWrapper.classList.add('expanded');
            }
        } else {
            // Desktop view - sidebar should be visible initially
            if (sidebarWrapper) {
                sidebarWrapper.classList.remove('toggled');
            }
            if (pageContentWrapper) {
                pageContentWrapper.classList.remove('expanded');
            }
        }
    }
    
    // Run on page load
    handleWindowResize();
    
    // Handle toggle button click
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (sidebarWrapper) {
                sidebarWrapper.classList.toggle('toggled');
            }
            if (pageContentWrapper) {
                pageContentWrapper.classList.toggle('expanded');
            }
        });
    }
    
    // Update on window resize
    window.addEventListener('resize', handleWindowResize);
}); 