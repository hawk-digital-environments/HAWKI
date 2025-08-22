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
            $context = $record['context'] ?? [];

            // Prepare message and extract stack trace from multiple possible sources
            $message = $record->message;
            $stackTrace = null;

            // 1) If exception object exists in context, use its trace
            if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
                try {
                    $stackTrace = $record->context['exception']->getTraceAsString();
                } catch (\Throwable $e) {
                    // ignore extraction errors
                }
            }

            // 2) If no stack trace yet, check if message itself contains a stack trace
            if (empty($stackTrace)) {
                // Look for "Stack trace:" marker in message
                if (preg_match('/Stack trace:/i', $message)) {
                    $pos = stripos($message, 'Stack trace:');
                    $stackTrace = trim(substr($message, $pos));
                    $message = trim(substr($message, 0, $pos));
                } 
                // Look for lines starting with #0 in message
                elseif (preg_match('/\n#0\s+/', $message)) {
                    if (preg_match('/\n(#0\s+.*)$/s', $message, $matches)) {
                        $stackTrace = trim($matches[1]);
                        $message = trim(str_replace($matches[0], '', $message));
                    }
                }
            }

            // 3) If still no stack trace, check formatted field as fallback
            if (empty($stackTrace) && !empty($record->formatted)) {
                // Look for exception trace in formatted output
                if (preg_match('/\{"exception":".*?Exception\(code: \d+\): .*? at (.*?)"\}/', $record->formatted, $matches)) {
                    $stackTrace = $matches[0];
                } elseif (strpos($record->formatted, '#0 ') !== false) {
                    $stackTrace = $record->formatted;
                }
            }

            // Final safety: limit stack trace size to avoid huge DB writes
            if (!empty($stackTrace) && strlen($stackTrace) > 200000) {
                $stackTrace = substr($stackTrace, 0, 200000) . "\n...[truncated]";
            }

            Log::create([
                'level' => strtolower($record->level->name),
                'channel' => $record->channel ?? 'default',
                'message' => $message, // Message without stack trace
                'context' => !empty($record->context) ? $record->context : null,
                'stack_trace' => $stackTrace,
                'remote_addr' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'user_id' => Auth::check() ? Auth::id() : null,
                'logged_at' => $record->datetime,
            ]);
        } catch (\Exception $e) {
            // Prevent infinite loops by not logging database errors
            // Write detailed error to file log for debugging
            error_log('Failed to write log to database: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            file_put_contents(storage_path('logs/database_handler_errors.log'), 
                date('Y-m-d H:i:s') . ' - DatabaseHandler Error: ' . $e->getMessage() . 
                ' - Record: ' . json_encode($record->toArray()) . PHP_EOL, 
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
