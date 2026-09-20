<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Models;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Formatters\Exceptions\UnknownFormatException;
use App\Services\Ai\Formatters\Models\Implementations\OpenAi\OpenAiModelsFormatter;
use App\Services\Ai\Formatters\Models\Implementations\OpenResponses\OpenResponsesModelsFormatter;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiModelsFormatter::class)]
#[CoversClass(OpenResponsesModelsFormatter::class)]
class ModelsFormatterTest extends TestCase
{
    public function testItMapsCatalogueEntriesToTheOpenAiShape(): void
    {
        $provider = new AiProvider(['provider_id' => 'openAi', 'name' => 'OpenAI']);

        $model = new AiModel([
            'model_id' => 'gpt-4.1-nano',
            'label' => 'GPT-4.1 nano',
            'model_type' => 'chat',
            'active' => true,
        ]);
        $model->created_at = \Carbon\Carbon::parse('2026-01-01 00:00:00 UTC');
        $model->setRelation('provider', $provider);
        $expectedCreated = $model->created_at->getTimestamp();

        $body = (new OpenAiModelsFormatter())->formatModels(new Collection([$model]))->getData(true);

        self::assertSame('list', $body['object']);
        self::assertSame([
            'id' => 'gpt-4.1-nano',
            'object' => 'model',
            'created' => $expectedCreated,
            'owned_by' => 'OpenAI',
            'label' => 'GPT-4.1 nano',
            'model_type' => 'chat',
        ], $body['data'][0]);
    }

    public function testTheOpenResponsesDialectUsesCreatedAtAndFallsBackOnMissingTimestamps(): void
    {
        $model = new AiModel(['model_id' => 'm-1', 'label' => 'M1']);
        $model->setRelation('provider', new AiProvider(['provider_id' => 'p', 'name' => 'P']));

        $body = (new OpenResponsesModelsFormatter())->formatModels(new Collection([$model]))->getData(true);

        self::assertSame('created_at', array_keys($body['data'][0])[2]);
        self::assertSame(0, $body['data'][0]['created_at']);
        self::assertArrayNotHasKey('created', $body['data'][0]);
        self::assertArrayNotHasKey('model_type', $body['data'][0]);
    }

    public function testItRendersErrorsInTheSharedShape(): void
    {
        $response = (new OpenAiModelsFormatter())->formatError(UnknownFormatException::forKey('nope'));

        self::assertSame(400, $response->status());
        self::assertSame('unknown_format', $response->getData(true)['error']['code']);
        self::assertSame('format', $response->getData(true)['error']['param']);
    }
}
