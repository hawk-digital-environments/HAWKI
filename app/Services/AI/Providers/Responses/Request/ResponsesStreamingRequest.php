<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Responses\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class ResponsesStreamingRequest extends AbstractRequest
{
    use ResponsesUsageTrait;

    private array $reasoningItems = [];
    private array $citations = [];
    private string $reasoningSummary = '';
    private array $allReasoningSummaries = [];
    private array $reasoningSummaryTitles = []; // Map output_index => title
    private array $reasoningSummaryContent = []; // Map output_index => summary content
    private array $webSearchQueries = [];
    private array $statusLog = []; // Collect all status updates for persistence

    public function __construct(
        private array    $payload,
        private \Closure $onData
    )
    {
    }

    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;

        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }

    /**
     * Convert streaming chunk to AiResponse
     * Handles all Responses API event types
     */
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);

        if (!$jsonChunk) {
            return $this->createErrorResponse('Invalid JSON chunk received.');
        }

        // Handle errors
        if (isset($jsonChunk['error'])) {
            $errorMessage = $jsonChunk['error']['message'] ?? 'Unknown error';
            
            // Log critical error for previous_response_id issues (known OpenAI Beta limitation)
            //if (str_contains($errorMessage, 'Previous response') && str_contains($errorMessage, 'not found')) {
            //    \Log::warning('Responses API: previous_response_id not found', [
            //        'error' => $errorMessage
            //    ]);
            //}
            
            return $this->createErrorResponse($errorMessage);
        }

        $type = $jsonChunk['type'] ?? '';
        
        $content = '';
        $isDone = false;
        $usage = null;
        $auxiliaries = [];

        switch ($type) {
            // Main streaming text chunks
            case 'response.output_text.delta':
                $content = $jsonChunk['delta'] ?? '';
                break;

            // Complete text output - DON'T send to avoid overwriting collected deltas
            // Text completion (metadata event)
            case 'response.output_text.done':
                // \Log::info('[RESPONSES] Event Type: response.output_text.done');
                // Just a completion signal, no content to send (deltas are already collected in frontend)
                break;

            // Content part completion (metadata event)
            case 'response.content_part.done':
                // \Log::info('[RESPONSES] Event Type: response.content_part.done');
                // Just a completion signal, no content to send
                break;

            // Progress status - metadata event (no user-facing status needed)
            case 'response.in_progress':
                // \Log::info('[RESPONSES] Event Type: response.in_progress');
                // No status update needed - actual status comes from reasoning/web_search events
                break;

            // Reasoning chunks (streaming)
            case 'response.reasoning.delta':
                $this->handleReasoningDelta($jsonChunk);
                // No status update needed - status already sent by response.output_item.added
                break;

            // Complete reasoning output
            case 'response.reasoning.done':
                $this->handleReasoningDone($jsonChunk);
                break;

            // MCP tool call initiated
            case 'response.mcp_call_tool':
                // Tool calls are handled internally by OpenAI
                // We just log for debugging if needed
                break;

            // Web search call initiated
            case 'response.web_search_call':
                // Extract output_index and web search metadata
                $outputIndex = $jsonChunk['output_index'] ?? null;
                //// \Log::info('[RESPONSES] Event Type: response.web_search_call', [
                //    'output_index' => $outputIndex
                //]);
                
                $this->handleWebSearchCall($jsonChunk);
                
                // DON'T collect in_progress status - will be replaced by completed state
                
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'web_search',
                        'output_index' => $outputIndex
                    ])
                ];
                $content = ''; // Ensure message element is created/updated
                break;

            // Web search in progress
            case 'response.web_search_call.searching':
                $outputIndex = $jsonChunk['output_index'] ?? null;
                //// \Log::info('[RESPONSES] Event Type: response.web_search_call.searching', [
                //    'output_index' => $outputIndex
                //]);
                
                // DON'T collect in_progress status - will be replaced by completed state
                
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'web_search',
                        'output_index' => $outputIndex
                    ])
                ];
                $content = ''; // Ensure status is sent
                break;

            // Web search completed
            case 'response.web_search_call.completed':
                $outputIndex = $jsonChunk['output_index'] ?? null;
                //// \Log::info('[RESPONSES] Event Type: response.web_search_call.completed', [
                //    'output_index' => $outputIndex
                //]);
                
                // Collect success status (without message - frontend derives label)
                $this->addStatusToLog('web_search', 'success', null, $outputIndex);
                
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'web_search_success',
                        'output_index' => $outputIndex
                    ])
                ];
                $content = ''; // Ensure status is sent
                break;

            // Web search in progress (metadata event)
            case 'response.web_search_call.in_progress':
                $outputIndex = $jsonChunk['output_index'] ?? null;
                //// \Log::info('[RESPONSES] Event Type: response.web_search_call.in_progress', [
                //    'output_index' => $outputIndex
                //]);
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'web_search',
                        'output_index' => $outputIndex
                    ])
                ];
                $content = ''; // Ensure status is sent
                break;

            // Response completed with final data
            case 'response.completed':
                // \Log::info('[RESPONSES] Event Type: response.completed');
                $isDone = true;
                
                // Extract usage from final response
                if (!empty($jsonChunk['response']['usage'])) {
                    $usage = $this->extractUsage($model, $jsonChunk['response']);
                    
                    // Add server tool use information
                    if ($usage && !empty($this->webSearchQueries)) {
                        $serverToolUse = [
                            'web_search_requests' => count($this->webSearchQueries)
                        ];
                        
                        // Create new TokenUsage with server tool use
                        $usage = new \App\Services\AI\Value\TokenUsage(
                            model: $usage->model,
                            promptTokens: $usage->promptTokens,
                            completionTokens: $usage->completionTokens,
                            totalTokens: $usage->totalTokens,
                            cacheReadInputTokens: $usage->cacheReadInputTokens,
                            cacheCreationInputTokens: $usage->cacheCreationInputTokens,
                            reasoningTokens: $usage->reasoningTokens,
                            audioInputTokens: $usage->audioInputTokens,
                            audioOutputTokens: $usage->audioOutputTokens,
                            serverToolUse: $serverToolUse,
                        );
                    }
                }

                // Extract response ID for multi-turn conversation continuity
                $responseId = $jsonChunk['response']['id'] ?? null;
                if ($responseId) {
                    $auxiliaries[] = [
                        'type' => 'responsesMetadata',
                        'content' => json_encode([
                            'response_id' => $responseId
                        ])
                    ];
                }

                // Include reasoning items as auxiliaries
                if (!empty($this->reasoningItems)) {
                    $auxiliaries[] = [
                        'type' => 'responsesReasoning',
                        'content' => json_encode([
                            'reasoning' => $this->reasoningItems
                        ])
                    ];
                }

                // Include ALL collected reasoning summaries as individual auxiliaries
                // This ensures they are stored in the database for later retrieval
                if (!empty($this->allReasoningSummaries)) {
                    ksort($this->allReasoningSummaries);
                    foreach ($this->allReasoningSummaries as $index => $summaryData) {
                        $summaryText = is_array($summaryData) ? $summaryData['text'] : $summaryData;
                        $outputIndex = is_array($summaryData) ? ($summaryData['output_index'] ?? null) : null;
                        
                        // Extract title from markdown header
                        $title = 'Reasoning';
                        if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                            $title = trim($matches[1]);
                            // Remove title from summary text
                            $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                            $summaryText = trim($summaryText);
                        }
                        
                        $auxContent = [
                            'index' => $index,
                            'title' => $title,
                            'summary' => $summaryText
                        ];
                        
                        if ($outputIndex !== null) {
                            $auxContent['output_index'] = $outputIndex;
                        }
                        
                        $auxiliaries[] = [
                            'type' => 'reasoning_summary_item',
                            'content' => json_encode($auxContent)
                        ];
                    }
                    
                    //// \Log::info('[RESPONSES] Added reasoning summaries to final response', [
                    //    'total_summaries' => count($this->allReasoningSummaries)
                    //]);
                }

                // Include web search queries as individual auxiliaries
                // This ensures they are stored in the database for later retrieval
                if (!empty($this->webSearchQueries)) {
                    foreach ($this->webSearchQueries as $index => $queryData) {
                        $query = is_array($queryData) ? $queryData['query'] : $queryData;
                        $outputIndex = is_array($queryData) ? ($queryData['output_index'] ?? null) : null;
                        
                        // Ensure query is a string (handle nested arrays/objects)
                        if (is_array($query) || is_object($query)) {
                            $query = json_encode($query);
                        }
                        
                        $auxContent = [
                            'index' => $index,
                            'query' => $query
                        ];
                        
                        if ($outputIndex !== null) {
                            $auxContent['output_index'] = $outputIndex;
                        }
                        
                        $auxiliaries[] = [
                            'type' => 'web_search_query',
                            'content' => json_encode($auxContent)
                        ];
                    }
                    
                    //// \Log::info('[RESPONSES] Added web search queries to final response', [
                    //    'total_queries' => count($this->webSearchQueries)
                    //]);
                }

                // Note: Reasoning summaries and web search queries are also sent individually
                // AND included here in final response for database persistence

                // Collect final completed status for persistence
                $this->addStatusToLog('processing', 'completed', null);
                
                // \Log::info('[RESPONSES] Added final processing completed status to log');

                // Send final processing completed status WITHOUT message (Frontend derives label)
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'completed'
                    ])
                ];
                
                // \Log::info('[RESPONSES] Sending final processing completed status to frontend');

                // Add final status log as auxiliary for persistence
                if (!empty($this->statusLog)) {
                    // Update reasoning step labels and summaries before saving
                    foreach ($this->statusLog as &$entry) {
                        if ($entry['type'] === 'reasoning' && isset($entry['output_index'])) {
                            $outputIndex = $entry['output_index'];
                            
                            // Add title if available
                            if (isset($this->reasoningSummaryTitles[$outputIndex])) {
                                $entry['message'] = $this->reasoningSummaryTitles[$outputIndex];
                                //// \Log::info('[RESPONSES] Updated reasoning step with title', [
                                //    'output_index' => $outputIndex,
                                //    'title' => $this->reasoningSummaryTitles[$outputIndex]
                                //]);
                            }
                            
                            // Add summary content if available
                            if (isset($this->reasoningSummaryContent[$outputIndex])) {
                                $entry['summary'] = $this->reasoningSummaryContent[$outputIndex];
                                //// \Log::info('[RESPONSES] Updated reasoning step with summary content', [
                                //    'output_index' => $outputIndex,
                                //    'summary_length' => strlen($this->reasoningSummaryContent[$outputIndex])
                                //]);
                            } else {
                                //\Log::warning('[RESPONSES] No summary content found for reasoning step', [
                                //    'output_index' => $outputIndex,
                                //    'available_summaries' => array_keys($this->reasoningSummaryContent)
                                //]);
                            }
                        }
                    }
                    unset($entry); // Break reference
                    
                    $auxiliaries[] = [
                        'type' => 'status_log',
                        'content' => json_encode([
                            'log' => $this->statusLog
                        ])
                    ];
                    //// \Log::info('[RESPONSES] Added status log to final response', [
                    //    'total_entries' => count($this->statusLog),
                    //    'reasoning_titles_updated' => count($this->reasoningSummaryTitles),
                    //    'reasoning_summaries_added' => count($this->reasoningSummaryContent)
                    //]);
                }

                // Include citations as auxiliaries
                if (!empty($this->citations)) {
                    $auxiliaries[] = [
                        'type' => 'responsesCitations',
                        'content' => json_encode([
                            'citations' => $this->citations
                        ])
                    ];
                    //// \Log::info('[RESPONSES] Added citations to final response', [
                    //    'total_citations' => count($this->citations)
                    //]);
                } else {
                    // \Log::info('[RESPONSES] No citations collected');
                }
                break;

            // Response failed
            case 'response.failed':
                $error = $jsonChunk['error'] ?? $jsonChunk['response']['error'] ?? [];
                $errorMessage = $error['message'] ?? 'Response failed';
                $errorCode = $error['code'] ?? null;
                
                \Log::error('[RESPONSES] Response failed', [
                    'error_message' => $errorMessage,
                    'error_code' => $errorCode
                ]);
                
                // Collect error status for persistence WITHOUT message (Frontend derives label)
                $this->addStatusToLog('processing', 'error', null);
                
                // Send error status to frontend WITHOUT message
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'error',
                        'error_code' => $errorCode
                    ])
                ];
                
                // Add error status log as auxiliary for persistence
                if (!empty($this->statusLog)) {
                    $auxiliaries[] = [
                        'type' => 'status_log',
                        'content' => json_encode([
                            'log' => $this->statusLog
                        ])
                    ];
                }
                
                // Return error response with auxiliaries (detailed error for debugging)
                return [
                    'content' => '',
                    'auxiliaries' => $auxiliaries,
                    'error' => $errorMessage // Keep detailed error for logs/debugging
                ];

            // Output item done - may contain citations/annotations
            case 'response.output_item.done':
                $this->handleOutputItemDone($jsonChunk);
                
                // Check if this is a reasoning item completion
                $item = $jsonChunk['item'] ?? [];
                $itemType = $item['type'] ?? null;
                $outputIndex = $jsonChunk['output_index'] ?? null;
                
                if ($itemType === 'reasoning') {
                    // Reasoning completed - send status update
                    // \Log::info('[RESPONSES] Event Type: response.output_item.done', [
                    //    'item_type' => $itemType,
                    //    'output_index' => $outputIndex
                    //]);
                    
                    // Use summary title if available (custom content), otherwise NO message (Frontend derives label)
                    $label = $this->reasoningSummaryTitles[$outputIndex] ?? null;
                    
                    // Collect status for persistence
                    $this->addStatusToLog('reasoning', 'completed', $label, $outputIndex);
                    
                    $statusContent = [
                        'status' => 'reasoning_complete',
                        'output_index' => $outputIndex
                    ];
                    
                    // Only add message if it's a custom summary title
                    if ($label !== null) {
                        $statusContent['message'] = $label;
                    }
                    
                    $auxiliaries[] = [
                        'type' => 'status',
                        'content' => json_encode($statusContent)
                    ];
                    $content = '';
                } elseif ($itemType === 'web_search_call') {
                    // Web search completed - extract query and send status
                    $action = $item['action'] ?? [];
                    $query = $action['query'] ?? $item['query'] ?? null;
                    $outputIndex = $jsonChunk['output_index'] ?? null;
                    
                    // Log the full item structure if query is null for debugging
                    if ($query === null) {
                        //\Log::warning('[RESPONSES] Web search query is null, full item:', [
                        //    'item' => $item,
                        //    'output_index' => $outputIndex
                        //]);
                    }
                    
                    //\Log::info('[RESPONSES] Event Type: response.output_item.done', [
                    //    'item_type' => $itemType,
                    //    'query' => $query,
                    //    'output_index' => $outputIndex
                    //]);
                    
                    // Only process and send status if query is available
                    if ($query) {
                        // Store query for final response (as array with output_index)
                        // Check if query already exists
                        $exists = false;
                        foreach ($this->webSearchQueries as $existingQuery) {
                            $existingQueryText = is_array($existingQuery) ? $existingQuery['query'] : $existingQuery;
                            if ($existingQueryText === $query) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            $this->webSearchQueries[] = [
                                'query' => $query,
                                'output_index' => $outputIndex
                            ];
                            //\Log::info('[RESPONSES] Web search query stored from output_item.done', [
                            //    'query' => $query,
                            //    'output_index' => $outputIndex,
                            //    'total_queries' => count($this->webSearchQueries)
                            //]);
                        }
                        
                        // Collect COMPLETED status with query for persistence
                        $this->addStatusToLog('web_search', 'completed', 'Searched for: ' . $query, $outputIndex);
                        
                        // Send web_search_complete status WITH query (Frontend uses query for label)
                        $auxiliaries[] = [
                            'type' => 'status',
                            'content' => json_encode([
                                'status' => 'web_search_complete',
                                'query' => $query,
                                'output_index' => $outputIndex
                            ])
                        ];
                    } else {
                        // No query available - still collect status but without query
                        $this->addStatusToLog('web_search', 'completed', null, $outputIndex);
                        
                        // Send web_search_complete WITHOUT query (Frontend uses fallback label)
                        // Frontend will remove the temporary status item
                        $auxiliaries[] = [
                            'type' => 'status',
                            'content' => json_encode([
                                'status' => 'web_search_complete',
                                'query' => null,
                                'output_index' => $outputIndex
                            ])
                        ];
                        // \Log::info('[RESPONSES] Sending web_search_complete without query (will be removed in frontend)');
                    }
                    
                    $content = '';
                } else {
                    // Generic output_item.done (e.g., message)
                    //\Log::info('[RESPONSES] Event Type: response.output_item.done', [
                    //    'item_type' => $itemType ?? 'unknown',
                    //    'output_index' => $outputIndex
                    //]);
                }
                break;

            // Response created - initial event, send status to create message element
            case 'response.created':
                // \Log::info('[RESPONSES] Event Type: response.created');
                // Send backend microtime as auxiliary for lag measurement
                $auxiliaries[] = [
                    'type' => 'debug_timestamp',
                    'content' => json_encode([
                        'backend_microtime' => microtime(true),
                        'backend_timestamp' => now()->toIso8601String()
                    ])
                ];
                
                // Collect initial status for persistence
                $this->addStatusToLog('processing', 'in_progress', null);
                
                // Send initial processing status WITHOUT message (Frontend derives label from status)
                $auxiliaries[] = [
                    'type' => 'status',
                    'content' => json_encode([
                        'status' => 'in_progress'
                    ])
                ];
                $content = '';
                break;

            // Output item added - check if it's reasoning or web search
            case 'response.output_item.added':
                $item = $jsonChunk['item'] ?? [];
                $itemType = $item['type'] ?? null;
                $outputIndex = $jsonChunk['output_index'] ?? null;
                
                if ($itemType === 'reasoning') {
                    // Reasoning started - send status update
                    //\Log::info('[RESPONSES] Event Type: response.output_item.added', [
                    //    'item_type' => $itemType,
                    //    'output_index' => $outputIndex
                    //]);
                    
                    // DON'T collect in_progress status - will be replaced by completed state
                    
                    $auxiliaries[] = [
                        'type' => 'status',
                        'content' => json_encode([
                            'status' => 'reasoning',
                            'output_index' => $outputIndex
                        ])
                    ];
                    $content = '';
                } elseif ($itemType === 'web_search_call') {
                    // Web search initiated - send initial status update
                    //\Log::info('[RESPONSES] Event Type: response.output_item.added', [
                    //    'item_type' => $itemType,
                    //    'output_index' => $outputIndex
                    //]);
                    
                    // Collect initial web_search status for persistence
                    $this->addStatusToLog('web_search', 'initiated', null, $outputIndex);
                    
                    $auxiliaries[] = [
                        'type' => 'status',
                        'content' => json_encode([
                            'status' => 'web_search_initiated',
                            'output_index' => $outputIndex
                        ])
                    ];
                    $content = '';
                } else {
                    // Generic output_item.added (e.g., message)
                    //\Log::info('[RESPONSES] Event Type: response.output_item.added', [
                    //    'item_type' => $itemType ?? 'unknown',
                    //    'output_index' => $outputIndex
                    //]);
                }
                break;

            // Metadata events (no action needed)
            case 'response.content_part.added':
            case 'response.content_part.done':
                // No action needed for these metadata events
                break;
            
            case 'response.output_text.annotation.added':
                // Log annotation events for debugging (citations, etc.)
                $annotation = $jsonChunk['annotation'] ?? [];
                $annotationType = $annotation['type'] ?? 'unknown';
                $annotationUrl = $annotation['url'] ?? null;
                //\Log::info('[RESPONSES] Event Type: response.output_text.annotation.added', [
                //    'annotation_type' => $annotationType,
                //    'url' => $annotationUrl,
                //    'output_index' => $jsonChunk['output_index'] ?? null
                //]);
                break;
            
            case 'response.refusal.delta':
            case 'response.refusal.done':
            case 'response.function_call_arguments.delta':
            case 'response.function_call_arguments.done':
            case 'response.file_search_call.in_progress':
            case 'response.file_search_call.searching':
            case 'response.file_search_call.completed':
            case 'response.code_interpreter_call.in_progress':
            case 'response.code_interpreter_call.completed':
            case 'response.code_interpreter_code.delta':
            case 'response.code_interpreter_code.done':
                // Ignore metadata events (status already handled above)
                break;

            // Reasoning summary events - collect summary text for display
            case 'response.reasoning_summary_part.added':
                // Reasoning summary started - initialize buffer
                $this->reasoningSummary = '';
                //\Log::info('[RESPONSES] Event Type: response.reasoning_summary_part.added', [
                //    'summary_index' => $jsonChunk['summary_index'] ?? null,
                //    'output_index' => $jsonChunk['output_index'] ?? null
                //]);
                break;

            case 'response.reasoning_summary_text.delta':
                // Accumulate reasoning summary chunks (for streaming display if needed)
                $delta = $jsonChunk['delta'] ?? '';
                $this->reasoningSummary .= $delta;
                //\Log::info('[RESPONSES] Event Type: response.reasoning_summary_text.delta', [
                //    'delta_length' => strlen($delta)
                //]);
                break;

            case 'response.reasoning_summary_text.done':
                // One summary part completed - store it but don't send yet (wait for part.done)
                $summaryText = $jsonChunk['text'] ?? '';
                $this->reasoningSummary = trim($summaryText);
                //\Log::info('[RESPONSES] Event Type: response.reasoning_summary_text.done', [
                //    'text_length' => strlen($this->reasoningSummary)
                //]);
                break;

            case 'response.reasoning_summary_part.done':
                // Summary part fully completed - send as individual auxiliary
                if (!empty($this->reasoningSummary)) {
                    $summaryIndex = $jsonChunk['summary_index'] ?? count($this->allReasoningSummaries);
                    $outputIndex = $jsonChunk['output_index'] ?? null;
                    
                    // Extract title from markdown header (e.g., **Title**)
                    $title = 'Reasoning';
                    $summaryText = $this->reasoningSummary;
                    if (preg_match('/^\*\*(.+?)\*\*/', $summaryText, $matches)) {
                        $title = trim($matches[1]);
                        // Remove the title line from the summary text
                        $summaryText = preg_replace('/^\*\*(.+?)\*\*\s*\n*/', '', $summaryText);
                        $summaryText = trim($summaryText);
                    }
                    
                    // Store title for status log update
                    if ($outputIndex !== null) {
                        $this->reasoningSummaryTitles[$outputIndex] = $title;
                        $this->reasoningSummaryContent[$outputIndex] = $summaryText;
                        
                        //\Log::info('[RESPONSES] Stored reasoning summary for persistence', [
                        //    'output_index' => $outputIndex,
                        //    'title' => $title,
                        //    'summary_length' => strlen($summaryText)
                        //]);
                    }
                    
                    //\Log::info('[RESPONSES] Sending reasoning summary as auxiliary', [
                    //    'summary_index' => $summaryIndex,
                    //    'output_index' => $outputIndex,
                    //    'title' => $title,
                    //    'text_preview' => substr($summaryText, 0, 50) . '...'
                    //]);
                    
                    // Send summary immediately as auxiliary
                    $auxiliaries[] = [
                        'type' => 'reasoning_summary_item',
                        'content' => json_encode([
                            'index' => $summaryIndex,
                            'output_index' => $outputIndex,
                            'title' => $title,
                            'summary' => $summaryText
                        ])
                    ];
                    
                    // Also store for final combined summary (with output_index)
                    $this->allReasoningSummaries[$summaryIndex] = [
                        'text' => $this->reasoningSummary,
                        'output_index' => $outputIndex
                    ];
                    
                    // Reset buffer
                    $this->reasoningSummary = '';
                    $content = ''; // Force sending auxiliary
                }
                break;

            case 'response.reasoning_text.delta':
            case 'response.reasoning_text.done':
                // Raw reasoning text events (not used when summary is enabled)
                break;

            case 'response.mcp_list_tools.in_progress':
            case 'response.mcp_list_tools.completed':
            case 'response.mcp_call.in_progress':
            case 'response.mcp_call.completed':
            case 'response.mcp_call_arguments.delta':
            case 'response.mcp_call.arguments.done':
            case 'response.mcp_call_arguments.done':
            case 'response.image_generation_call.completed':
            case 'response.image_generation_call.generating':
            case 'response.image_generation_call.in_progress':
            case 'response.image_generation_call.partial_image':
            case 'response.incomplete':
            case 'error':
                // Ignore metadata events (status already handled above)
                break;

            default:
                // Unknown event type - log for debugging
                // Note: Don't use Log::debug in production
                break;
        }

        // Skip empty responses for metadata events (prevents duplicate messages)
        // BUT send responses with status auxiliaries (for user feedback)
        //if (empty($content) && !$isDone && empty($auxiliaries)) {
        //    \Log::info('[RESPONSES DEBUG] Skipping empty response (no content, no auxiliaries)');
        //    return new AiResponse(
        //        content: ['text' => ''],
        //        isDone: false
        //    );
        //}

        // Build content array (like Google does with groundingMetadata)
        $responseContent = ['text' => $content];

        // Add auxiliaries to content (will be encrypted client-side with text)
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
     * Handle streaming reasoning delta
     */
    private function handleReasoningDelta(array $chunk): void
    {
        // Store reasoning chunk for later assembly
        $itemId = $chunk['item_id'] ?? null;
        $delta = $chunk['delta'] ?? '';

        if ($itemId) {
            if (!isset($this->reasoningItems[$itemId])) {
                $this->reasoningItems[$itemId] = [
                    'id' => $itemId,
                    'type' => 'reasoning',
                    'content' => ''
                ];
            }
            $this->reasoningItems[$itemId]['content'] .= $delta;
        }
    }

    /**
     * Handle complete reasoning output
     */
    private function handleReasoningDone(array $chunk): void
    {
        $itemId = $chunk['item_id'] ?? null;
        $content = $chunk['content'] ?? $chunk['text'] ?? '';

        if ($itemId) {
            $this->reasoningItems[$itemId] = [
                'id' => $itemId,
                'type' => 'reasoning',
                'content' => $content
            ];
        }
    }

    /**
     * Handle web search call event
     * Extracts search metadata for debugging/analytics
     */
    private function handleWebSearchCall(array $chunk): void
    {
        // Extract web search call metadata
        $searchId = $chunk['id'] ?? null;
        $status = $chunk['status'] ?? 'unknown';
        
        // Extract action details if available
        $action = $chunk['action'] ?? [];
        $actionType = $action['type'] ?? null; // 'search', 'open_page', 'find_in_page'
        
        //\Log::info('[RESPONSES] Web search call event', [
        //    'search_id' => $searchId,
        //    'status' => $status,
        //    'action_type' => $actionType,
        //    'action' => $action
        //]);
        
        // Extract and store query when status is 'completed'
        if ($status === 'completed' && $actionType === 'search') {
            $query = $action['query'] ?? null;
            if ($query) {
                $this->webSearchQueries[] = $query;
                //\Log::info('[RESPONSES] Web search query captured', [
                //    'query' => $query,
                //    'search_id' => $searchId,
                //    'total_queries' => count($this->webSearchQueries)
                //]);
            } else {
                //\Log::warning('[RESPONSES] Web search completed but no query found', [
                //    'action' => $action
                //]);
            }
        }
        
        // Optional: Extract additional details for future use
        // $domains = $action['domains'] ?? [];
        // $sources = $action['sources'] ?? [];
    }

    /**
     * Handle output item done event
     * Extracts URL citations from message annotations
     */
    private function handleOutputItemDone(array $chunk): void
    {
        // Check if this is a message output item with content
        $item = $chunk['item'] ?? [];
        if (($item['type'] ?? '') !== 'message') {
            return;
        }

        //\Log::info('[RESPONSES] Processing message output_item.done for citations');

        // Extract content array
        $content = $item['content'] ?? [];
        if (empty($content)) {
            //\Log::info('[RESPONSES] No content in message item');
            return;
        }

        // Parse each content part for annotations
        foreach ($content as $contentPart) {
            if (($contentPart['type'] ?? '') === 'output_text') {
                $annotations = $contentPart['annotations'] ?? [];
                
                //\Log::info('[RESPONSES] Found output_text with annotations', [
                //    'annotation_count' => count($annotations)
                //]);
                
                foreach ($annotations as $annotation) {
                    if (($annotation['type'] ?? '') === 'url_citation') {
                        // Store citation for later use
                        $this->citations[] = [
                            'type' => 'url_citation',
                            'url' => $annotation['url'] ?? '',
                            'title' => $annotation['title'] ?? '',
                            'start_index' => $annotation['start_index'] ?? 0,
                            'end_index' => $annotation['end_index'] ?? 0,
                        ];
                        
                        //\Log::info('[RESPONSES] Stored citation', [
                        //    'url' => $annotation['url'] ?? '',
                        //    'title' => $annotation['title'] ?? ''
                        //]);
                    }
                }
            }
        }
        
        //\Log::info('[RESPONSES] Total citations collected so far', [
        //    'total' => count($this->citations)
        //]);
    }

    /**
     * Add status update to log for persistence
     * Only call this for completed/final states
     */
    private function addStatusToLog(string $type, string $status, ?string $message, ?int $outputIndex = null): void
    {
        $statusEntry = [
            'type' => $type,
            'status' => $status,
            'timestamp' => microtime(true)
        ];
        
        // Only add message if provided (for custom content like Reasoning Summary Titles)
        if ($message !== null) {
            $statusEntry['message'] = $message;
        }
        
        if ($outputIndex !== null) {
            $statusEntry['output_index'] = $outputIndex;
        }
        
        $this->statusLog[] = $statusEntry;
    }
}
