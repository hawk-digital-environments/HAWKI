<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


class StreamChunkHandler
{
    private string $jsonBuffer = '';
    
    public function __construct(
        private readonly \Closure $onChunk
    )
    {
    }
    
    public function handle(string $data): void
    {
        // Check if data already has SSE format (OpenAI uses plain JSON, others use "data: " prefix)
        $hasDataPrefix = str_starts_with(trim($data), 'data: ');
        
        // If data is already valid JSON (OpenAI Responses API), process immediately without buffering
        if (!$hasDataPrefix && json_validate(trim($data))) {
            ($this->onChunk)($data);
            return;
        }
        
        // Otherwise, use buffer normalization for incomplete chunks
        if (!$hasDataPrefix) {
            $data = $this->normalizeDataChunk($data);
            
            // Log normalized/assembled data after buffer processing (complete JSON objects)
            if (config('logging.triggers.normalized_return_object')) {
                \Log::info('2. StreamChunkHandler - Assembled JSON', [
                    'data_size' => strlen($data),
                    'data_preview' => substr($data, 0, 300),
                    'note' => 'Incomplete JSON objects have been assembled via buffer'
                ]);
            }
        }
        
        foreach (explode("data: ", $data) as $chunk) {
            if (connection_aborted()) {
                break;
            }
            
            if (empty($chunk) || !json_validate($chunk)) {
                continue;
            }
            
            // Log formatted chunk before passing to provider-specific parsing
            if (config('logging.triggers.formatted_stream_chunk')) {
                \Log::info('2. StreamChunkHandler - Valid JSON Chunk', [
                    'chunk_size' => strlen($chunk),
                    'chunk_data' => json_decode($chunk, true)
                ]);
            }
            
            ($this->onChunk)($chunk);
        }
    }
    
    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        $this->jsonBuffer .= $data;
        
        if (trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }
        
        $output = "";
        while ($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $this->jsonBuffer = $extracted['rest'];
            $output .= "data: " . $jsonStr . "\n";
        }
        return $output;
    }
    
    private function extractJsonObject(string $buffer): ?array
    {
        $openBraces = 0;
        $startFound = false;
        $startPos = 0;
        
        $bufferLength = strlen($buffer);
        for ($i = 0; $i < $bufferLength; $i++) {
            $char = $buffer[$i];
            if ($char === '{') {
                if (!$startFound) {
                    $startFound = true;
                    $startPos = $i;
                }
                $openBraces++;
            } elseif ($char === '}') {
                $openBraces--;
                if ($openBraces === 0 && $startFound) {
                    $jsonStr = substr($buffer, $startPos, $i - $startPos + 1);
                    $rest = substr($buffer, $i + 1);
                    return ['jsonStr' => $jsonStr, 'rest' => $rest];
                }
            }
        }
        return null;
    }
    
    
}
