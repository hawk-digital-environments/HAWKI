<?php

namespace App\Orchid\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Orchid\Support\Facades\Toast;

trait OrchidImportTrait
{
    /**
     * Validate and decode JSON file for import.
     *
     * @param UploadedFile $file
     * @param string $expectedStructure Description of expected structure for error messages
     * @return array|null Returns decoded data or null if invalid
     */
    protected function validateAndDecodeJsonFile(UploadedFile $file, string $expectedStructure = 'an array'): ?array
    {
        try {
            $jsonContent = file_get_contents($file->getPathname());
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Invalid JSON file format: ' . json_last_error_msg());
                return null;
            }

            if (!is_array($data)) {
                Toast::error("JSON file must contain {$expectedStructure}.");
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Toast::error('Error reading file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate file upload for import.
     *
     * @param Request $request
     * @param string $fileFieldName
     * @param string $acceptedExtensions
     * @param int $maxSizeKb
     * @return UploadedFile|null Returns the uploaded file or null if validation fails
     */
    protected function validateImportFile(Request $request, string $fileFieldName = 'json_file', string $acceptedExtensions = 'json', int $maxSizeKb = 2048): ?UploadedFile
    {
        try {
            // Use extensions validation instead of mimes to avoid MIME type issues
            $request->validate([
                $fileFieldName => "required|file|extensions:{$acceptedExtensions}|max:{$maxSizeKb}",
            ]);
            
            return $request->file($fileFieldName);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->first($fileFieldName);
            $allErrors = $e->validator->errors()->toArray();
            
            // Analyze specific validation failure reasons
            $uploadedFile = $request->file($fileFieldName);
            $fileSizeKb = $uploadedFile ? round($uploadedFile->getSize() / 1024, 2) : 0;
            $failureReasons = [];
            
            if ($uploadedFile) {
                // Check size limit
                if ($fileSizeKb > $maxSizeKb) {
                    $failureReasons[] = "File too large: {$fileSizeKb} KB (max: {$maxSizeKb} KB)";
                }
                
                // Check file extension
                $extension = $uploadedFile->getClientOriginalExtension();
                $acceptedExtensionsList = explode(',', $acceptedExtensions);
                if (!in_array($extension, $acceptedExtensionsList)) {
                    $failureReasons[] = "File extension '{$extension}' not accepted. Allowed: " . implode(', ', $acceptedExtensionsList);
                }
            } else {
                $failureReasons[] = "No file uploaded";
            }
            
            // Log validation failure details
            \Log::error('File upload validation failed', [
                'field_name' => $fileFieldName,
                'accepted_extensions' => $acceptedExtensions,
                'max_size_kb' => $maxSizeKb,
                'validation_error' => $errors,
                'all_errors' => $allErrors,
                'failure_reasons' => $failureReasons,
                'file_info' => $uploadedFile ? [
                    'name' => $uploadedFile->getClientOriginalName(),
                    'extension' => $uploadedFile->getClientOriginalExtension(),
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size_bytes' => $uploadedFile->getSize(),
                    'size_kb' => $fileSizeKb
                ] : 'No file'
            ]);
            
            // Provide more specific error message
            if (!empty($failureReasons)) {
                Toast::error('File validation failed: ' . implode('; ', $failureReasons));
            } else {
                Toast::error($errors ?: 'File validation failed.');
            }
            
            return null;
        }
    }

    /**
     * Display import results with standardized messaging.
     *
     * @param array $results
     * @param string $itemType
     * @param array $errors
     * @return void
     */
    protected function displayImportResults(array $results, string $itemType = 'items', array $errors = []): void
    {
        $imported = $results['imported'] ?? 0;
        $updated = $results['updated'] ?? 0;
        $skipped = $results['skipped'] ?? 0;
        
        $message = "Import completed: ";
        $messageParts = [];
        
        if ($imported > 0) {
            $messageParts[] = "{$imported} {$itemType} imported";
        }
        if ($updated > 0) {
            $messageParts[] = "{$updated} {$itemType} updated";
        }
        if ($skipped > 0) {
            $messageParts[] = "{$skipped} {$itemType} skipped";
        }
        
        $message .= implode(', ', $messageParts);
        
        if ($imported > 0 || $updated > 0) {
            Toast::success($message);
        } else {
            Toast::warning($message);
        }

        // Display errors if any
        if (!empty($errors)) {
            $errorMessage = "Errors during import:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $errorMessage .= "\n... and " . (count($errors) - 5) . " more errors";
            }
            Toast::error($errorMessage);
        }
    }

    /**
     * Filter import data to only include allowed keys.
     *
     * @param array $data
     * @param array $allowedKeys
     * @return array
     */
    protected function filterImportData(array $data, array $allowedKeys): array
    {
        return array_intersect_key($data, array_flip($allowedKeys));
    }
}
