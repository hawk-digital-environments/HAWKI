<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RemoveFavoriteAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('removeFavorite', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
