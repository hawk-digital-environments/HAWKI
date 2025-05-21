<div class="bg-white rounded shadow-sm p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <i class="icon-info text-primary me-3" style="font-size: 2rem;"></i>
        <h3 class="mb-0">No LLM Models Configured</h3>
    </div>
    
    <div class="mb-4">
        <p>
            No Large Language Models are currently configured in the database.
            You can import models from your configuration file:
        </p>
        
        <ul>
            <li class="mb-2">
                <strong>Import from Configuration:</strong> Click the "Import from Config" button above to import
                all models defined in the model_providers.php file.
            </li>
        </ul>
        
        <p>
            Before importing models, make sure you have properly configured your providers
            in the <a href="{{ route('platform.modelsettings.providers') }}">API Provider Settings</a> section.
        </p>
    </div>
    
    <div>
        <form method="POST" action="{{ route('platform.modelsettings.activemodels') }}">
            @csrf
            <button type="submit" name="_method" value="importModelsFromConfig" class="btn btn-primary">
                <i class="icon-cloud-download me-2"></i>Import Models from Config
            </button>
        </form>
    </div>
</div>
