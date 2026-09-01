<?php
declare(strict_types=1);


namespace App\Http\Requests\Api\V1;


use Illuminate\Foundation\Http\FormRequest;

class MarkAnnouncementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'announcement_id' => 'required|integer',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function getAnnouncementId(): int
    {
        return (int)$this->input('announcement_id');
    }
}
