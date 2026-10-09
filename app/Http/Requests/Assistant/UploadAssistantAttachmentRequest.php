<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * The storage service is resolved via the container in {@see rules()}:
 * action FormRequests are introspected for the OpenAPI spec through the
 * container (see {@see \App\Services\OpenApi\Builders\SchemaBuilder}), so
 * injected rules() dependencies would work too — this class simply keeps
 * the local resolution style it was written with. The app is always booted
 * when these rules are evaluated (live request or spec build).
 */
class UploadAssistantAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('uploadAttachment', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        $fileStorage = app(FileStorageService::class);
        $allowedMimes = implode(',', $fileStorage->getAllowedMimeTypes());
        $maxKb = (int) ceil($fileStorage->getMaxFileSize() / 1024);

        return [
            'file' => "required|file|max:{$maxKb}|mimetypes:{$allowedMimes}",
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->validated('file');

        return $file;
    }
}
