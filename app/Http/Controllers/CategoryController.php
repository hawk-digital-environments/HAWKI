<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Category\CategoryResource;
use App\Services\Assistant\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    use ApiTrait;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $categories = $this->categoryService->list($this->pageSize());

        return CategoryResource::collection(
            $this->applyPagination($categories)
        );
    }
}
