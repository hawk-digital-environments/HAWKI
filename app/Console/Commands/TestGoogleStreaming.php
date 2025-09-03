<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestGoogleStreaming extends Command
{
    protected $signature = 'test:google-streaming';
    protected $description = 'Test Google streaming chunk processing with sample data';

    public function handle()
    {
        $this->info('Testing Google Streaming Chunk Processing...');
        
        // Create a test instance of StreamController with dependencies
        $usageAnalyzer = app(\App\Services\AI\UsageAnalyzerService::class);
        $aiConnectionService = app(\App\Services\AI\AIConnectionService::class);
        $controller = new StreamController($usageAnalyzer, $aiConnectionService);
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('normalizeGoogleStreamChunk');
        $method->setAccessible(true);
        
        // Test data that simulates what Google sends
        $testChunks = [
            // First chunk with partial data
            'data: {"candidates":[{"content":{"parts":[{"text":"# Laravel Middleware\n\nLaravel Middleware"}],"role":"model"}],"usageMetadata":{"promptTokenCount":25,"candidatesTokenCount":8},"groundingMetadata":{"',
            
            // Second chunk continuing the data
            'searchEntryPoint":{"renderedContent":"Laravel Middleware ist ein wichtiges Konzept..."},"segments":[{"partIndex":0,"startIndex":0,"endIndex":100}]}}]' . "\n\n",
            
            // Third chunk with more content
            'data: {"candidates":[{"content":{"parts":[{"text":" ist ein Konzept..."}],"role":"model"}],"usageMetadata":{"promptTokenCount":25,"candidatesTokenCount":20}}]' . "\n\n",
            
            // Final chunk
            'data: {"candidates":[{"content":{"parts":[{"text":""}],"role":"model"}],"finishReason":"STOP","usageMetadata":{"promptTokenCount":25,"candidatesTokenCount":150}}]' . "\n\n"
        ];
        
        $buffer = "";
        
        foreach($testChunks as $i => $chunk) {
            $this->info("\n--- Processing Chunk " . ($i + 1) . " ---");
            $this->line("Input: " . substr($chunk, 0, 100) . (strlen($chunk) > 100 ? '...' : ''));
            
            // Process the chunk
            $result = $method->invokeArgs($controller, [$chunk, &$buffer]);
            
            $this->line("Output: " . substr($result, 0, 100) . (strlen($result) > 100 ? '...' : ''));
            $this->line("Buffer remaining: " . strlen($buffer) . " chars");
            
            if (!empty($result)) {
                $this->info("✅ Chunk processed successfully");
            } else {
                $this->warn("⏳ Chunk buffered, waiting for more data");
            }
        }
        
        $this->info("\n🎉 Test completed!");
        return 0;
    }
}
