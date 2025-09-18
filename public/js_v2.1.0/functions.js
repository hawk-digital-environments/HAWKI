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
    const tooltipParents = document.querySelectorAll(".tooltip-parent");
    tooltipParents.forEach(ttp => {
        let hoverTimer;
        const tt = ttp.querySelector('.tooltip');
        ttp.addEventListener("mouseenter", function () {
            hoverTimer = setTimeout(() => {
                tt.style.display = "flex"; // Show tooltip after 500ms
            }, 700);
        });
        ttp.addEventListener("mouseleave", function () {
            clearTimeout(hoverTimer); // Cancel the timer if the user leaves early
            tt.style.display = "none"; // Hide tooltip immediately
        });
    });
});