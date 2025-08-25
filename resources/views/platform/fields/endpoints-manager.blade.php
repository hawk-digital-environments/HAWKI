<!-- API Endpoints Manager -->
<div class="endpoints-manager" id="endpoints-manager">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Endpoints</h5>
                    <small class="text-muted">Define the available endpoints for this API format</small>
                </div>
                <div class="card-body">
                    <div id="endpoints-container">
                        @foreach($endpoints as $index => $endpoint)
                            <div class="endpoint-row row mb-3" data-index="{{ $index }}">
                                <div class="col-md-4">
                                    <label class="form-label">Endpoint Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="endpoints[{{ $index }}][name]" 
                                           value="{{ $endpoint['name'] ?? '' }}"
                                           placeholder="e.g., chat.create">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Path</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="endpoints[{{ $index }}][path]" 
                                           value="{{ $endpoint['path'] ?? '' }}"
                                           placeholder="e.g., /chat/completions">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">HTTP Method</label>
                                    <select class="form-control" name="endpoints[{{ $index }}][method]">
                                        <option value="GET" {{ ($endpoint['method'] ?? '') === 'GET' ? 'selected' : '' }}>GET</option>
                                        <option value="POST" {{ ($endpoint['method'] ?? '') === 'POST' ? 'selected' : '' }}>POST</option>
                                        <option value="PUT" {{ ($endpoint['method'] ?? '') === 'PUT' ? 'selected' : '' }}>PUT</option>
                                        <option value="DELETE" {{ ($endpoint['method'] ?? '') === 'DELETE' ? 'selected' : '' }}>DELETE</option>
                                        <option value="PATCH" {{ ($endpoint['method'] ?? '') === 'PATCH' ? 'selected' : '' }}>PATCH</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-endpoint" style="margin-top: 6px;">
                                        <i class="bs bs-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-outline-primary" id="add-endpoint">
                                <i class="bs bs-plus-lg"></i> Add Endpoint
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let endpointIndex = {{ count($endpoints) }};
    
    // Add new endpoint
    document.getElementById('add-endpoint').addEventListener('click', function() {
        const container = document.getElementById('endpoints-container');
        const newRow = document.createElement('div');
        newRow.className = 'endpoint-row row mb-3';
        newRow.setAttribute('data-index', endpointIndex);
        
        newRow.innerHTML = `
            <div class="col-md-4">
                <label class="form-label">Endpoint Name</label>
                <input type="text" 
                       class="form-control" 
                       name="endpoints[${endpointIndex}][name]" 
                       placeholder="e.g., chat.create">
            </div>
            <div class="col-md-4">
                <label class="form-label">Path</label>
                <input type="text" 
                       class="form-control" 
                       name="endpoints[${endpointIndex}][path]" 
                       placeholder="e.g., /chat/completions">
            </div>
            <div class="col-md-3">
                <label class="form-label">HTTP Method</label>
                <select class="form-control" name="endpoints[${endpointIndex}][method]">
                    <option value="GET">GET</option>
                    <option value="POST" selected>POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                    <option value="PATCH">PATCH</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-endpoint" style="margin-top: 6px;">
                    <i class="bs bs-trash3"></i>
                </button>
            </div>
        `;
        
        container.appendChild(newRow);
        endpointIndex++;
        
        // Add event listener to new remove button
        newRow.querySelector('.remove-endpoint').addEventListener('click', function() {
            newRow.remove();
        });
    });
    
    // Remove endpoint
    document.querySelectorAll('.remove-endpoint').forEach(function(button) {
        button.addEventListener('click', function() {
            // Don't remove if it's the last row
            const rows = document.querySelectorAll('.endpoint-row');
            if (rows.length > 1) {
                button.closest('.endpoint-row').remove();
            } else {
                // Clear the values instead of removing
                const row = button.closest('.endpoint-row');
                row.querySelectorAll('input').forEach(input => input.value = '');
                row.querySelector('select').value = 'POST';
            }
        });
    });
});
</script>
