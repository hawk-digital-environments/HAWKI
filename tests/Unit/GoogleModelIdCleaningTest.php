<?php

namespace Tests\Unit;

use App\Orchid\Traits\AiConnectionTrait;
use Tests\TestCase;

class GoogleModelIdCleaningTest extends TestCase
{
    private $traitInstance;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create anonymous class that uses the trait
        $this->traitInstance = new class {
            use AiConnectionTrait;
            
            // Make protected method public for testing
            public function testCleanGoogleModelId(string $modelId): string
            {
                return $this->cleanGoogleModelId($modelId);
            }
        };
    }

    public function test_cleans_models_prefix_from_google_model_ids(): void
    {
        $testCases = [
            'models/embedding-gecko-001' => 'embedding-gecko-001',
            'models/gemini-pro' => 'gemini-pro',
            'models/gemini-pro-vision' => 'gemini-pro-vision',
            'models/text-bison-001' => 'text-bison-001',
            'models/chat-bison-001' => 'chat-bison-001',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->traitInstance->testCleanGoogleModelId($input);
            $this->assertEquals($expected, $result, "Failed to clean model ID: {$input}");
        }
    }

    public function test_leaves_non_google_model_ids_unchanged(): void
    {
        $testCases = [
            'gpt-4' => 'gpt-4',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo',
            'claude-3-sonnet' => 'claude-3-sonnet',
            'llama-2-70b' => 'llama-2-70b',
            'embedding-gecko-001' => 'embedding-gecko-001', // Already cleaned
            'some-random-model' => 'some-random-model',
            '' => '', // Empty string
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->traitInstance->testCleanGoogleModelId($input);
            $this->assertEquals($expected, $result, "Should not modify non-Google model ID: {$input}");
        }
    }

    public function test_handles_edge_cases(): void
    {
        $testCases = [
            'models/' => '', // Only prefix, no model name
            'models/models/nested' => 'models/nested', // Nested models prefix
            'MODELS/uppercase' => 'MODELS/uppercase', // Case sensitivity
            'models' => 'models', // No slash
            'some/models/in/path' => 'some/models/in/path', // Models in middle
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->traitInstance->testCleanGoogleModelId($input);
            $this->assertEquals($expected, $result, "Edge case failed for: {$input}");
        }
    }
}