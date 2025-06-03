// Mobile detection and handling
document.addEventListener('DOMContentLoaded', function() {
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    
    if (isMobile) {
        document.body.classList.add('mobile-device');
        
        // Add mobile-specific classes to elements
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            table.parentElement.classList.add('table-responsive');
        });

        // Handle mobile navigation
        const navMenus = document.querySelectorAll('.nav-menu');
        navMenus.forEach(menu => {
            menu.classList.add('mobile-nav');
        });

        // Add touch event handling for better mobile interaction
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            button.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Handle form inputs for better mobile experience
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                // Scroll the input into view when focused
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    }
}); 