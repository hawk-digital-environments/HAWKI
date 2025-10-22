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
        // Debug: Log raw input
        \Log::debug('[StreamChunkHandler] Raw input', [
            'length' => strlen($data),
            'preview' => substr($data, 0, 200),
            'starts_with_data' => str_starts_with(trim($data), 'data: '),
            'starts_with_event' => str_starts_with(trim($data), 'event:')
        ]);
        
        // Normalize data to pure JSON newline format
        if (!str_starts_with(trim($data), 'data: ') && !str_starts_with(trim($data), 'event:')) {
            // Already JSON format (Google) - normalize to ensure complete objects
            $data = $this->normalizeDataChunk($data);
        } else {
            // SSE format (Anthropic) - extract and normalize to JSON
            $data = $this->normalizeSSEStreamChunk($data);
        }
        
        // Debug: Log normalized output
        \Log::debug('[StreamChunkHandler] Normalized output', [
            'length' => strlen($data),
            'preview' => substr($data, 0, 200),
            'line_count' => substr_count($data, "\n")
        ]);
        
        // At this point, $data contains pure JSON objects separated by newlines
        // Split on newlines and process each JSON object
        $lines = explode("\n", $data);
        
        foreach ($lines as $index => $line) {
            if (connection_aborted()) {
                break;
            }
            
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Filter out common stream end markers that are not JSON
            if ($line === '[DONE]' || $line === 'data: [DONE]') {
                \Log::debug('[StreamChunkHandler] Skipping stream end marker', [
                    'line_index' => $index,
                    'marker' => $line
                ]);
                continue;
            }
            
            if (!json_validate($line)) {
                \Log::warning('[StreamChunkHandler] Invalid JSON line', [
                    'line_index' => $index,
                    'line' => substr($line, 0, 100)
                ]);
                continue;
            }
            
            \Log::debug('[StreamChunkHandler] Calling onChunk with valid JSON', [
                'line_index' => $index,
                'preview' => substr($line, 0, 100)
            ]);
            
            ($this->onChunk)($line);
        }
    }
    
    /**
     * Normalize SSE stream chunks (handles Anthropic format with event: and data: lines)
     * Improved to handle very large objects split across packets
     * Returns pure JSON strings separated by newlines (no "data: " prefix)
     */
    private function normalizeSSEStreamChunk(string $data): string
    {
        // Add incoming data to buffer
        $this->jsonBuffer .= $data;
        
        // Handle special case: end of stream marker
        if (trim($this->jsonBuffer) === "]" || trim($this->jsonBuffer) === "data: ]") {
            $this->jsonBuffer = "";
            return "";
        }
        
        $output = "";
        $extractedCount = 0;
        $maxExtractions = 1000; // Increased for large response objects
        
        // Look for complete chunks in the buffer
        while ($extractedCount < $maxExtractions) {
            $extracted = null;
            
            // First try to find SSE-formatted chunks (data: {JSON})
            if (strpos($this->jsonBuffer, 'data: ') !== false) {
                $extracted = $this->extractSSEChunk();
            }
            
            // If no SSE chunk found, try to extract raw JSON (for continuation packets)
            if (!$extracted) {
                $extracted = $this->extractJsonObject($this->jsonBuffer);
            }
            
            if (!$extracted) {
                // Check if buffer is getting too large (>10MB) and might be stuck
                if (strlen($this->jsonBuffer) > 10 * 1024 * 1024) {
                    \Log::warning('SSE Stream: Buffer exceeds 10MB, potential incomplete packet. Clearing buffer.');
                    $this->jsonBuffer = "";
                }
                break; // No more complete chunks
            }
            
            $jsonStr = $extracted['jsonStr'];
            
            // Remove "data: " prefix if present and output only JSON
            if (str_starts_with($jsonStr, 'data: ')) {
                $jsonStr = substr($jsonStr, 6); // Remove "data: " (6 characters)
            }
            
            $output .= trim($jsonStr) . "\n";
            $extractedCount++;
        }
        
        // Safety check for infinite loops
        if ($extractedCount >= $maxExtractions) {
            \Log::error('SSE Stream: Maximum extraction limit reached, clearing buffer to prevent infinite loop');
            $this->jsonBuffer = "";
        }
        
        return $output;
    }
    
    /**
     * Extract SSE-formatted chunk (data: {JSON})
     * Handles event: lines by skipping them
     */
    private function extractSSEChunk(): ?array
    {
        $dataPos = strpos($this->jsonBuffer, 'data: ');
        if ($dataPos === false) {
            return null;
        }
        
        // Find the JSON part after "data: "
        $jsonStart = $dataPos + 6; // length of "data: "
        
        $possibleJson = substr($this->jsonBuffer, $jsonStart);
        
        // Try to find a complete JSON object using brace counting
        $jsonCopy = $possibleJson;
        $extracted = $this->extractJsonObject($jsonCopy);
        
        if ($extracted) {
            // We found a complete JSON object
            $jsonStr = $extracted['jsonStr'];
            $jsonLength = strlen($jsonStr);
            
            // Calculate the end position in the original buffer
            $jsonEnd = $jsonStart + $jsonLength;
            
            // Extract the complete SSE chunk including "data: " prefix
            $fullChunk = 'data: ' . $jsonStr;
            
            // Update buffer to remove processed chunk
            $nextDataPos = strpos($this->jsonBuffer, 'data: ', $jsonEnd);
            if ($nextDataPos !== false) {
                $this->jsonBuffer = substr($this->jsonBuffer, $nextDataPos);
            } else {
                $this->jsonBuffer = ltrim(substr($this->jsonBuffer, $jsonEnd), "\n\r ");
            }
            
            return [
                'jsonStr' => trim($fullChunk)
            ];
        }
        
        // Fallback: Look for newline-based chunks (for simple responses)
        $jsonEnd = strpos($this->jsonBuffer, "\n", $jsonStart);
        
        if ($jsonEnd === false) {
            // No newline found, check if we have a complete JSON object
            if (json_decode(trim($possibleJson), true) !== null) {
                // We have a complete JSON object without newline
                $jsonEnd = strlen($this->jsonBuffer);
            } else {
                return null; // Incomplete chunk
            }
        }
        
        // Extract the complete SSE chunk including "data: " prefix
        $fullChunk = substr($this->jsonBuffer, $dataPos, $jsonEnd - $dataPos);
        
        // Update buffer to remove processed chunk
        $this->jsonBuffer = ltrim(substr($this->jsonBuffer, $jsonEnd), "\n\r ");
        
        return [
            'jsonStr' => trim($fullChunk)
        ];
    }
    
    /**
     * Helper function to translate curl return object from google to pure JSON format
     * Returns JSON objects separated by newlines (no "data: " prefix)
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
            $output .= $jsonStr . "\n";
        }
        return $output;
    }
    
    /**
     * Helper function to extract complete JSON objects from buffer
     * Handles strings properly to avoid false brace detection inside JSON strings
     */
    private function extractJsonObject(string &$buffer): ?array
    {
        $buffer = trim($buffer);
        
        if (empty($buffer)) {
            return null;
        }
        
        // Find the start of a JSON object
        $start = strpos($buffer, '{');
        if ($start === false) {
            return null;
        }
        
        // Track brace depth to find the complete JSON object
        $depth = 0;
        $inString = false;
        $escapeNext = false;
        $length = strlen($buffer);
        $i = $start;
        
        $maxIterations = $length * 2; // Allow more iterations for large objects
        $iterations = 0;
        
        while ($i < $length && $iterations < $maxIterations) {
            $char = $buffer[$i];
            $iterations++;
            
            if ($escapeNext) {
                $escapeNext = false;
                $i++;
                continue;
            }
            
            if ($char === '\\') {
                $escapeNext = true;
                $i++;
                continue;
            }
            
            if ($char === '"') {
                $inString = !$inString;
                $i++;
                continue;
            }
            
            if (!$inString) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                    
                    // Found complete JSON object
                    if ($depth === 0) {
                        $end = $i + 1;
                        $jsonStr = substr($buffer, $start, $end - $start);
                        
                        // Verify that this is valid JSON before proceeding
                        $testDecode = json_decode($jsonStr, true, 512, JSON_INVALID_UTF8_IGNORE);
                        if ($testDecode === null && json_last_error() !== JSON_ERROR_NONE) {
                            // Invalid JSON, continue search
                            $i++;
                            continue;
                        }
                        
                        $rest = substr($buffer, $end);
                        
                        // Update the buffer reference
                        $buffer = trim($rest);
                        
                        return [
                            'jsonStr' => $jsonStr,
                            'rest' => $rest
                        ];
                    }
                }
            }
            
            $i++;
        }
        
        // Check if we hit the iteration limit
        if ($iterations >= $maxIterations) {
            \Log::warning('SSE Stream: Hit iteration limit while parsing JSON object, buffer length: ' . strlen($buffer));
        }
        
        // No complete JSON object found
        return null;
    }
}
