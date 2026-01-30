<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OpenAiStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;
    
    // State management for reasoning blocks (similar to Responses API)
    private array $reasoningBlocks = []; // [output_index => ['content' => '', 'sent' => false, 'title' => '']]
    private int $currentOutputIndex = 0; // Track current output (always 0 for Chat Completions)
    private bool $processingStatusSent = false; // Track if initial processing status was sent
    private array $statusLog = []; // Collect all status updates for persistence (like Responses API)
    private bool $reasoningEnabled = false; // Track if reasoning was explicitly requested
    
    public function __construct(
        private array    $payload,
        private \Closure $onData
    )
    {
        // Check if reasoning was explicitly enabled in the payload
        // This is used to filter out unwanted reasoning content from vLLM OSS models
        $this->reasoningEnabled = isset($payload['reasoning']);
    }
    
    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;
        $this->payload['stream_options'] = [
            'include_usage' => true,
        ];
        
        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }
    
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        
        if (isset($jsonChunk['error'])) {
            return $this->createErrorResponse($jsonChunk['error']['message'] ?? 'Unknown error');
        }
        
        $content = '';
        $isDone = false;
        $usage = null;
        $auxiliaries = [];
        
        // When finish_reason is present, send final "processing completed" status
        // This chunk comes AFTER all content chunks - perfect timing for finalization
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
            
            // Collect final status for persistence
            $this->addStatusToLog('processing', 'completed', null, $this->currentOutputIndex);
            
            // Include collected status log for persistence (like Responses API)
            if (!empty($this->statusLog)) {
                // Update reasoning steps with titles if available
                foreach ($this->statusLog as &$entry) {
                    if ($entry['type'] === 'reasoning' && $entry['status'] === 'completed') {
                        $outputIndex = $entry['output_index'] ?? null;
                        $block = $this->reasoningBlocks[$outputIndex] ?? null;
                        
                        // Add title if available
                        if ($block && isset($block['title'])) {
                            $entry['message'] = $block['title'];
                        }
                        
                        // Add summary content if available
                        if ($block && !empty($block['content'])) {
                            // Remove title from content if present
                            $summaryText = $block['content'];
                            if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                                $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                                $summaryText = trim($summaryText);
                            }
                            $entry['summary'] = $summaryText;
                        }
                    }
                }
                unset($entry);
                
                $auxiliaries[] = [
                    'type' => 'status_log',
                    'content' => json_encode([
                        'log' => $this->statusLog
                    ])
                ];
            }
            
            // NOTE: We DON'T send "processing completed" as separate status auxiliary
            // It's already in the status_log and would be overwritten when status_log is processed
        }
        
        // Extract usage data when available
        // OpenAI: sends usage in final chunk with finish_reason when stream_options.include_usage is true
        // LiteLLM: sends finish_reason in one chunk, then usage in a separate subsequent chunk
        // To support both, we extract usage whenever it's present, regardless of finish_reason
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($model, $jsonChunk);
            
            // NOTE: We DON'T send "processing_completed" status here
            // The frontend will automatically add it when isDone=true
            // This prevents the status from appearing before content rendering is complete
            
            if (config('logging.triggers.usage') && $usage) {
                \Log::info('Token Usage - OpenAI Compatible Provider', [
                    'model' => $model->getId(),
                    'prompt_tokens' => $jsonChunk['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $jsonChunk['usage']['completion_tokens'] ?? 0,
                    'total_tokens' => $jsonChunk['usage']['total_tokens'] ?? 0,
                    'reasoning_tokens' => $jsonChunk['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0,
                    'finish_reason' => $jsonChunk['choices'][0]['finish_reason'] ?? 'not_in_chunk',
                    'has_finish_reason' => isset($jsonChunk['choices'][0]['finish_reason']),
                    'note' => 'Usage extracted when present (supports OpenAI and LiteLLM behavior)'
                ]);
            }
        }
        
        // Handle reasoning_content from GPT-OSS and o1 models
        $delta = $jsonChunk['choices'][0]['delta'] ?? [];
        
        // Send initial processing status on FIRST chunk (with role or any delta content)
        // This ensures "Anfrage gesendet" appears immediately
        if (!$this->processingStatusSent && (!empty($delta['role']) || !empty($delta['reasoning_content']) || !empty($delta['reasoning']) || !empty($delta['content']))) {
            $this->addStatusToLog('processing', 'in_progress', null, $this->currentOutputIndex);
            
            $auxiliaries[] = [
                'type' => 'status',
                'content' => json_encode([
                    'status' => 'in_progress',
                    'type' => 'processing', // Explicit type for disambiguation
                    'output_index' => $this->currentOutputIndex
                ])
            ];
            $this->processingStatusSent = true;
        }
        
        // Check for reasoning_content in delta
        // HOTFIX: vLLM OSS models always send reasoning_content, even when not requested
        // Only process reasoning if it was explicitly enabled in the payload
        if ($this->reasoningEnabled && (isset($delta['reasoning_content']) || isset($delta['reasoning']))) {
            $reasoningDelta = $delta['reasoning_content'] ?? $delta['reasoning'] ?? '';
            
            // Initialize reasoning block for this output if not exists
            if (!isset($this->reasoningBlocks[$this->currentOutputIndex])) {
                $this->reasoningBlocks[$this->currentOutputIndex] = [
                    'content' => '',
                    'sent' => false
                ];
            }
            
            // First REAL reasoning chunk (non-empty) - send "reasoning started" status
            if (empty($this->reasoningBlocks[$this->currentOutputIndex]['content']) && !empty($reasoningDelta)) {
                $this->addStatusToLog('reasoning', 'in_progress', null, $this->currentOutputIndex);
                
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'reasoning',
                        'type' => 'reasoning', // Explicit type
                        'output_index' => $this->currentOutputIndex
                    ])
                ];
            }
            
            // Accumulate reasoning content for this output
            $this->reasoningBlocks[$this->currentOutputIndex]['content'] .= $reasoningDelta;
        }
        
        // Extract regular content if available
        if (isset($delta['content'])) {
            $content = $delta['content'];
            
            // If we have accumulated reasoning for this output and haven't sent it yet
            $block = $this->reasoningBlocks[$this->currentOutputIndex] ?? null;
            if ($block && !empty($block['content']) && !$block['sent']) {
                
                // Parse title from markdown header (like Responses API)
                $title = 'Reasoning';
                $summaryText = $block['content'];
                if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                    $title = trim($matches[1]);
                    // Remove the title line from summary text
                    $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                    $summaryText = trim($summaryText);
                }
                
                // Collect status for persistence
                $this->addStatusToLog('reasoning', 'completed', $title, $this->currentOutputIndex);
                
                // Send "reasoning completed" status
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'completed',
                        'type' => 'reasoning',
                        'message' => $title, // Send title as message for custom label
                        'output_index' => $this->currentOutputIndex
                    ])
                ];
                
                // Send reasoning summary
                $auxiliaries[] = [
                    'type' => 'reasoning_summary',
                    'content' => json_encode([
                        'summary' => $summaryText,
                        'title' => $title,
                        'output_index' => $this->currentOutputIndex
                    ])
                ];
                
                // Mark as sent and store title
                $this->reasoningBlocks[$this->currentOutputIndex]['sent'] = true;
                $this->reasoningBlocks[$this->currentOutputIndex]['title'] = $title;
            }
        }
        
        // Build response content
        $responseContent = ['text' => $content];
        
        // CRITICAL: Only add auxiliaries if we actually have any in THIS chunk
        // This prevents duplicates from being sent in every subsequent chunk
        if (!empty($auxiliaries)) {
            $responseContent['auxiliaries'] = $auxiliaries;
        }
        
        return new AiResponse(
            content: $responseContent,
            usage: $usage,
            isDone: $isDone
        );
    }
    
    /**
     * Add status update to log for persistence (like Responses API)
     * 
     * @param string $type - Status type (processing, reasoning, web_search)
     * @param string $status - Status value (in_progress, completed, etc.)
     * @param string|null $message - Optional custom message (e.g., reasoning title)
     * @param int|null $outputIndex - Output index for multi-output scenarios
     */
    private function addStatusToLog(string $type, string $status, ?string $message = null, ?int $outputIndex = null): void
    {
        $entry = [
            'type' => $type,
            'status' => $status,
            'output_index' => $outputIndex,
            'timestamp' => microtime(true)
        ];
        
        if ($message !== null) {
            $entry['message'] = $message;
        }
        
        $this->statusLog[] = $entry;
    }
}
