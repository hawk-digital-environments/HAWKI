<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Responses\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Providers\Responses\Request\ResponsesUsageTrait;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class ResponsesRequest extends AbstractRequest
{
    use ResponsesUsageTrait;

    public function __construct(
        private array $payload
    )
    {
    }

    public function execute(AiModel $model): AiResponse
    {
        // Ensure stream is set to false for non-streaming
        $this->payload['stream'] = false;

        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            dataToResponse: fn(array $data) => $this->dataToResponse($data, $model)
        );
    }

    /**
     * Convert response data to AiResponse
     * Handles Responses API non-streaming format
     */
    protected function dataToResponse(array $data, AiModel $model): AiResponse
    {
        // Handle errors
        if (isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            return $this->createErrorResponse($errorMessage);
        }

        // Extract response data
        $response = $data['response'] ?? $data;
        $output = $response['output'] ?? [];
        
        // Find the message output (skip reasoning items)
        $messageOutput = null;
        foreach ($output as $item) {
            if (($item['type'] ?? '') === 'message') {
                $messageOutput = $item;
                break;
            }
        }

        // Extract text content
        $content = '';
        if ($messageOutput) {
            $contentParts = $messageOutput['content'] ?? [];
            foreach ($contentParts as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $content = $part['text'] ?? '';
                    break;
                }
            }
        }

        // Extract usage
        $usage = null;
        if (isset($response['usage'])) {
            $usage = $this->extractUsage(
                model: $model,
                data: $response
            );
        }

        // Build auxiliaries array
        $auxiliaries = [];
        
        // Build status log for non-streaming (similar to streaming)
        $statusLog = [];

        // Add response ID for conversation continuity
        if (isset($response['id'])) {
            $auxiliaries[] = [
                'type' => 'responsesMetadata',
                'content' => json_encode([
                    'response_id' => $response['id']
                ])
            ];
        }

        // Extract reasoning items and build status log
        $reasoningItems = [];
        $webSearchQueries = [];
        
        foreach ($output as $outputIndex => $item) {
            // Process reasoning items
            if (($item['type'] ?? '') === 'reasoning') {
                $summary = $this->extractReasoningSummary($item);
                
                $reasoningItems[] = [
                    'id' => $item['id'] ?? null,
                    'type' => 'reasoning',
                    'content' => $summary
                ];
                
                // Add reasoning status to log - ONE ENTRY PER SUMMARY PART (not combined)
                $summaryParts = $item['summary'] ?? [];
                foreach ($summaryParts as $part) {
                    if (($part['type'] ?? '') === 'summary_text') {
                        $summaryText = $part['text'] ?? '';
                        
                        if (!empty($summaryText)) {
                            // Extract title from markdown header
                            $title = 'Reasoning';
                            if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                                $title = trim($matches[1]);
                                $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                                $summaryText = trim($summaryText);
                            }
                            
                            $statusLog[] = [
                                'type' => 'reasoning',
                                'status' => 'completed',
                                'output_index' => $outputIndex,
                                'message' => $title,
                                'summary' => $summaryText
                            ];
                        }
                    }
                }
            }
            
            // Process web search calls
            if (($item['type'] ?? '') === 'web_search_call') {
                $query = $item['action']['query'] ?? null;
                $status = $item['status'] ?? 'unknown';
                
                if ($query) {
                    $webSearchQueries[] = [
                        'query' => $query,
                        'status' => $status
                    ];
                    
                    // Add web search status to log
                    $statusLog[] = [
                        'type' => 'web_search',
                        'status' => $status === 'completed' ? 'completed' : 'in_progress',
                        'output_index' => $outputIndex,
                        'message' => $query
                    ];
                }
            }
        }

        if (!empty($reasoningItems)) {
            $auxiliaries[] = [
                'type' => 'responsesReasoning',
                'content' => json_encode([
                    'reasoning' => $reasoningItems
                ])
            ];
        }

        // Extract and store reasoning summaries as individual auxiliaries
        $summaryIndex = 0;
        foreach ($output as $outputIndex => $item) {
            if (($item['type'] ?? '') === 'reasoning') {
                // Extract individual summary parts (not combined)
                $summaryParts = $item['summary'] ?? [];
                
                foreach ($summaryParts as $part) {
                    if (($part['type'] ?? '') === 'summary_text') {
                        $summaryText = $part['text'] ?? '';
                        
                        if (!empty($summaryText)) {
                            // Extract title from markdown header
                            $title = 'Reasoning';
                            if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                                $title = trim($matches[1]);
                                $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                                $summaryText = trim($summaryText);
                            }
                            
                            // Add as individual auxiliary (same format as streaming)
                            $auxiliaries[] = [
                                'type' => 'reasoning_summary_item',
                                'content' => json_encode([
                                    'index' => $summaryIndex,
                                    'output_index' => $outputIndex,
                                    'title' => $title,
                                    'summary' => $summaryText
                                ])
                            ];
                            
                            $summaryIndex++;
                        }
                    }
                }
            }
        }
        
        // NOTE: web_search_query auxiliaries are only needed for streaming
        // For non-streaming, web search info is already in status_log
        // So we don't create separate web_search_query auxiliaries here
        
        // Add final "processing completed" status to the log (if log has entries)
        if (!empty($statusLog)) {
            $statusLog[] = [
                'type' => 'processing',
                'status' => 'completed',
                'output_index' => null,
                'message' => null,  // Frontend derives label from status
                'timestamp' => now()->timestamp * 1000  // Match frontend timestamp format (milliseconds)
            ];
        }
        
        // Add final status log if not empty
        if (!empty($statusLog)) {
            $auxiliaries[] = [
                'type' => 'status_log',
                'content' => json_encode([
                    'log' => $statusLog  // Wrap in object with 'log' property for frontend compatibility
                ])
            ];
        }

        // Extract citations from message annotations
        $citations = [];
        if ($messageOutput) {
            $contentParts = $messageOutput['content'] ?? [];
            foreach ($contentParts as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $annotations = $part['annotations'] ?? [];
                    foreach ($annotations as $annotation) {
                        if (($annotation['type'] ?? '') === 'url_citation') {
                            $citations[] = [
                                'type' => 'url_citation',
                                'url' => $annotation['url'] ?? '',
                                'title' => $annotation['title'] ?? '',
                                'start_index' => $annotation['start_index'] ?? 0,
                                'end_index' => $annotation['end_index'] ?? 0,
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($citations)) {
            $auxiliaries[] = [
                'type' => 'responsesCitations',
                'content' => json_encode([
                    'citations' => $citations
                ])
            ];
        }

        // Build response content
        $responseContent = ['text' => $content];
        if (!empty($auxiliaries)) {
            $responseContent['auxiliaries'] = $auxiliaries;
        }

        // Debug logging for non-streaming response
        if (config('logging.triggers.curl_return_object')) {
            \Log::info('[NON-STREAMING] Response Content', [
                'text_length' => strlen($content),
                'auxiliaries_count' => count($auxiliaries),
                'has_reasoning' => !empty($reasoningItems),
                'has_citations' => !empty($citations),
                'response_id' => $response['id'] ?? null
            ]);
        }

        return new AiResponse(
            content: $responseContent,
            usage: $usage,
            isDone: true
        );
    }

    /**
     * Extract reasoning summary from reasoning item
     */
    private function extractReasoningSummary(array $item): ?string
    {
        $summaryParts = $item['summary'] ?? [];
        
        $summaryText = '';
        foreach ($summaryParts as $part) {
            if (($part['type'] ?? '') === 'summary_text') {
                $summaryText .= $part['text'] ?? '';
            }
        }
        
        return !empty($summaryText) ? $summaryText : null;
    }
}
