<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Language\LanguageResource;
use App\Services\Assistant\LanguageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssistantLanguageController extends Controller
{
    use ApiTrait;

    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $languages = $this->languageService->list($this->pageSize());

        return LanguageResource::collection($languages);
    }
}
