<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiConvAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(FileStorageService $fileStorage): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . max(1, intdiv($fileStorage->getMaxFileSize(), 1024)),
            ],
        ];
    }
}
