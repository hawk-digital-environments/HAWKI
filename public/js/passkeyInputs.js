function initializePasskeyInputs(applyCharacterLimitation = false){

    const inputWrappers = document.querySelectorAll('.password-input-wrapper');

    inputWrappers.forEach(wrapper => {
        const input = wrapper.querySelector('.passkey-input');
        const toggleBtn = wrapper.querySelector('.btn-xs');
        if (!input || !toggleBtn) return;
        
        input.dataset.visible = 'false'

        //random name will prevent chrome from auto filling.
        const rand = generateTempHash();
        input.setAttribute('name', rand);

        // Initialize the real value in a dataset
        input.dataset.realValue = '';

        if(applyCharacterLimitation){
            // Input filter for allowed characters
            input.addEventListener('beforeinput', function (event) {
                if (event.inputType.startsWith('insert')) {
                    if (!/^[A-Za-z0-9!@#$%^&*()_+-]+$/.test(event.data)) {
                        event.preventDefault();
                        showAllowedCharactersMessage();
                        input.parentElement.style.border = '1px solid red'

                        setTimeout(() => {
                            input.parentElement.style.border = 'var(--border-stroke-thin)';
                            console.log('back');
                        }, 200);
                    }
                }
            });

        }

        // Handle Enter key
        input.addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                checkPasskey();
            }
        });

        // Mask input and store real value
        input.addEventListener('beforeinput', function (e) {
            // current true value
            const real = input.dataset.realValue || '';
            // selection BEFORE the change
            const start = input.selectionStart ?? 0;
            const end = input.selectionEnd ?? start;

            let updated = real;
            let resultingCaret = start; // default

            // handle common insertion types
            if (e.inputType === 'insertText' || e.inputType === 'insertReplacementText') {
                // e.data is the typed character(s)
                const insert = e.data || '';
                updated = real.slice(0, start) + insert + real.slice(end);
                resultingCaret = start + insert.length;
            }
            // paste — treat similarly (you prevent paste elsewhere but keep support just in case)
            else if (e.inputType === 'insertFromPaste' || e.inputType === 'insertFromDrop') {
                const insert = e.data || '';
                updated = real.slice(0, start) + insert + real.slice(end);
                resultingCaret = start + (insert ? insert.length : 0);
            }
            // deletion/backspace when selection exists or without selection
            else if (e.inputType === 'deleteContentBackward') {
                if (start === end) {
                    // backspace deletes the character before caret
                    if (start > 0) {
                        updated = real.slice(0, start - 1) + real.slice(end);
                        resultingCaret = start - 1;
                    } else {
                        updated = real; resultingCaret = 0;
                    }
                } else {
                    // selection removed
                    updated = real.slice(0, start) + real.slice(end);
                    resultingCaret = start;
                }
            }
            else if (e.inputType === 'deleteContentForward') {
                if (start === end) {
                    // delete key deletes the character at caret
                    updated = real.slice(0, start) + real.slice(start + 1);
                    resultingCaret = start;
                } else {
                    // selection removed
                    updated = real.slice(0, start) + real.slice(end);
                    resultingCaret = start;
                }
            }
            // other input types (uncommon) — try to be safe and mirror selection replacement
            else {
                // treat as replacement of selection with e.data if present
                const insert = e.data || '';
                updated = real.slice(0, start) + insert + real.slice(end);
                resultingCaret = start + (insert ? insert.length : 0);
            }

            // store the computed real value and caret to use during 'input' masking
            input.dataset.realValue = updated;
            input._pendingCaret = resultingCaret;
            // allow browser to proceed — don't call e.preventDefault() here
        });

        // main input handler: keep masked view and restore caret
        input.addEventListener('input', function (e) {
            const realValue = input.dataset.realValue || '';
            const updatedLength = realValue.length;

            // if hidden, show masked stars and restore caret position
            if (input.dataset.visible === 'false') {
                input.value = '*'.repeat(updatedLength);

                // restore caret (clamp to length)
                const caret = Math.max(0, Math.min(updatedLength, Number(input._pendingCaret) || 0));
                try {
                    input.setSelectionRange(caret, caret);
                } catch (_) {
                    // some input types may not support selection setting; ignore
                }
            } else {
                // visible mode: show real value (useful when toggled visible)
                input.value = realValue;
                // when visible, try to set caret as well
                const caret = Math.max(0, Math.min(updatedLength, Number(input._pendingCaret) || 0));
                try {
                    input.setSelectionRange(caret, caret);
                } catch (_) {}
            }

            // clean up pending caret after processing
            delete input._pendingCaret;
        });


        // Prevent copy/cut/paste only for inputs without data-allow-paste attribute
        if (!input.hasAttribute('data-allow-paste')) {
            ['copy', 'cut', 'paste'].forEach(evt =>
                input.addEventListener(evt, e => e.preventDefault())
            );
        } else {
            // Special handling for paste in backup hash inputs
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                
                // Get pasted text from clipboard
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                
                // Clean up the pasted text (trim whitespace)
                const cleanedText = pastedText.trim();
                
                // Update the real value
                input.dataset.realValue = cleanedText;
                
                // Update display based on visibility state
                if (input.dataset.visible === 'true') {
                    input.value = cleanedText;
                } else {
                    input.value = '*'.repeat(cleanedText.length);
                }
            });
        }

        // Toggle visibility (unchanged, but will read dataset.realValue)
        toggleBtn.addEventListener('click', function () {
            const real = input.dataset.realValue || '';
            
            // Find the icon elements - they are direct children of the button container
            const iconChildren = Array.from(toggleBtn.children);
            
            if (iconChildren.length < 2) {
                console.warn('Toggle button should have at least 2 icon children');
                return;
            }
            
            const eye = iconChildren[0];
            const eyeOff = iconChildren[1];

            const isVisible = input.dataset.visible === 'true';
            if (!isVisible) {
                input.value = real;
                eye.style.display = 'none';
                eyeOff.style.display = 'inline-block';
                input.dataset.visible = 'true';
            }
            else {
                input.value = '*'.repeat(real.length);
                eye.style.display = 'inline-block';
                eyeOff.style.display = 'none';
                input.dataset.visible = 'false';
            }
        });
    });
}


// Helper function to get the real (unmasked) value of a passkey input
function getPasskeyRealValue(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return '';
    return input.dataset.realValue || input.value;
}


function showAllowedCharactersMessage(){
    const msg = document.querySelector('#alert-message');
    msg.innerText = translation.HS_Allowed_PK_Characters;
}
