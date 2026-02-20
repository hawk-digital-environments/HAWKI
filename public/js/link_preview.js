// Link Preview Functionality
// Handles hover preview for inline links

let previewPanel = null;
let previewTimeout = null;
let currentPreviewLink = null;
let previewCache = new Map();

// Initialize preview system
function initializeLinkPreview() {
    // Get or create preview panel
    const template = document.getElementById('link-preview-template');
    if (!template) {
        console.warn('link-preview-template not found');
        return;
    }

    const clone = template.content.cloneNode(true);
    previewPanel = clone.querySelector('.link-preview-panel');
    document.body.appendChild(previewPanel);

    // Add global event listeners
    document.addEventListener('mouseover', handleLinkHover, true);
    document.addEventListener('mouseout', handleLinkLeave, true);

    // Add panel-specific listeners
    previewPanel.addEventListener('mouseleave', () => {
        setTimeout(() => {
            checkAndHidePreview(currentPreviewLink);
        }, 200);
    });

    previewPanel.addEventListener('mouseenter', () => {
        // Keep the preview open when mouse is over it
        if (previewTimeout) {
            clearTimeout(previewTimeout);
        }
    });
}

// Handle mouse hover on inline links
function handleLinkHover(event) {
    const target = event.target.closest('.inline-link');
    if (!target) return;

    // Clear any existing timeout
    if (previewTimeout) {
        clearTimeout(previewTimeout);
    }

    // Set new timeout for 1 second
    previewTimeout = setTimeout(() => {
        showLinkPreview(target);
    }, 200);

    currentPreviewLink = target;
}

// Handle mouse leave
function handleLinkLeave(event) {
    const target = event.target.closest('.inline-link');
    if (!target) return;

    // Clear timeout if user moves away before 1 second
    if (previewTimeout) {
        clearTimeout(previewTimeout);
        previewTimeout = null;
    }

    // Hide preview after a short delay to allow moving to panel
    setTimeout(() => {
        checkAndHidePreview(target);
    }, 200);
}

// Check if mouse is over the preview panel or the link
function isMouseOverPanel() {
    if (!previewPanel) return false;
    return previewPanel.matches(':hover');
}

function isMouseOverLink() {
    if (!currentPreviewLink) return false;
    return currentPreviewLink.matches(':hover');
}

// Check if we should hide the preview
function checkAndHidePreview(linkElement) {
    if (!isMouseOverPanel() && !isMouseOverLink() && currentPreviewLink === linkElement) {
        hideLinkPreview();
    }
}

// Show link preview
async function showLinkPreview(linkElement) {
    if (!previewPanel) return;

    const url = linkElement.getAttribute('href');
    if (!url) return;

    // Position the panel
    positionPreviewPanel(linkElement);

    // Show loading state
    showPreviewLoading();

    // Check cache first
    if (previewCache.has(url)) {
        const cachedData = previewCache.get(url);
        if (cachedData.error) {
            showPreviewError();
        } else {
            showPreviewContent(cachedData);
        }
        return;
    }

    // Fetch preview data
    try {
        const metadata = await fetchLinkMetadata(url);
        previewCache.set(url, metadata);
        showPreviewContent(metadata);
    } catch (error) {
        console.error('Failed to fetch link preview:', error);
        previewCache.set(url, { error: true });
        showPreviewError();
    }
}

// Position preview panel relative to link
function positionPreviewPanel(linkElement) {
    if (!previewPanel) return;

    const linkRect = linkElement.getBoundingClientRect();
    const panelWidth = 400;
    const panelHeight = 400; // Estimated height, will adjust after content loads
    const padding = 10;

    let left = linkRect.left;
    let top;
    let position = 'bottom';

    // Check if there's enough space below
    const spaceBelow = window.innerHeight - linkRect.bottom;
    const spaceAbove = linkRect.top;

    if (spaceBelow >= panelHeight + padding) {
        // Position below the link
        top = linkRect.bottom + padding;
        position = 'bottom';
    } else if (spaceAbove >= panelHeight + padding) {
        // Position above the link - align bottom of panel with top of link
        top = linkRect.top - panelHeight - padding;
        position = 'top';
    } else {
        // Not enough space either way, prefer below
        top = linkRect.bottom + padding;
        position = 'bottom';
    }

    // Adjust horizontal position to keep panel in viewport
    if (left + panelWidth > window.innerWidth - padding) {
        left = window.innerWidth - panelWidth - padding;
    }
    if (left < padding) {
        left = padding;
    }

    // Adjust vertical position to keep panel in viewport
    if (top + panelHeight > window.innerHeight - padding) {
        top = window.innerHeight - panelHeight - padding;
    }
    if (top < padding) {
        top = padding;
    }

    // Apply position
    previewPanel.style.left = `${left}px`;
    previewPanel.style.top = `${top}px`;
    previewPanel.classList.remove('position-top', 'position-bottom');
    previewPanel.classList.add(`position-${position}`);

    // After panel is positioned, adjust based on actual height
    setTimeout(() => {
        const actualHeight = previewPanel.offsetHeight;
        if (position === 'top' && top > padding) {
            // Recalculate top position based on actual height
            const newTop = linkRect.top - actualHeight - padding;
            if (newTop >= padding) {
                previewPanel.style.top = `${newTop}px`;
            }
        }
    }, 50);
}

// Show loading state
function showPreviewLoading() {
    if (!previewPanel) return;

    previewPanel.querySelector('.preview-loading').style.display = 'flex';
    previewPanel.querySelector('.preview-content').style.display = 'none';
    previewPanel.querySelector('.preview-error').style.display = 'none';
    previewPanel.classList.add('visible');
}

// Show preview content
function showPreviewContent(metadata) {
    if (!previewPanel) return;

    const content = previewPanel.querySelector('.preview-content');

    // Set title
    const titleElement = content.querySelector('.preview-title');
    titleElement.textContent = metadata.title || metadata.domain || 'No title';

    // Set description
    const descElement = content.querySelector('.preview-description');
    descElement.textContent = metadata.description || 'No description available';

    // Set image
    const imgContainer = content.querySelector('.preview-image-container');
    const imgElement = content.querySelector('.preview-image');
    if (metadata.image) {
        imgElement.src = metadata.image;
        imgElement.style.display = 'block';
        imgContainer.style.display = 'block';

        imgContainer.addEventListener('click', () => {
            openLink();
        })

        // Handle image load error
        imgElement.onerror = () => {
            imgContainer.style.display = 'none';
        };
    } else {
        imgContainer.style.display = 'none';
    }

    // Set favicon and domain
    const faviconElement = content.querySelector('.preview-favicon');
    const domainElement = content.querySelector('.preview-domain');

    if (metadata.favicon) {
        faviconElement.src = metadata.favicon;
        faviconElement.style.display = 'block';
    } else {
        faviconElement.style.display = 'none';
    }

    domainElement.textContent = metadata.domain || new URL(metadata.url).hostname;
    domainElement.addEventListener('click', () => {
        openLink();
    })
    // Show content
    previewPanel.querySelector('.preview-loading').style.display = 'none';
    previewPanel.querySelector('.preview-error').style.display = 'none';
    content.style.display = 'flex';
    previewPanel.classList.add('visible');
}

// Show error state
function showPreviewError() {
    if (!previewPanel) return;

    previewPanel.querySelector('.preview-loading').style.display = 'none';
    previewPanel.querySelector('.preview-content').style.display = 'none';
    previewPanel.querySelector('.preview-error').style.display = 'flex';
    previewPanel.classList.add('visible');
}

//Open Link in new Tab
function openLink(){
    window.open(currentPreviewLink, '_blank').focus();
}

// Hide preview panel
function hideLinkPreview() {
    if (!previewPanel) return;

    previewPanel.classList.remove('visible');
    currentPreviewLink = null;
}

// Fetch link metadata from backend
async function fetchLinkMetadata(url) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const response = await fetch('/api/link-preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ url })
    });

    if (!response.ok) {
        throw new Error('Failed to fetch metadata');
    }

    return await response.json();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLinkPreview);
} else {
    initializeLinkPreview();
}
