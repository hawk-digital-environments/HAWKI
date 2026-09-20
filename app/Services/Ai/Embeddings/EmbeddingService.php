<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings;

use App\Models\Ai\AiModel;
use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\Embeddings\Factories\VectorizerRegistry;
use App\Services\Ai\Embeddings\Values\EmbeddingItem;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Embeddings\Values\EmbeddingResponse;
use App\Services\Ai\Embeddings\Values\EmbeddingUsageInfo;
use App\Services\Ai\UsageAnalyzerService;
use App\Services\Ai\Values\TokenUsage;
use App\Services\Ai\Values\UsageRecordContext;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;

/**
 * Embeddings-scoped domain service: resolves a vectorizer for an
 * {@see EmbeddingRequest}, executes it, and normalises the vendor result into the
 * embeddings IR.
 *
 * The programmatic entry point for embeddings — HTTP controllers delegate here, and
 * any PHP code (jobs, commands, knowledge-base pipelines) can call {@see send()}
 * directly without the HTTP/formatter layer.
 *
 * Embeddings bypass agents, so the chat usage listeners never fire (expected, not a
 * gap): usage is recorded here, explicitly, from day one. The
 * {@see UsageRecordedEvent} is chat-named but payload-generic; it is reused with
 * `channel: 'embeddings'` rather than relocated.
 *
 * @api
 */
#[Singleton()]
readonly class EmbeddingService
{
    public function __construct(
        private VectorizerRegistry $vectorizerRegistry,
        private UsageAnalyzerService $usageAnalyzer,
        private UsageContext $usageContext,
        private Request $request,
    ) {
    }

    /**
     * Executes the request synchronously and returns the normalised response IR.
     *
     * The vendor response's vectors are input-ordered; {@see EmbeddingResponse::$data}
     * preserves that order index-aligned to {@see EmbeddingRequest::$input}.
     */
    public function send(EmbeddingRequest $request): EmbeddingResponse
    {
        $vectorizer = $this->vectorizerRegistry->getVectorizer($request);
        $vendorResponse = $vectorizer->vectorize($request);
        $model = $vectorizer->model();

        $usage = new EmbeddingUsageInfo(
            promptTokens: $vendorResponse->tokens,
            totalTokens: $vendorResponse->tokens,
        );

        $this->recordUsage($request, $model, $usage);

        return new EmbeddingResponse(
            model: $model->model_id,
            data: array_map(
                static fn (array $embedding, int $index): EmbeddingItem => new EmbeddingItem(
                    embedding: $embedding,
                    index: $index,
                ),
                $vendorResponse->embeddings,
                array_keys($vendorResponse->embeddings),
            ),
            usage: $usage,
        );
    }

    private function recordUsage(EmbeddingRequest $request, AiModel $model, EmbeddingUsageInfo $usage): void
    {
        $tokenUsage = new TokenUsage(
            model: $model,
            promptTokens: $usage->promptTokens,
            completionTokens: 0,
        );

        $this->usageAnalyzer->submitUsageRecord(
            $tokenUsage,
            new UsageRecordContext(
                type: WellKnownUsageTypes::EXTERNAL_APP === $this->usageContext->get() ? 'api' : 'private',
                channel: 'embeddings',
                userId: $this->request->user()?->id,
                userAgent: $this->request->userAgent(),
                formatKey: $request->formatKey,
            ),
        );

        UsageRecordedEvent::dispatch(
            tokenUsage: $tokenUsage,
            usageType: $this->usageContext->get(),
            channel: 'embeddings',
            modelId: $model->model_id,
            formatKey: $request->formatKey,
            userAgent: $this->request->userAgent(),
        );
    }
}
