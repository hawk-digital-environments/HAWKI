<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Language\LanguageResource;
use App\Services\Assistant\LanguageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LanguageController extends Controller
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $languages = $this->languageService->list();

        return LanguageResource::collection($languages);
    }
}
