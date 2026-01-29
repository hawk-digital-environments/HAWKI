document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const errorAlert = document.getElementById('app-alert-error');
    const waitingAlert = document.getElementById('app-alert-waiting');
    const confirmButton = document.getElementById('app-accept-button');
    const declineButton = document.getElementById('app-decline-button');

    if (!csrfToken) {
        throw new Error('CSRF token not found in meta tag.');
    }

    /**
     * Sends a POST request with the provided payload to the specified URL.
     * @param {string} url The URL to send the POST request to.
     * @param {object} payload The data to be sent in the POST request.
     * @returns {Promise<string>}
     */
    async function requestRedirectUrl(
        url,
        payload
    ) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        if (!data || typeof data !== 'object' || !data.success || !data.redirect_url) {
            return Promise.reject(new Error('Invalid response format'));
        }
        return data.redirect_url;
    }

    function showErrorAlert() {
        if (!errorAlert) {
            return () => void 0;
        }
        errorAlert.style.display = '';
        return function hideErrorAlert() {
            errorAlert.style.display = 'none';
        };
    }

    function showWaitingAlert() {
        if (!waitingAlert) {
            return () => void 0;
        }
        waitingAlert.style.display = '';
        if (confirmButton) {
            confirmButton.setAttribute('disabled', 'disabled');
        }
        if (declineButton) {
            declineButton.setAttribute('disabled', 'disabled');
        }
        return function hideWaitingAlert() {
            waitingAlert.style.display = 'none';
            if (confirmButton) {
                confirmButton.removeAttribute('disabled');
            }
            if (declineButton) {
                declineButton.removeAttribute('disabled');
            }
        };
    }

    (function initializeConfirmButton() {
        if (!confirmButton) {
            return;
        }

        const publicKey = confirmButton.getAttribute('data-user-public-key');
        if (!publicKey) {
            throw new Error('Public key not found in confirmButton data attribute.');
        }

        const confirmPostUrl = confirmButton.getAttribute('data-post-url');
        if (!confirmPostUrl) {
            throw new Error('Post URL not found in confirmButton data attribute.');
        }

        async function onConfirmButtonClick(e) {
            e.preventDefault();
            const hideWaitingAlert = showWaitingAlert();
            const passkey = await getPassKey();
            const passkeyEncrypted = await encryptWithHybrid(passkey, publicKey);
            try {
                window.location.href = await requestRedirectUrl(confirmPostUrl, {
                    passkey: passkeyEncrypted.toString()
                });
            } catch (e) {
                console.error('Error while requesting redirect url (confirm):', e);
                hideWaitingAlert();
                const closeErrorAlert = showErrorAlert();
                setTimeout(() => {
                    closeErrorAlert();
                }, 5000);
            }
        }

        confirmButton.addEventListener('click', onConfirmButtonClick);
    })();

    (function initializeDeclineButton() {
        if (!declineButton) {
            return;
        }

        const declinePostUrl = declineButton.getAttribute('data-post-url');
        if (!declinePostUrl) {
            throw new Error('Post URL not found in declineButton data attribute.');
        }

        async function onDeclineButtonClick(e) {
            e.preventDefault();
            const hideWaitingAlert = showWaitingAlert();
            try {
                window.location.href = await requestRedirectUrl(declinePostUrl, {});
            } catch (e) {
                console.error('Error while requesting redirect url (decline):', e);
                hideWaitingAlert();
                const closeErrorAlert = showErrorAlert();
                setTimeout(() => {
                    closeErrorAlert();
                }, 5000);
            }
        }

        declineButton.addEventListener('click', onDeclineButtonClick);
    })();
});
