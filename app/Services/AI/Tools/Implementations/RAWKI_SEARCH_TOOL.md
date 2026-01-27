# RAWKI Web Search Tool

## Overview

The RawkiSearchTool integrates HAWKI with the RAWKI MCP server to provide web search capabilities using Brave or Tavily search providers.

## Architecture

```
User Query
   ↓
HAWKI AI Model (OpenAI/GWDG)
   ↓
Tool Call: web_search
   ↓
HAWKI (Laravel) - RawkiSearchTool
   ↓
RAWKI MCP Server (http://localhost:8080/mcp/rawki)
   ↓
Search Provider (Brave or Tavily API)
   ↓
Search Results
   ↓
HAWKI formats and returns to AI model
   ↓
AI generates response with search results
```

## Configuration

### HAWKI Configuration

**config/tools.php:**
```php
'available_tools' => [
    \App\Services\AI\Tools\Implementations\RawkiSearchTool::class,
],

'mcp_servers' => [
    'web_search' => [
        'url' => env('RAWKI_MCP_SERVER_URL', 'http://localhost:8080/mcp/rawki'),
        'server_label' => 'rawki_search',
        'description' => 'RAWKI Web Search',
        'require_approval' => 'never',
    ],
],
```

**config/model_lists/openai_models.php:**
```php
'gpt-4.1' => [
    'tools' => [
        'web_search' => 'mcp',  // Use MCP execution strategy
    ],
],
```

### RAWKI Server Configuration

The RAWKI server must have API keys configured in `.env`:

```env
# Choose one or both providers
SEARCH_PROVIDER=brave  # or tavily

# Brave Search (https://brave.com/search/api/)
BRAVE_SEARCH_API_KEY=your_brave_api_key_here

# Tavily Search (https://tavily.com/)
TAVILY_API_KEY=your_tavily_api_key_here
```

## Tool Parameters

### Input

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | Yes | The search query |
| `provider` | string | No | Search provider: "brave" or "tavily". Defaults to brave |
| `max_results` | integer | No | Maximum number of results (1-20). Default: 5 |

### Output

Returns a JSON object with:
```json
{
  "query": "search query",
  "provider": "brave",
  "results": [...],
  "raw_response": {...}
}
```

## Usage Examples

### Via AI Model

```
User: "What are the latest developments in AI in 2026?"
AI: [Calls web_search tool]
Tool: {query: "latest AI developments 2026", provider: "brave", max_results: 5}
AI: [Receives results and synthesizes response]
```

### Direct Testing

You can test the tool directly via the ToolRegistry:

```php
$registry = app(ToolRegistry::class);
$tool = $registry->get('web_search');

$result = $tool->execute([
    'query' => 'Laravel 11 features',
    'provider' => 'brave',
    'max_results' => 5
], 'test_call_123');
```

## Execution Strategies

The tool can be configured with either strategy in model configs:

### `mcp` Strategy (Recommended)
```php
'web_search' => 'mcp',
```
- HAWKI orchestrates the MCP call
- Full error handling and logging
- Results formatted for AI consumption

### `function_call` Strategy
```php
'web_search' => 'function_call',
```
- Same execution as MCP (AbstractMCPTool handles both)
- Just a different config naming convention

## Error Handling

The tool handles various error scenarios:

1. **RAWKI server unavailable**
   - Returns error: "MCP server not available"
   - Logged with server URL

2. **Missing API keys** (in RAWKI)
   - Brave: "BRAVE_SEARCH_API_KEY is not set"
   - Tavily: "TAVILY_API_KEY is not set"

3. **Invalid query**
   - Returns error: "Search query is required"

4. **Search provider errors**
   - Returned from RAWKI with status code and details

## Server Availability Check

The tool includes a health check that verifies RAWKI server connectivity:

```php
public function isServerAvailable(): bool
{
    // Performs a quick HEAD request to the MCP server
    // Returns true if server responds
}
```

This check is performed before each tool execution.

## RAWKI Server Details

**Location:** `../rawki/app/Mcp/Tools/SearchTool.php`

**MCP Endpoint:** `http://localhost:8080/mcp/rawki`

**Tool Name:** `search` (not `web_search`)

The tool name mapping:
- HAWKI calls it: `web_search`
- RAWKI MCP server tool: `search`
- Mapping happens in RawkiSearchTool.executeMCP()

## Docker Setup

If RAWKI is running in Docker, ensure:

1. Port 8080 is exposed:
```yaml
ports:
  - "8080:8080"
```

2. HAWKI can reach localhost:8080 from its environment

3. Network connectivity between containers (if HAWKI is also containerized)

## Logging

The tool logs at various points:

```php
// Before execution
Log::info('RAWKI search tool executing', [
    'query' => $query,
    'provider' => $provider,
    'max_results' => $maxResults,
    'server' => $serverUrl,
]);

// After execution
Log::info('RAWKI search tool executed successfully', [
    'query' => $query,
    'result_count' => count($response['results'] ?? []),
]);

// On server unavailable
Log::warning('RAWKI MCP server not available', [
    'url' => $url,
    'error' => $e->getMessage(),
]);
```

Check logs at: `storage/logs/laravel.log`

## Testing

### 1. Verify RAWKI Server
```bash
curl http://localhost:8080/mcp/rawki
```

### 2. Test via HAWKI
Ask the AI model:
```
"Search for information about Laravel 11 new features"
```

### 3. Check Logs
```bash
tail -f storage/logs/laravel.log | grep "RAWKI"
```

## Comparison with Other Tools

| Tool | Purpose | Execution | Server |
|------|---------|-----------|--------|
| TestTool | Function calling test | Local | N/A |
| DmcpTool | D&D dice rolling | MCP | External (Deno) |
| RawkiSearchTool | Web search | MCP | Local Docker |

## Files Created/Modified

### Created
- `app/Services/AI/Tools/Implementations/RawkiSearchTool.php`

### Modified
- `config/tools.php` - Added tool registration and MCP server config
- `config/model_lists/openai_models.php` - Enabled for gpt-5 and gpt-4.1

## Next Steps

1. **Start RAWKI server:**
   ```bash
   cd ../rawki
   docker-compose up -d
   ```

2. **Configure API keys** in RAWKI's `.env`:
   - Add BRAVE_SEARCH_API_KEY or TAVILY_API_KEY

3. **Test the tool:**
   - Ask HAWKI AI: "Search for recent AI news"
   - Check logs for execution details

4. **Enable for more models** (optional):
   - Add `'web_search' => 'mcp'` to other model configs
   - Consider adding to GWDG models with `function_call` strategy

## Troubleshooting

### "MCP server not available"
- Check if RAWKI is running: `docker ps | grep rawki`
- Verify port 8080 is accessible: `curl localhost:8080`
- Check RAWKI logs: `docker logs rawki`

### "BRAVE_SEARCH_API_KEY is not set"
- Add API key to RAWKI's `.env` file
- Restart RAWKI server

### No search results
- Check RAWKI server logs for API errors
- Verify API key is valid and has quota
- Try different search provider

### Tool not found
- Clear Laravel cache: `php artisan cache:clear`
- Check tool is registered in `config/tools.php`
- Verify class namespace and file location

## Benefits

1. **Up-to-date information** - AI can access current web data
2. **Multiple providers** - Fallback between Brave and Tavily
3. **Local integration** - RAWKI runs alongside HAWKI
4. **Flexible configuration** - Easy to switch providers or adjust limits
5. **Full logging** - Complete audit trail of searches
6. **Error handling** - Graceful degradation on failures
