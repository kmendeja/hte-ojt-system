// Function to check trainee restrictions
async function checkTraineeRestrictions() {
    try {
        const response = await fetch('php/check_trainee_restrictions.php');
        const data = await response.json();
        
        if (data.success && data.restricted) {
            // Show restriction message
            Swal.fire({
                icon: 'warning',
                title: 'Access Restricted',
                text: data.restriction_message,
                confirmButtonColor: '#02602e'
            });
            
            // Disable all interactive elements
            disableInteractiveElements();
            
            // Redirect to requirements page if not already there
            if (!window.location.href.includes('trainee_requirements.html')) {
                window.location.href = 'trainee_requirements.html';
            }
            
            return true; // Restricted
        }
        
        return false; // Not restricted
    } catch (error) {
        console.error('Error checking restrictions:', error);
        return false;
    }
}

// Function to disable interactive elements
function disableInteractiveElements() {
    // Disable all buttons
    document.querySelectorAll('button').forEach(button => {
        button.disabled = true;
    });
    
    // Disable all input fields except in requirements page
    if (!window.location.href.includes('trainee_requirements.html')) {
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.disabled = true;
        });
    }
    
    // Disable all links except logout and requirements
    document.querySelectorAll('a').forEach(link => {
        if (!link.href.includes('logout.php') && !link.href.includes('trainee_requirements.html')) {
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.5';
        }
    });
}

// Function to enable interactive elements
function enableInteractiveElements() {
    // Enable all buttons
    document.querySelectorAll('button').forEach(button => {
        button.disabled = false;
    });
    
    // Enable all input fields
    document.querySelectorAll('input, select, textarea').forEach(input => {
        input.disabled = false;
    });
    
    // Enable all links
    document.querySelectorAll('a').forEach(link => {
        link.style.pointerEvents = 'auto';
        link.style.opacity = '1';
    });
}

// Check restrictions when page loads
document.addEventListener('DOMContentLoaded', async () => {
    // Don't check restrictions on the requirements page
    if (!window.location.href.includes('trainee_requirements.html')) {
        const isRestricted = await checkTraineeRestrictions();
        if (isRestricted) {
            disableInteractiveElements();
        } else {
            enableInteractiveElements();
        }
    }
});

// Add restriction check to all form submissions
document.addEventListener('submit', async (event) => {
    const isRestricted = await checkTraineeRestrictions();
    if (isRestricted) {
        event.preventDefault();
        return false;
    }
});

// Add restriction check to all button clicks
document.addEventListener('click', async (event) => {
    if (event.target.tagName === 'BUTTON' || event.target.tagName === 'A') {
        const isRestricted = await checkTraineeRestrictions();
        if (isRestricted) {
            event.preventDefault();
            return false;
        }
    }
}); 