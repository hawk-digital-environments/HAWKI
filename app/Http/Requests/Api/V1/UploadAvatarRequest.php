<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Storage\AvatarStorageService;
use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(AvatarStorageService $avatarStorage): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:' . max(1, intdiv($avatarStorage->getMaxFileSize(), 1024)),
                'mimetypes:' . implode(',', $avatarStorage->getAllowedMimeTypes()),
            ],
        ];
    }
}
