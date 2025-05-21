<div class="bg-white rounded shadow-sm p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <i class="icon-info text-primary me-3" style="font-size: 2rem;"></i>
        <h3 class="mb-0">No API Providers Configured</h3>
    </div>
    
    <div class="mb-4">
        <p>
            No API Providers are currently configured in the system. Models need to be associated with a provider.
        </p>
        
        <p>
            Please first configure providers in the 
            <a href="{{ route('platform.modelsettings.providers') }}">API Provider Settings</a> section 
            before managing models.
        </p>
    </div>
    
    <div>
        <a href="{{ route('platform.modelsettings.providers') }}" class="btn btn-primary">
            <i class="icon-settings me-2"></i>Go to Provider Settings
        </a>
    </div>
</div>
