/**
 * Guest Access Request Functions
 * Handles the guest registration form and validation
 */

// Utility function to hide all auth panels
function hideAllAuthPanels() {
    const panels = ['main-auth-panel', 'local-auth-panel', 'guest-request-panel'];
    panels.forEach(panelId => {
        const panel = document.getElementById(panelId);
        if (panel) {
            panel.style.display = 'none';
        }
    });
}

// Function to reset all auth forms and panels to a clean state
function resetAllAuthPanels() {
    hideAllAuthPanels();
    clearGuestRequestForm();
    
    // Clear any error messages in other panels
    const loginMessage = document.getElementById('login-message');
    if (loginMessage) {
        loginMessage.textContent = '';
    }
    
    const guestLoginMessage = document.getElementById('guest-login-message');
    if (guestLoginMessage) {
        guestLoginMessage.textContent = '';
    }
}

// Switch to guest request form
function switchToGuestRequestForm() {
    resetAllAuthPanels();
    // Show guest request panel
    document.getElementById('guest-request-panel').style.display = 'block';
}

// Switch back to local users login
function switchToLocalUsersLogin() {
    resetAllAuthPanels();
    // Show local auth panel
    document.getElementById('local-auth-panel').style.display = 'block';
    // Focus on username field
    const guestAccountField = document.getElementById('guest-account');
    if (guestAccountField) {
        guestAccountField.focus();
    }
}

// Clear the guest request form
function clearGuestRequestForm() {
    const form = document.getElementById('guestRequestForm');
    if (form) {
        form.reset();
    }
    clearGuestRequestErrors();
    
    const messageDiv = document.getElementById('guest-request-message');
    if (messageDiv) {
        messageDiv.innerHTML = '';
    }
    
    // Reset button state
    const submitButton = document.getElementById('submitGuestRequestButton');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.style.display = 'block'; // Ensure button is visible
        // Don't change the text - keep the localized text from Blade template
    }
}

// Clear all error messages
function clearGuestRequestErrors() {
    const errorElements = [
        'username-error',
        'password-error',
        'password-confirm-error',
        'email-error',
        'employeetype-error'
    ];
    
    errorElements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = '';
            element.style.display = 'none';
        }
    });
}

// Display error message for a field
function showGuestRequestError(fieldId, message) {
    const errorElement = document.getElementById(fieldId + '-error');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        errorElement.style.color = '#dc3545';
        errorElement.style.fontSize = '0.875rem';
        errorElement.style.marginTop = '0.25rem';
    }
}

// Validate the guest request form
function validateGuestRequestForm() {
    clearGuestRequestErrors();
    let isValid = true;
    
    // Get form values
    const username = document.getElementById('request-username').value.trim();
    const password = document.getElementById('request-password').value;
    const passwordConfirm = document.getElementById('request-password-confirm').value;
    const email = document.getElementById('request-email').value.trim();
    const employeetype = document.getElementById('request-employeetype').value;
    
    // Username validation
    if (!username) {
        showGuestRequestError('username', 'Username is required');
        isValid = false;
    } else if (username.length < 3) {
        showGuestRequestError('username', 'Username must be at least 3 characters long');
        isValid = false;
    } else if (!/^[a-zA-Z0-9_-]+$/.test(username)) {
        showGuestRequestError('username', 'Username can only contain letters, numbers, underscores, and hyphens');
        isValid = false;
    }
    
    // Password validation
    if (!password) {
        showGuestRequestError('password', 'Password is required');
        isValid = false;
    } else if (password.length < 8) {
        showGuestRequestError('password', 'Password must be at least 8 characters long');
        isValid = false;
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password)) {
        showGuestRequestError('password', 'Password must contain at least one uppercase letter, one lowercase letter, and one number');
        isValid = false;
    }
    
    // Password confirmation validation
    if (!passwordConfirm) {
        showGuestRequestError('password-confirm', 'Password confirmation is required');
        isValid = false;
    } else if (password !== passwordConfirm) {
        showGuestRequestError('password-confirm', 'Passwords do not match');
        isValid = false;
    }
    
    // Email validation
    if (!email) {
        showGuestRequestError('email', 'Email is required');
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showGuestRequestError('email', 'Please enter a valid email address');
        isValid = false;
    }
    
    // Employee type validation
    if (!employeetype) {
        showGuestRequestError('employeetype', 'User group is required');
        isValid = false;
    }
    
    return isValid;
}

// Submit the guest request
function submitGuestRequest() {
    if (!validateGuestRequestForm()) {
        return;
    }
    
    const submitButton = document.getElementById('submitGuestRequestButton');
    const messageDiv = document.getElementById('guest-request-message');
    
    // Hide button and show loading message
    submitButton.style.display = 'none';
    
    // Get localized texts from data attributes
    const submittingText = messageDiv.getAttribute('data-submitting') || 'Submitting...';
    const submittingMessage = messageDiv.getAttribute('data-submitting-text') || 'Please wait while we process your request.';
    
    messageDiv.innerHTML = `
        <div style="color: #0066cc; padding: 1rem; background-color: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 0.375rem; margin-bottom: 1rem;">
            <strong>${submittingText}</strong> ${submittingMessage}
        </div>
    `;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('_token', document.querySelector('input[name="_token"]').value);
    formData.append('username', document.getElementById('request-username').value.trim());
    formData.append('password', document.getElementById('request-password').value);
    formData.append('password_confirmation', document.getElementById('request-password-confirm').value);
    formData.append('email', document.getElementById('request-email').value.trim());
    formData.append('employeetype', document.getElementById('request-employeetype').value);
    
    // Submit the request
    fetch('/req/submit-guest-request', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Success message with localized text
            const successText = messageDiv.getAttribute('data-success') || 'Success!';
            const successMessage = messageDiv.getAttribute('data-success-message') || 'Your guest access request has been submitted successfully. You can now log in with your credentials.';
            
            messageDiv.innerHTML = `
                <div style="color: #28a745; padding: 1rem; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 0.375rem; margin-bottom: 1rem;">
                    <strong>${successText}</strong> ${data.message || successMessage}
                </div>
            `;
            
            // Auto-switch to login after 3 seconds (don't clear form yet)
            setTimeout(() => {
                clearGuestRequestForm();
                switchToLocalUsersLogin();
            }, 3000);
            
        } else {
            // Error handling - show button again on error
            submitButton.style.display = 'block';
            submitButton.disabled = false;
            
            if (data.errors) {
                // Display field-specific errors
                Object.keys(data.errors).forEach(field => {
                    if (data.errors[field] && data.errors[field].length > 0) {
                        showGuestRequestError(field.replace('_', '-'), data.errors[field][0]);
                    }
                });
            } else {
                // General error message with localized text
                const errorText = messageDiv.getAttribute('data-error') || 'Error:';
                const generalErrorMessage = messageDiv.getAttribute('data-general-error') || 'An error occurred while processing your request. Please try again.';
                
                messageDiv.innerHTML = `
                    <div style="color: #dc3545; padding: 1rem; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 0.375rem; margin-bottom: 1rem;">
                        <strong>${errorText}</strong> ${data.message || generalErrorMessage}
                    </div>
                `;
            }
        }
    })
    .catch(error => {
        console.error('Guest request submission error:', error);
        
        // Show button again on network error
        submitButton.style.display = 'block';
        submitButton.disabled = false;
        
        // Network error message with localized text
        const errorText = messageDiv.getAttribute('data-error') || 'Error:';
        const networkErrorMessage = messageDiv.getAttribute('data-network-error') || 'A network error occurred. Please check your connection and try again.';
        
        messageDiv.innerHTML = `
            <div style="color: #dc3545; padding: 1rem; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 0.375rem; margin-bottom: 1rem;">
                <strong>${errorText}</strong> ${networkErrorMessage}
            </div>
        `;
    });
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Real-time password validation
    const passwordField = document.getElementById('request-password');
    const passwordConfirmField = document.getElementById('request-password-confirm');
    
    if (passwordField && passwordConfirmField) {
        passwordConfirmField.addEventListener('input', function() {
            const password = passwordField.value;
            const passwordConfirm = this.value;
            
            if (passwordConfirm && password !== passwordConfirm) {
                showGuestRequestError('password-confirm', 'Passwords do not match');
            } else {
                document.getElementById('password-confirm-error').style.display = 'none';
            }
        });
    }
    
    // Enter key submission
    const form = document.getElementById('guestRequestForm');
    if (form) {
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitGuestRequest();
            }
        });
    }
});
