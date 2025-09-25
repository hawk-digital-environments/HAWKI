<?php

namespace App\Orchid\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait OrchidLoggingTrait
{
    /**
     * Log a screen operation with structured data.
     */
    protected function logScreenOperation(string $operation, string $status, array $data = [], string $level = 'info'): void
    {
        $logData = [
            'screen' => static::class,
            'operation' => $operation,
            'status' => $status,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        // Merge additional data
        $logData = array_merge($logData, $data);

        $message = "Screen operation '{$operation}' {$status}";

        Log::{$level}($message, $logData);
    }

    /**
     * Log a model operation with structured data.
     *
     * @param  mixed  $modelId
     */
    protected function logModelOperation(string $action, string $modelType, $modelId, string $status, array $data = [], string $level = 'info'): void
    {
        $logData = [
            'screen' => static::class,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'status' => $status,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        // Merge additional data
        $logData = array_merge($logData, $data);

        // Build message with model identifier if available
        $message = "Model {$action} for {$modelType} (ID: {$modelId})";

        // Add model identifier to message if available in data
        if (isset($data['model_identifier']) && ! empty($data['model_identifier'])) {
            $message = "Model {$action} for {$modelType} '{$data['model_identifier']}' (ID: {$modelId})";
        }

        $message .= " {$status}";

        Log::{$level}($message, $logData);
    }

    /**
     * Log a provider operation with structured data.
     *
     * @param  mixed  $providerId
     */
    protected function logProviderOperation(string $action, string $providerName, $providerId, string $status, array $data = [], string $level = 'info'): void
    {
        $logData = [
            'screen' => static::class,
            'action' => $action,
            'provider_name' => $providerName,
            'provider_id' => $providerId,
            'status' => $status,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        // Merge additional data
        $logData = array_merge($logData, $data);

        $message = "Provider {$action} for '{$providerName}' (ID: {$providerId}) {$status}";

        Log::{$level}($message, $logData);
    }

    /**
     * Log a batch operation with structured data.
     */
    protected function logBatchOperation(string $operation, string $type, array $results, string $level = 'info'): void
    {
        $logData = [
            'screen' => static::class,
            'operation' => $operation,
            'type' => $type,
            'results' => $results,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        // Calculate total from numeric values only
        $total = 0;
        foreach ($results as $key => $value) {
            if (is_numeric($value)) {
                $total += (int) $value;
            }
        }
        $message = "Batch {$operation} for {$type} completed - Total: {$total}";

        Log::{$level}($message, $logData);
    }

    /**
     * Log an info message with structured data.
     */
    protected function logInfo(string $operation, array $context = []): void
    {
        $logData = [
            'screen' => static::class,
            'operation' => $operation,
            'context' => $context,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        $message = "Screen operation '{$operation}' completed successfully";

        Log::info($message, $logData);
    }

    /**
     * Log a warning with structured data.
     */
    protected function logWarning(string $operation, array $context = []): void
    {
        $logData = [
            'screen' => static::class,
            'operation' => $operation,
            'context' => $context,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        $message = "Screen operation '{$operation}' completed with warnings";

        Log::warning($message, $logData);
    }

    /**
     * Log an error with structured data.
     */
    protected function logError(string $operation, \Exception $exception, array $context = []): void
    {
        $logData = [
            'screen' => static::class,
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'context' => $context,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        $message = "Screen operation '{$operation}' failed with error";

        Log::error($message, $logData);
    }
}
