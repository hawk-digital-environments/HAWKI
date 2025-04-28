<div class="bg-white rounded shadow-sm p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <i class="icon-info text-primary me-3" style="font-size: 2rem;"></i>
        <h3 class="mb-0">No Models for {{ $provider->provider_name }}</h3>
    </div>
    
    <div class="mb-4">
        <p>
            This provider doesn't have any models configured yet. You can:
        </p>
        
        <ul>
            <li class="mb-2">
                <strong>Fetch Models:</strong> Click the "Fetch Models" button below to check the provider's API for available models
            </li>
            <li class="mb-2">
                <strong>Import from Configuration:</strong> Use the "Import from Config" button at the top to import models from the configuration file
            </li>
        </ul>
    </div>
    
</div>
