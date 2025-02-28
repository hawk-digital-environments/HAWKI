<?php

namespace App\Services\AI;
use Illuminate\Support\Facades\Log;


use App\Services\AI\ModelUtilityService;



class ModelConnectionService
{
    public function __construct(ModelUtilityService $utilities){
        $this->utilities = $utilities;
    }

    public function streamToAiModel(array $payload, callable $onData)
    {
        set_time_limit(120); 
        // ob_start(); // Start output buffering

        // Set headers for SSE (Server-Sent Events)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Content-Type: text/html; charset=UTF-8');

        $provider = $this->utilities->getProvider($payload['model']);
        // Convert the payload to JSON
        $requestPayload = json_encode($payload);
    
        // Initialize a cURL session to make the request to OpenAI
        $ch = curl_init();

        // CURLOPT_PROXY
        // Needed for server deployment?
        // $apiProxy = isset($env) ? $env['HTTPPROXY'] : getenv('HTTPPROXY');
        // curl_setopt($ch, CURLOPT_PROXY, $apiProxy);

        // CURLOPT_TIMEOUT
        // Function: Sets the maximum execution time for the entire cURL request.
        // Effect: If the request takes longer than the specified time (in seconds), the request will be aborted.
        // Leads to 503 Service Unavailable: Operation timed out after CURLOPT_TIMEOUT
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);

        // CURLOPT_CONNECTTIMEOUT
        // Function: Sets the maximum time (in seconds) that cURL should spend attempting to connect to the server.
        // Effect: If the connection takes longer than the specified time, cURL will abort the request and return an error. This prevents the script from waiting unnecessarily long if the server is unreachable.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // CURLOPT_LOW_SPEED_LIMIT
        // Function: Defines the minimum data transfer speed (in bytes per second) that is considered acceptable.
        // Effect: If the transfer speed falls below this value, CURLOPT_LOW_SPEED_TIME is triggered.
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);

        // CURLOPT_LOW_SPEED_TIME
        // Function: Determines the time period (in seconds) during which the transfer speed must be below CURLOPT_LOW_SPEED_LIMIT to abort the request.
        // Effect: If the transfer speed remains below the defined limit for the specified duration, the request will be aborted.
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 20);

        curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $provider['api_key'],
            'Content-Type: application/json'
        ]);
    
        // Process each chunk of data as it is received
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($onData) {
            if (connection_aborted()) {
                return 0; // Return 0 to stop further processing
            }
            // Call the provided callback with the chunk of data
            $onData($data);
            // Flush the output buffer to ensure the client receives the data immediately

            if (ob_get_length()) {
                ob_flush();
            }
            flush();
            
            return strlen($data);
        });
    
        // Execute the cURL session and keep it open to stream data
        curl_exec($ch);
    
        // Handle errors
        if (curl_errno($ch)) {
            $onData('Error:' . curl_error($ch));  // Send the error to the callback
            ob_flush();
            flush();
        }
    
        // Close the cURL session when done
        curl_close($ch);
    
        // Explicitly flush any remaining data in the output buffer
        ob_flush(); 
        flush();
    }


    public function requestToAiModel(array $payload)
    {
        // Set headers for CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Content-Type: application/json; charset=UTF-8');

        $provider = $this->utilities->getProvider($payload['model']);
        // Convert the payload to JSON
        $requestPayload = json_encode($payload);

        // Initialize a cURL session to make a non-streaming request to OpenAI
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // We want the full response returned
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $provider['api_key'],
            'Content-Type: application/json'
        ]);

        // Execute the cURL session
        $response = curl_exec($ch);
        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error:' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }

        curl_close($ch);

        // Return the response
        return response($response)->header('Content-Type', 'application/json');
    }


    public function requestToGoogle(array $payload){
        // Set headers for SSE (Server-Sent Events)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Content-Type: text/html; charset=UTF-8');

        $provider = $this->utilities->getProvider($payload['model']);
    
        // Convert the payload to JSON
        $requestPayload = json_encode([
            'contents'=> $payload['contents']
        ]);

        // Ensure provider is correctly set up with the Google API key
        $url = $provider['api_url'] . $payload['model'] . ':generateContent?key=' . $provider['api_key'];

        // Initialize a cURL session to make the request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        
        // Set content type header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        // Execute the cURL session
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error:' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }

        curl_close($ch);
  
          // Return the response
          return response($response)->header('Content-Type', 'application/json');
    }

}