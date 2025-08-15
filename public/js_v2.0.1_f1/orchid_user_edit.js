/**
 * Orchid User Edit Form - Dynamic Field Display
 * Shows/hides fields based on authentication type selection
 */
document.addEventListener('DOMContentLoaded', function() {
    const authTypeSelect = document.querySelector('select[name="user[auth_type]"]');
    const employeeTypeField = document.querySelector('select[name="user[employeetype]"]');
    const resetPwField = document.querySelector('input[name="user[reset_pw]"]');
    
    if (!authTypeSelect) return;
    
    // Get the parent form groups for proper hiding/showing
    const employeeTypeGroup = employeeTypeField?.closest('.form-group');
    const resetPwGroup = resetPwField?.closest('.form-group');
    
    function toggleLocalUserFields(authType) {
        const isLocal = authType === 'local';
        
        // Show/hide employee type field
        if (employeeTypeGroup) {
            employeeTypeGroup.style.display = isLocal ? 'block' : 'none';
            if (employeeTypeField) {
                employeeTypeField.required = isLocal;
            }
        }
        
        // Show/hide reset password field
        if (resetPwGroup) {
            resetPwGroup.style.display = isLocal ? 'block' : 'none';
        }
    }
    
    // Initial state
    toggleLocalUserFields(authTypeSelect.value);
    
    // Listen for changes
    authTypeSelect.addEventListener('change', function() {
        toggleLocalUserFields(this.value);
    });
});
