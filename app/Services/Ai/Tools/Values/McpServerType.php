<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Values;


enum McpServerType: string
{
    case SSE = 'sse';
    case STDIO = 'stdio';
    case HTTP = 'http';
}
