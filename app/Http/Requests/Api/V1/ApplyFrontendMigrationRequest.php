<?php
declare(strict_types=1);


namespace App\Http\Requests\Api\V1;


use Illuminate\Foundation\Http\FormRequest;

class ApplyFrontendMigrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'migration_name' => 'required|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function getMigrationName(): string
    {
        return $this->input('migration_name');
    }
}
