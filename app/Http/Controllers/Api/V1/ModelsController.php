<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\Formatters\Exceptions\UnknownFormatException;
use App\Services\Ai\Formatters\Models\ModelsFormatterRegistry;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Model catalogue endpoint: `GET /api/hawki/v1/models/{format?}`.
 *
 * The purest spoke of the proxy: no agent, no factory, no stream context — the
 * repository's contextually scoped {@see AiModelRepository::findAll()} (active models
 * available under the current usage type) rendered through a wire-format formatter.
 * An explicit-but-unknown `{format}` segment errors with 400 `unknown_format`.
 */
class ModelsController extends Controller
{
    public function __construct(
        private readonly ModelsFormatterRegistry $formatters,
        private readonly AiModelRepository $modelRepository,
    ) {
    }

    public function __invoke(Request $request, ?string $format = null): Response
    {
        if (null !== $format && !$this->formatters->has($format)) {
            return $this->formatters->resolve()->formatError(UnknownFormatException::forKey($format));
        }

        $models = $this->modelRepository
            ->findAll()
            ->load('provider')
            ->sortBy('model_id', SORT_NATURAL)
            ->values();

        return $this->formatters->resolve($format)->formatModels($models);
    }
}
