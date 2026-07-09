<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation;

use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\ModelInfoApplier;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelInfoApplier::class)]
class ModelInfoApplierTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(): ModelInfoApplier
    {
        return new ModelInfoApplier();
    }

    /**
     * Creates a bare AiModel with stub value objects so enrichment helpers don't hit null.
     *
     * `description` is intentionally stored as an empty array attribute rather than a relation,
     * because `ModelInfoApplier::applyAllInformationToModel` passes `$information->description`
     * directly to `attachAllDescriptions(array|null)`. Eloquent would return a Collection from
     * a lazy-loaded relation, which violates the strict type hint at the call site. Storing
     * an empty array avoids the TypeError without modifying production code.
     */
    private function makeModel(array $attributes = []): AiModel
    {
        // Set description as a raw attribute (empty array) so Eloquent returns [] rather than
        // triggering a lazy-load that yields a Collection incompatible with array|null.
        $defaults = ['description' => []];
        $model = new AiModel(array_merge($defaults, $attributes));

        if (!isset($attributes['flags'])) {
            $model->flags = AiModelFlags::fromArray([]);
        }
        if (!isset($attributes['parameters'])) {
            $model->parameters = AiModelParameters::fromArray([]);
        }
        if (!isset($attributes['native_capabilities'])) {
            $model->native_capabilities = NativeAiModelCapabilities::fromArray([]);
        }
        if (!isset($attributes['input'])) {
            $model->input = AiModelIoMethods::fromArray([]);
        }
        if (!isset($attributes['output'])) {
            $model->output = AiModelIoMethods::fromArray([]);
        }

        return $model;
    }

    private function makeInfoModel(array $attributes = []): AiModel
    {
        return $this->makeModel($attributes);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(ModelInfoApplier::class, $sut);
    }

    // =========================================================================
    // Plain scalar fields
    // =========================================================================

    public function testItCopiesLabelWhenTargetHasNone(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel([]);
        $info = $this->makeInfoModel(['label' => 'GPT-4o']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame('GPT-4o', $target->label);
    }

    public function testItDoesNotOverwriteExistingLabel(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['label' => 'Existing Label']);
        $info = $this->makeInfoModel(['label' => 'New Label']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame('Existing Label', $target->label);
    }

    public function testItCopiesModelTypeWhenTargetHasNone(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel([]);
        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::CHAT]);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame(WellKnownModelTypes::CHAT, $target->model_type);
    }

    public function testItDoesNotOverwriteExistingModelType(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::CHAT]);
        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::IMAGE_GENERATION]);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame(WellKnownModelTypes::CHAT, $target->model_type);
    }

    public function testItCopiesDocumentationUrlWhenTargetHasNone(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel([]);
        $info = $this->makeInfoModel(['documentation_url' => 'https://example.com/docs']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame('https://example.com/docs', $target->documentation_url);
    }

    public function testItDoesNotOverwriteExistingDocumentationUrl(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['documentation_url' => 'https://existing.com']);
        $info = $this->makeInfoModel(['documentation_url' => 'https://new.com']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame('https://existing.com', $target->documentation_url);
    }

    // =========================================================================
    // I/O methods
    // =========================================================================

    public function testItMergesInputMethods(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel();
        $target->input = AiModelIoMethods::fromArray(['text']);

        $info = $this->makeInfoModel();
        $info->input = AiModelIoMethods::fromArray(['text', 'image']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame(['text', 'image'], $target->input->toArray());
    }

    public function testItMergesOutputMethods(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel();
        $target->output = AiModelIoMethods::fromArray(['text']);

        $info = $this->makeInfoModel();
        $info->output = AiModelIoMethods::fromArray(['text', 'audio']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame(['text', 'audio'], $target->output->toArray());
    }

    // =========================================================================
    // Flags
    // =========================================================================

    public function testItMergesFlags(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel();
        $target->flags = AiModelFlags::fromArray(['streaming']);

        $info = $this->makeInfoModel();
        $info->flags = AiModelFlags::fromArray(['streaming', 'reasoning']);

        $sut->applyAllInformationToModel($target, $info);

        $result = $target->flags->toArray();
        static::assertContains('streaming', $result);
        static::assertContains('reasoning', $result);
    }

    public function testItDeduplicatesFlagsOnMerge(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel();
        $target->flags = AiModelFlags::fromArray(['streaming']);

        $info = $this->makeInfoModel();
        $info->flags = AiModelFlags::fromArray(['streaming']);

        $sut->applyAllInformationToModel($target, $info);

        static::assertSame(['streaming'], $target->flags->toArray());
    }

    // =========================================================================
    // Parameters
    // =========================================================================

    public function testItMergesParametersPreservingTargetValues(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel();
        $target->parameters = AiModelParameters::fromArray(['temperature' => 0.5]);

        $info = $this->makeInfoModel();
        $info->parameters = AiModelParameters::fromArray(['temperature' => 0.9, 'top_p' => 0.8]);

        $sut->applyAllInformationToModel($target, $info);

        // target value must win on collision
        static::assertSame(0.5, $target->parameters->getTemperature());
        // new key from info is merged in
        static::assertSame(0.8, $target->parameters->getTopP());
    }

    // =========================================================================
    // Limits — only applied for CHAT type
    // =========================================================================

    public function testItEnrichesLimitsForChatModel(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::CHAT]);
        $target->limits = NullAiModelLimits::fromArray([]);

        $chatLimits = ChatAiModelLimits::fromArray([
            'max_input_tokens' => 128000,
            'max_output_tokens' => 4096,
        ]);

        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::CHAT]);
        $info->limits = $chatLimits;
        $info->pricing = NullPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        static::assertInstanceOf(ChatAiModelLimits::class, $target->limits);
        static::assertSame(128000, $target->limits->getMaxInputTokens());
        static::assertSame(4096, $target->limits->getMaxOutputTokens());
    }

    public function testItSkipsLimitsForNonChatModel(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::IMAGE_GENERATION]);
        $target->limits = NullAiModelLimits::fromArray([]);

        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::IMAGE_GENERATION]);
        $info->limits = ChatAiModelLimits::fromArray(['max_input_tokens' => 9999]);
        $info->pricing = NullPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        // Limits must remain the null object — non-chat models must not receive chat limits.
        static::assertInstanceOf(NullAiModelLimits::class, $target->limits);
    }

    public function testItSkipsLimitsWhenInfoLimitsAreNotChatType(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::CHAT]);
        $target->limits = NullAiModelLimits::fromArray([]);

        // Info carries NullAiModelLimits, not ChatAiModelLimits
        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::CHAT]);
        $info->limits = NullAiModelLimits::fromArray([]);
        $info->pricing = NullPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        // No chat limits were available, so target stays at NullAiModelLimits.
        static::assertInstanceOf(NullAiModelLimits::class, $target->limits);
    }

    // =========================================================================
    // Pricing — only applied for CHAT type
    // =========================================================================

    public function testItSkipsPricingForNonChatModel(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::IMAGE_GENERATION]);
        $target->pricing = NullPricing::fromArray([]);

        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::IMAGE_GENERATION]);
        $info->limits = NullAiModelLimits::fromArray([]);
        $info->pricing = ChatAiModelPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        static::assertInstanceOf(NullPricing::class, $target->pricing);
    }

    public function testItSkipsPricingWhenInfoPricingIsNotChatType(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel(['model_type' => WellKnownModelTypes::CHAT]);
        $target->pricing = NullPricing::fromArray([]);

        $info = $this->makeInfoModel(['model_type' => WellKnownModelTypes::CHAT]);
        $info->limits = NullAiModelLimits::fromArray([]);
        $info->pricing = NullPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        // NullPricing on info side — pricing stays undefined on target.
        static::assertInstanceOf(NullPricing::class, $target->pricing);
    }

    // =========================================================================
    // Empty info model is a no-op
    // =========================================================================

    public function testItIsNoOpWhenInfoModelHasNoData(): void
    {
        $sut = $this->makeSut();

        $target = $this->makeModel([
            'label' => 'My Model',
            'model_type' => WellKnownModelTypes::CHAT,
        ]);
        $target->limits = NullAiModelLimits::fromArray([]);
        $target->pricing = NullPricing::fromArray([]);

        $info = $this->makeInfoModel();
        $info->limits = NullAiModelLimits::fromArray([]);
        $info->pricing = NullPricing::fromArray([]);

        $sut->applyAllInformationToModel($target, $info);

        // Label was set on target and must remain unchanged
        static::assertSame('My Model', $target->label);
        static::assertSame(WellKnownModelTypes::CHAT, $target->model_type);
    }
}
