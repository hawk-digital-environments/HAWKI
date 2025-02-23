<?php

namespace App\Services\AI;
use Illuminate\Support\Facades\Log;

class ModelUtilityService
{

    public function getModels()
    {
        // Decode JSON arrays from environment variables
        $providers = config('model_providers')['providers'];
        $models = [];
        foreach ($providers as $conn) {
            if($conn['active']){
                foreach($conn['models'] as $model){
                    $models[] = $model;
                }
            }
        }
        return [
            'models' => $models,
            'defaultModel' => config('model_providers')['defaultModel'],
            'titleGenerationModel' => config('model_providers')['titleGenerationModel']

        ];
    }


    /**
     * Determine the provider based on the model.
     *
     * @param string $model
     * @return string
     */
    public function getProviderId(string $model): string
    {
        $provider = $this->getProvider($model);
        return $provider['id'];
    }
    public function getProvider(string $model): array
    {
        $providers = config('model_providers')['providers'];

        foreach($providers as $provider){
            if($provider['active'])
            {
                $models = $this->getModelIdsByProvider($provider);
                if(in_array($model, $models)){
                    return $provider;
                }
            }
        }
        throw new \Exception("Unsupported model");
    }



    public function getModelIdsByProvider(array $provider)
    {
        // Decode JSON arrays from environment variables
        $models = [];
        if($provider['active']){
            foreach($provider['models'] as $model){
                $models[] = $model['id'];
            }
            return $models;
        }
        else{
            return null;
            // throw new \Exception("Trying to access inactive provider!" . json_encode($provider));
        }
    }


    public function pingGwdgModels(string $model){

        if($this->getProviderId($model) != 'gwdg'){
            throw new \Exception("Unsupported model");
        }

        $gwdg = config('model_providers')['providers']['gwdg'];
        $stats = $this->getModelsStatus($gwdg);
        $stats = json_decode($stats, true)['data'];  

        foreach($stats as $stat){
            if($stat['id'] === $model){
                return $stat['status'];
            }
        }
    }


    private function getModelsStatus(array $provider)
    {
        // Initialize a cURL session
        $ch = curl_init($provider['ping_url']);
        
        // Sample payload, replace with actual payload as needed
        $requestPayload = json_encode([
            // Add your required payload parameters here
        ]);

        // Configure cURL options for a POST request
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $provider['api_key'],
            'Content-Type: application/json'
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Handle errors
        if ($response === false) {
            // Log or handle error as necessary
            $error = 'Curl error: ' . curl_error($ch);
            curl_close($ch);
            return $error;
        }

        // Close the cURL session
        curl_close($ch);

        // Return the API response
        return $response;
    }
}