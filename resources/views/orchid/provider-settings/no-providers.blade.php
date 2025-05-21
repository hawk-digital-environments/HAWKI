<div class="bg-white rounded shadow-sm p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <i class="icon-info text-primary me-3" style="font-size: 2rem;"></i>
        <h3 class="mb-0">No API Providers Configured</h3>
    </div>
    
    <div class="mb-4">
        <p>
            No API Providers are configured yet for LLM models. 
            You can set them up in the following ways:
        </p>
        
        <ul>
            <li class="mb-2">
                <strong>Create a new provider</strong> using the "Add" button at the top right - 
                here you can manually configure a provider with its name, API schema, API key, and URLs.
            </li>
            <li class="mb-2">
                <strong>Import providers from the configuration file</strong> using the "Import from Config" button - 
                this will import all providers defined in the <code>model_providers.php</code> file into the database.
            </li>
        </ul>
    </div>
    
    <div class="mb-4">
        <h4>What are API Providers?</h4>
        <p>
            API Providers enable communication with various Large Language Models (LLMs) such as OpenAI GPT-4, 
            Anthropic Claude, Mistral AI, and others. For each provider, you can configure the following settings:
        </p>
        
        <ul>
            <li><strong>Provider Name:</strong> A unique name to identify the provider</li>
            <li><strong>API Schema:</strong> The interface schema used for communication with the provider</li>
            <li><strong>API Key:</strong> Your personal authentication key for the provider's API</li>
            <li><strong>API URL:</strong> The endpoint URL for requests to the provider's API</li>
            <li><strong>Models URL:</strong> The URL to retrieve the available models from the provider</li>
        </ul>
    </div>
    
    <div>
        <a href="{{ route('platform.modelsettings.provider.create') }}" class="btn btn-primary">
            <i class="icon-plus me-2"></i>Create new provider
        </a>
    </div>
</div>
