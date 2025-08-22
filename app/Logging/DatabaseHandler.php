<?php

namespace App\Logging;

use App\Models\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class DatabaseHandler extends AbstractProcessingHandler
{
    /**
     * Write the log record to the database
     */
    protected function write(LogRecord $record): void
    {
        try {
            // Extract stack trace from context or formatted record
            $stackTrace = null;
            if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
                $stackTrace = $record->context['exception']->getTraceAsString();
            } elseif (strpos($record->formatted ?? '', '#0 ') !== false) {
                // Extract stack trace from formatted message
                $stackTrace = $record->formatted;
            }
            
            Log::create([
                'level' => strtolower($record->level->name),
                'channel' => $record->channel ?? 'default',
                'message' => $record->message, // Full message without truncation
                'context' => !empty($record->context) ? $record->context : null,
                'stack_trace' => $stackTrace,
                'remote_addr' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'user_id' => Auth::check() ? Auth::id() : null,
                'logged_at' => $record->datetime,
            ]);
        } catch (\Exception $e) {
            // Prevent infinite loops by not logging database errors
            // You could write to a file log as fallback here
            error_log('Failed to write log to database: ' . $e->getMessage());
        }
    }
}
