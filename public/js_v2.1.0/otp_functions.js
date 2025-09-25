// OTP System Variables
let otpTimer = null;

// OTP Functions
// Initialize OTP input handlers when container is shown
function initializeOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    
    otpInputs.forEach((input, index) => {
        // Handle input events
        input.addEventListener('input', function(e) {
            const value = e.target.value;
            
            // Only allow numbers
            if (!/^\d$/.test(value)) {
                e.target.value = '';
                return;
            }
            
            // Add filled class
            e.target.classList.add('filled');
            
            // Move to next input
            if (value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }

            // Check if all inputs are filled
            checkOTPComplete();
        });
        
        // Handle backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (!e.target.value && index > 0) {
                    // Move to previous input if current is empty
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                } else if (e.target.value) {
                    // Clear current input
                    e.target.value = '';
                    e.target.classList.remove('filled');
                }
            }
        });
        
        // Handle paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/\D/g, '');
            
            if (pasteData.length === 6) {
                otpInputs.forEach((inp, idx) => {
                    if (idx < pasteData.length) {
                        inp.value = pasteData[idx];
                        inp.classList.add('filled');
                    }
                });
                checkOTPComplete();
            }
        });
        
        // Focus management
        input.addEventListener('focus', function() {
            this.select();
        });
    });
}

function getOTPValue() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    return Array.from(otpInputs).map(input => input.value).join('');
}

function clearOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    otpInputs.forEach(input => {
        input.value = '';
        input.classList.remove('filled', 'error');
    });
}

function setOTPError() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    otpInputs.forEach(input => {
        input.classList.add('error');
    });
    
    // Remove error class after animation
    setTimeout(() => {
        otpInputs.forEach(input => {
            input.classList.remove('error');
        });
    }, 500);
}

function checkOTPComplete() {
    const otp = getOTPValue();
    if (otp.length === 6) {
        // Auto-verify when all digits are entered
        setTimeout(() => {
            const verifyButton = document.getElementById('verify-otp-btn');
            if (verifyButton && !verifyButton.disabled) {
                verifyOTP(verifyButton);
            }
        }, 300);
    }
}

async function sendOTP(button = null) {
    if (!button) button = event.target;
    
    const originalText = button.textContent;
    
    try {
        button.textContent = translations["HS-LoginCodeB2"]; // 'Sende E-Mail...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                username: userInfo.username,
                email: userInfo.email
            })
        });
        
        // Check if response is actually JSON before parsing
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('Server returned non-JSON response:', textResponse);
            throw new Error('Server returned invalid response format');
        }
        
        const data = await response.json();
        
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB5"];
            console.log('OTP sent successfully:', data.message);
            
            // Hide send container and show input container
            document.getElementById('otp-send-container').style.display = 'none';
            document.getElementById('otp-input-container').style.display = 'block';
            
            // Set email display
            const emailDisplay = document.getElementById('otp-email-display');
            if (emailDisplay && userInfo.email) {
                emailDisplay.textContent = userInfo.email;
            }
            
            // Initialize OTP inputs
            initializeOTPInputs();
            
            // Focus first input
            const firstInput = document.querySelector('.otp-digit[data-index="0"]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Start single OTP timer (using config value)
            startOTPTimer();
            
            // Show timer element
            const timerElement = document.getElementById('otp-timer');
            if (timerElement) {
                timerElement.style.display = 'block';
            }
            
        } else {
            button.textContent = translations["HS-LoginCodeB6"];
            console.error('OTP sending failed:', data.error);
            showErrorMessage(data.error);
            
            // Re-enable button after error
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error sending OTP:', error);
        button.textContent = translations["HS-LoginCodeB6"];
        showErrorMessage(translations["HS-LoginCodeE2"]);
        
        // Re-enable button after error
        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = '';
            button.disabled = false;
        }, 3000);
    }
}

async function resendOTP(button = null) {
    if (!button) button = event.target;
    
    console.log('Resending OTP...');
    const originalText = button.textContent;
    
    // Hide resend container and show input elements again
    document.getElementById('resend-container').style.display = 'none';
    document.querySelector('.otp-input-group').style.display = 'flex';
    document.getElementById('verify-otp-btn').style.display = 'block';
    
    // Clear and re-enable OTP inputs
    const otpInputs = document.querySelectorAll('.otp-digit');
    const verifyButton = document.getElementById('verify-otp-btn');
    
    otpInputs.forEach(input => {
        input.disabled = false;
        input.value = '';
        input.classList.remove('filled', 'error');
    });
    
    if (verifyButton) verifyButton.disabled = false;
    
    // Reset timer display
    const timerElement = document.getElementById('otp-timer');
    if (timerElement) {
        timerElement.style.color = 'var(--accent-color)';
    }
    
    try {
        button.textContent = translations["HS-LoginCodeB2"]; // 'Sende E-Mail...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                username: userInfo.username,
                email: userInfo.email
            })
        });
        
        // Check if response is actually JSON before parsing
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('Server returned non-JSON response:', textResponse);
            throw new Error('Server returned invalid response format');
        }
        
        const data = await response.json();
        
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB5"];
            console.log('OTP resent successfully:', data.message);
            
            // Set email display (in case it was cleared)
            const emailDisplay = document.getElementById('otp-email-display');
            if (emailDisplay && userInfo.email) {
                emailDisplay.textContent = userInfo.email;
            }
            
            // Focus first input
            const firstInput = document.querySelector('.otp-digit[data-index="0"]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Start new OTP timer (using config value)
            startOTPTimer();
            
            // Show timer element
            const timerElement = document.getElementById('otp-timer');
            if (timerElement) {
                timerElement.style.display = 'block';
            }
            
            // Reset button after successful send
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 2000);
            
        } else {
            button.textContent = translations["HS-LoginCodeB6"];
            console.error('OTP resending failed:', data.error);
            showErrorMessage(data.error);
            
            // Show resend container again on error
            document.getElementById('resend-container').style.display = 'block';
            
            // Re-enable button after error
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error resending OTP:', error);
        button.textContent = translations["HS-LoginCodeB6"];
        showErrorMessage(translations["HS-LoginCodeE2"]);
        
        // Show resend container again on error
        document.getElementById('resend-container').style.display = 'block';
        
        // Re-enable button after error
        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = '';
            button.disabled = false;
        }, 3000);
    }
}

function startOTPTimer() {
    // Use config value as default timeout
    const seconds = otpTimeout || 300; // fallback to 5 minutes if config not available
    
    const timerElement = document.getElementById('otp-timer');
    if (!timerElement) {
        console.error('OTP Timer element not found!');
        return;
    }
    
    // Clear any existing timer first
    if (otpTimer) {
        console.log('Clearing existing OTP timer');
        clearInterval(otpTimer);
    }
    
    let remainingSeconds = seconds;
    
    const updateTimer = () => {
        const minutes = Math.floor(remainingSeconds / 60);
        const secondsDisplay = remainingSeconds % 60;
        const timeDisplay = `${minutes}:${secondsDisplay.toString().padStart(2, '0')}`;
        
        timerElement.textContent = timeDisplay;
        
        if (remainingSeconds <= 0) {
            console.log('OTP Timer expired - cleaning up');
            clearInterval(otpTimer);
            otpTimer = null;
            
            timerElement.textContent = translations["HS-LoginCodeT3"]; // 'Log-in Code abgelaufen'
            timerElement.style.color = '#dc3545';
            
            // Hide OTP input elements
            const otpInputGroup = document.querySelector('.otp-input-group');
            const verifyButton = document.getElementById('verify-otp-btn');
            const resendContainer = document.getElementById('resend-container');
            
            console.log('Hiding OTP input elements and showing resend container');
            
            if (otpInputGroup) {
                otpInputGroup.style.display = 'none';
            }
            
            if (verifyButton) {
                verifyButton.style.display = 'none';
            }
            
            if (resendContainer) {
                resendContainer.style.display = 'block';
            }
            
            showErrorMessage(translations["HS-LoginCodeE6"]);
            console.log('OTP Timer finished - all cleanup completed');
            return;
        }
        
        remainingSeconds--;
    };
    
    // Run immediately, then set interval
    updateTimer();
    
    otpTimer = setInterval(updateTimer, 1000);
    
    // Store timer reference for debugging
    window.currentOTPTimer = otpTimer;
    console.log('OTP Timer reference stored:', {
        timerId: otpTimer,
        windowTimer: window.currentOTPTimer,
        initialSeconds: seconds
    });
}

// Add function to manually clear OTP timer for debugging
function clearOTPTimer() {
    console.log('Manually clearing OTP timer');
    if (otpTimer) {
        clearInterval(otpTimer);
        otpTimer = null;
        window.currentOTPTimer = null;
        console.log('OTP timer cleared');
    } else {
        console.log('No OTP timer to clear');
    }
}

// Add function to check timer status for debugging
function checkOTPTimerStatus() {
    console.log('OTP Timer Status:', {
        otpTimer: otpTimer,
        windowCurrentOTPTimer: window.currentOTPTimer,
        timerElement: document.getElementById('otp-timer'),
        inputContainer: document.getElementById('otp-input-container'),
        otpInputGroup: document.querySelector('.otp-input-group'),
        verifyButton: document.getElementById('verify-otp-btn'),
        resendContainer: document.getElementById('resend-container')
    });
}

async function verifyOTP(button = null) {
    if (!button) button = event.target;
    const originalText = button.textContent;
    
    const otp = getOTPValue();
    
    if (!otp || otp.length !== 6) {
        showErrorMessage(translations["HS-LoginCodeE3"]);
        setOTPError();
        return;
    }
    
    try {
        button.textContent = translations["HS-LoginCodeB7"]; // 'Verifiziere Log-in Code...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/verify-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                otp: otp
            })
        });
        
        const data = await response.json();
        console.log(data);
        
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB8"];
            button.style.backgroundColor = '#28a745';
            console.log('OTP verified successfully:', data.message);
            
            // Clear any error messages
            const errorMessage = document.getElementById('alert-message');
            if (errorMessage) {
                errorMessage.textContent = '';
            }
            
            // Generate new passkey after successful OTP verification
            button.textContent = translations["HS-LoginCodeB9"]; // 'Logge ein...'
            console.log('Regenerating passkey after OTP verification...');
            
            try {
                await verifyGeneratedPassKey();
                console.log('Passkey verification successfully');
                
                // Redirect to chat after passkey generation
                setTimeout(() => {
                    window.location.href = '/chat';
                }, 1000);
                
            } catch (passkeyError) {
                console.error('Error generating passkey:', passkeyError);
                showErrorMessage(translations["HS-LoginCodeE4"]);
                button.textContent = 'Passkey-Fehler';
                button.style.backgroundColor = '#dc3545';
            }
            
        } else {
            button.textContent = translations["HS-LoginCodeB10"];
            button.style.backgroundColor = '#dc3545';
            console.error('OTP verification failed:', data.error);
            showErrorMessage(data.error);
            setOTPError();
        }
    } catch (error) {
        console.error('Error verifying OTP:', error);
        button.textContent = translations["HS-LoginCodeB11"];
        button.style.backgroundColor = '#dc3545';
        showErrorMessage(translations["HS-LoginCodeE5"]);
        setOTPError();
    }
    
    setTimeout(() => {
        button.textContent = originalText;
        button.style.backgroundColor = '';
        button.disabled = false;
    }, 3000);
}

function showErrorMessage(message) {
    const errorElement = document.getElementById('alert-message') || document.getElementById('backup-alert-message');
    if (errorElement) {
        errorElement.textContent = message;
        setTimeout(() => {
            errorElement.textContent = '';
        }, 5000);
    }
}

// Make debugging functions available globally
window.clearOTPTimer = clearOTPTimer;
window.checkOTPTimerStatus = checkOTPTimerStatus;
