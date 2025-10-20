//#region Overlay

async function setOverlay(activation, smooth = true) {
    const overlay = document.getElementById('overlay');

    overlay.style.transition = `opacity ${smooth ? 500 : 0}ms`;

    if (activation) {
        overlay.style.visibility = 'visible'; // Make it visible first
        overlay.style.opacity = '1';          // Transition the opacity
        await new Promise(resolve => setTimeout(resolve, 1000));
    } else {
        overlay.style.opacity = '0';          // Fade out the opacity
        // Wait for the opacity transition to finish before hiding
        await new Promise(resolve => setTimeout(resolve, 1000));
        overlay.style.visibility = 'hidden';  // Now hide it after the fade-out
    }
}
//#endregion

async function logout(){
    await setOverlay(true, true);
    window.location.href = '/logout';
}

document.addEventListener("DOMContentLoaded", function () {
    // Function to initialize tooltip logic for a given tooltip-parent element
    function setupTooltip(ttp) {
        if (ttp.dataset.tooltipInit === "true") return;
        ttp.dataset.tooltipInit = "true";
        const tt = ttp.querySelector('.tooltip');
        if (!tt) {
            return;
        }
        tt.style.display = "none";
        let hoverTimer;

        ttp.addEventListener("mouseenter", function (e) {
            hoverTimer = setTimeout(() => {
                tt.style.display = "flex";
            }, 700);
        });

        ttp.addEventListener("mouseleave", function (e) {
            clearTimeout(hoverTimer);
            tt.style.display = "none";
        });
    }

    // Initialize existing tooltip parents
    document.querySelectorAll(".tooltip-parent").forEach(setupTooltip);

    // Watch for dynamically added tooltip-parent elements
    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;

                if (node.classList.contains("tooltip-parent")) {
                    setupTooltip(node);
                }
                node.querySelectorAll?.(".tooltip-parent").forEach(setupTooltip);
            }
        }
    });

    // Observe the entire body for added nodes
    observer.observe(document.body, { childList: true, subtree: true });
});
