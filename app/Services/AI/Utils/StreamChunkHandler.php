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
        if (!str_starts_with(trim($data), 'data: ')) {
            $data = $this->normalizeDataChunk($data);
        }
        
        foreach (explode("data: ", $data) as $chunk) {
            if (connection_aborted()) {
                break;
            }
            
            if (empty($chunk) || !json_validate($chunk)) {
                continue;
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
