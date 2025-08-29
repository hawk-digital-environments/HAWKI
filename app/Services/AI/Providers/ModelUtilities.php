<?php


namespace App\Services\AI\Providers;

class ModelUtilities{

    protected $config;

    /**
     * Create a new provider instance
     *
     * @param array $config Provider configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function hasTool(string $modelId, string $tool): bool
    {
        $tools = $this->getModelDetails($modelId)['tools'];
        if(in_array($tool, $tools) && $tools[$tool] === true){
            return true;
        }
        else{
            return false;
        }
    }

    public function hasInput(string $modelId, string $input): bool{
        $inputs = $this->getModelDetails($modelId)['input'];
        if(in_array($input, $inputs)){
            return true;
        }
        else{
            return false;
        }
    }

    public function canProcessImage(string $modelId): bool
    {
        return $this->hasInput($modelId, 'image') && $this->hasTool($modelId, 'vision');
    }

    public function canProcessDocument(string $modelId): bool
    {
        return $this->hasTool($modelId, 'file_upload');
    }



    /**
     * Get details for a specific model
     *
     * @param string $modelId Model identifier
     * @return array Model details
     */
    public function getModelDetails(string $modelId): array
    {
        foreach ($this->config['models'] as $model) {
            if ($model['id'] === $modelId) {
                return $model;
            }
        }
        throw new \Exception("Unknown model ID: {$modelId}");
    }

}
