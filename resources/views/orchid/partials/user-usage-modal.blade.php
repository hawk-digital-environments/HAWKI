<!-- Modal für User Provider Details (HTML5 Dialog) -->
<dialog id="userProviderModal" style="max-width: 800px; width: 90%; border: none; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 0; z-index: 1000;">
    <div style="padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px;">
            <h5 id="userProviderModalLabel" style="margin: 0; font-weight: 300;">Provider Details</h5>
            <button type="button" 
                    onclick="event.preventDefault(); document.getElementById('userProviderModal').close(); return false;" 
                    style="border: none; background: none; cursor: pointer; padding: 5px; line-height: 1; opacity: 0.7; transition: opacity 0.2s;"
                    onmouseover="this.style.opacity='1'" 
                    onmouseout="this.style.opacity='0.7'"
                    aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                </svg>
            </button>
        </div>
        <div id="userProviderModalBody">
            <div style="text-align: center; padding: 40px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</dialog>

<!-- Backdrop für Modal -->
<div id="userProviderModalBackdrop" onclick="document.getElementById('userProviderModal').close()" 
     style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999;"></div>

<script>
// Helper function to format tool use details
function formatToolUseDetails(toolUseDetails, totalCount) {
    // Check if we have any tool use data
    if (!totalCount || totalCount === 0 || !toolUseDetails || typeof toolUseDetails !== 'object' || Object.keys(toolUseDetails).length === 0) {
        return '—';
    }
    
    // Create main count display with "total:" prefix
    let html = `<small class="text-muted">total:</small> ${totalCount.toLocaleString()}<br>`;
    
    // Add tool breakdown
    html += '<small class="text-muted">';
    const toolEntries = Object.entries(toolUseDetails)
        .sort((a, b) => b[1] - a[1]) // Sort by count descending
        .slice(0, 3) // Show max 3 tools
        .map(([tool, count]) => `${tool}: ${count}`);
    
    if (toolEntries.length > 0) {
        html += toolEntries.join('<br>');
        
        // Show "and X more" if there are more tools
        const remainingTools = Object.keys(toolUseDetails).length - 3;
        if (remainingTools > 0) {
            html += `<br>+${remainingTools} more`;
        }
    }
    
    html += '</small>';
    
    return html;
}

// Warte bis DOM geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // Definiere Funktion im globalen Scope
    window.showUserProviderDetails = function(userId, userName, monthName) {
        
        const modal = document.getElementById('userProviderModal');
        const backdrop = document.getElementById('userProviderModalBackdrop');
        
        if (!modal) {
            console.error('Modal element not found in DOM');
            alert('Modal element not found. Please refresh the page.');
            return;
        }
        
        if (!backdrop) {
            console.error('Backdrop element not found in DOM');
            alert('Backdrop element not found. Please refresh the page.');
            return;
        }
        
        // Setze Titel und zeige Modal
        document.getElementById('userProviderModalLabel').textContent = userName + ' - ' + monthName;
        document.getElementById('userProviderModalBody').innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Zeige Modal und Backdrop
        backdrop.style.display = 'block';
        
        // Prüfe ob showModal unterstützt wird
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.style.display = 'block';
        }
        
        // Event Listener für ESC-Taste
        modal.addEventListener('close', function() {
            backdrop.style.display = 'none';
        });
        
        // Hole aktuelle Query-Parameter
        const urlParams = new URLSearchParams(window.location.search);
        const monthlyDate = urlParams.get('monthly_date') || '';
        
        // Lade Daten via AJAX
        fetch(`{{ route('platform.dashboard.requests.user-details') }}?user_id=${userId}&monthly_date=${monthlyDate}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div class="table-responsive"><table class="table">';
                    html += '<thead><tr>';
                    html += '<th>Provider / Model</th>';
                    html += '<th class="text-end">Requests</th>';
                    html += '<th class="text-end">Tokens<br><small class="text-muted">(Prompt / Completion)</small></th>';
                    html += '<th class="text-end">Tool Use</th>';
                    html += '</tr></thead><tbody>';
                    
                    if (data.providers.length === 0) {
                        html += '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
                    } else {
                        data.providers.forEach((provider, index) => {
                            // Provider Zeile (fett, mit Hintergrund)
                            const tokenPresenter = `${provider.total_tokens.toLocaleString()}<br><small class="text-muted">(${provider.prompt_tokens.toLocaleString()} / ${provider.completion_tokens.toLocaleString()})</small>`;
                            const toolUsePresenter = formatToolUseDetails(provider.tool_use_details, provider.tool_use_count);
                            
                            html += `<tr style="border-top: 1px solid;">`;
                            html += `<td><strong>${provider.api_provider}</strong></td>`;
                            html += `<td class="text-end">${provider.api_requests.toLocaleString()}</td>`;
                            html += `<td class="text-end">${tokenPresenter}</td>`;
                            html += `<td class="text-end">${toolUsePresenter}</td>`;
                            html += '</tr>';
                            
                            // Modelle für diesen Provider
                            if (provider.models && provider.models.length > 0) {
                                provider.models.forEach(model => {
                                    const modelTokenPresenter = `${model.total_tokens.toLocaleString()}<br><small class="text-muted">(${model.prompt_tokens.toLocaleString()} / ${model.completion_tokens.toLocaleString()})</small>`;
                                    const modelToolUsePresenter = formatToolUseDetails(model.tool_use_details, model.tool_use_count);
                                    
                                    html += '<tr>';
                                    html += `<td style="padding-left: 30px;" class="text-muted">${model.model}</td>`;
                                    html += `<td class="text-end text-muted">${model.api_requests.toLocaleString()}</td>`;
                                    html += `<td class="text-end text-muted">${modelTokenPresenter}</td>`;
                                    html += `<td class="text-end text-muted">${modelToolUsePresenter}</td>`;
                                    html += '</tr>';
                                });
                            }
                            
                            // Trennlinie nach jedem Provider (außer beim letzten)
                            if (index < data.providers.length - 1) {
                                html += '<tr><td colspan="4" style="padding: 0; border-top: 2px solid #dee2e6;"></td></tr>';
                            }
                        });
                    }
                    
                    html += '</tbody></table></div>';
                    document.getElementById('userProviderModalBody').innerHTML = html;
                } else {
                    document.getElementById('userProviderModalBody').innerHTML = 
                        '<div class="alert alert-danger">Error loading data: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('userProviderModalBody').innerHTML = 
                    '<div class="alert alert-danger">Error loading data. Please try again.</div>';
            });
    };
    
    // Event Listener für User Usage Trigger Links
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-user-usage="true"]');
        if (target) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = target.getAttribute('data-user-id');
            const userName = target.getAttribute('data-user-name');
            const month = target.getAttribute('data-month');
            
            if (userId && userName && month) {
                showUserProviderDetails(parseInt(userId), userName, month);
            } else {
                console.error('Missing data attributes', { userId, userName, month });
            }
            
            return false;
        }
    });
});

// Fallback: Registriere Funktion auch sofort für den Fall, dass DOM bereits geladen ist
if (document.readyState === 'loading') {
    // Waiting for DOM to load
} else {
    window.showUserProviderDetails = function(userId, userName, monthName) {
        
        const modal = document.getElementById('userProviderModal');
        const backdrop = document.getElementById('userProviderModalBackdrop');
        
        if (!modal || !backdrop) {
            console.error('Modal or backdrop not found');
            alert('Modal elements not found. Please refresh the page.');
            return;
        }
        
        document.getElementById('userProviderModalLabel').textContent = userName + ' - ' + monthName;
        document.getElementById('userProviderModalBody').innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        backdrop.style.display = 'block';
        
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.style.display = 'block';
        }
        
        modal.addEventListener('close', function() {
            backdrop.style.display = 'none';
        });
        
        const urlParams = new URLSearchParams(window.location.search);
        const monthlyDate = urlParams.get('monthly_date') || '';
        
        fetch(`{{ route('platform.dashboard.requests.user-details') }}?user_id=${userId}&monthly_date=${monthlyDate}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div class="table-responsive"><table class="table">';
                    html += '<thead><tr>';
                    html += '<th>Provider / Model</th>';
                    html += '<th class="text-end">Requests</th>';
                    html += '<th class="text-end">Tokens<br><small class="text-muted">(Prompt / Completion)</small></th>';
                    html += '<th class="text-end">Tool Use</th>';
                    html += '</tr></thead><tbody>';
                    
                    if (data.providers.length === 0) {
                        html += '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
                    } else {
                        data.providers.forEach((provider, index) => {
                            const tokenPresenter = `${provider.total_tokens.toLocaleString()}<br><small class="text-muted">(${provider.prompt_tokens.toLocaleString()} / ${provider.completion_tokens.toLocaleString()})</small>`;
                            
                            html += `<tr style="background-color: #f8f9fa; font-weight: 600;">`;
                            html += `<td><strong>${provider.api_provider}</strong></td>`;
                            html += `<td class="text-end">${provider.api_requests.toLocaleString()}</td>`;
                            html += `<td class="text-end">${tokenPresenter}</td>`;
                            html += `<td class="text-end">${provider.tool_use_count > 0 ? provider.tool_use_count.toLocaleString() : '—'}</td>`;
                            html += '</tr>';
                            
                            if (provider.models && provider.models.length > 0) {
                                provider.models.forEach(model => {
                                    const modelTokenPresenter = `${model.total_tokens.toLocaleString()}<br><small class="text-muted">(${model.prompt_tokens.toLocaleString()} / ${model.completion_tokens.toLocaleString()})</small>`;
                                    
                                    html += `<td style="padding-left: 30px;" class="text-muted">${model.model}</td>`;
                                    html += `<td class="text-end text-muted">${model.api_requests.toLocaleString()}</td>`;
                                    html += `<td class="text-end text-muted">${modelTokenPresenter}</td>`;
                                    html += `<td class="text-end text-muted">${model.tool_use_count > 0 ? model.tool_use_count.toLocaleString() : '—'}</td>`;
                                    html += '</tr>';
                                });
                            }
                            
                            if (index < data.providers.length - 1) {
                                html += '<tr><td colspan="4" style="padding: 0; border-top: 2px solid #dee2e6;"></td></tr>';
                            }
                        });
                    }
                    
                    html += '</tbody></table></div>';
                    document.getElementById('userProviderModalBody').innerHTML = html;
                } else {
                    document.getElementById('userProviderModalBody').innerHTML = 
                        '<div class="alert alert-danger">Error loading data: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('userProviderModalBody').innerHTML = 
                    '<div class="alert alert-danger">Error loading data. Please try again.</div>';
            });
    };
}
</script>
