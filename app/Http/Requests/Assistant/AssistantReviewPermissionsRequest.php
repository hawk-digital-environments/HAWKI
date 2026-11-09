<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The assistant-reviews `actions/permissions` probe: the class-level verdict
 * gating the admin surface. Signed-in members only — the answer discloses
 * org-admin-ness.
 */
class AssistantReviewPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
