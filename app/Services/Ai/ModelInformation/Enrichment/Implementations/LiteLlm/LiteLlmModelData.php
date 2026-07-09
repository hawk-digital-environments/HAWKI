<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use Illuminate\Support\Arr;

/**
 * Immutable DTO wrapping a single model entry from the LiteLLM model catalog.
 *
 * Raw API data is stored as a plain array and exposed via a dynamic property accessor
 * ({@see __get}) for convenience. All documented `@property-read` entries correspond
 * to known API response fields; additional fields are accessible via {@see get()} or the
 * same dynamic accessor and return null when absent.
 *
 * A model may appear under multiple IDs (e.g. a path-style alias such as
 * `provider/model-name` and a bare `model-name`). {@see mergeWith()} combines two
 * entries for the same physical model, and {@see modelIdMatches()} handles both exact
 * and path-suffix comparison.
 *
 * @see LiteLlmApiDataStore
 * @see StaticLiteLlmDataStore
 *
 * @property-read array|null $metadata
 * @property-read array|null $provider_specific_entry
 * @property-read array|null $search_context_cost_per_query
 * @property-read array|null $supported_endpoints
 * @property-read array|null $supported_modalities
 * @property-read array|null $supported_output_modalities
 * @property-read array|null $supported_regions
 * @property-read array|null $supported_resolutions
 * @property-read array|null $tiered_pricing
 * @property-read bool|null $supports_adaptive_thinking
 * @property-read bool|null $supports_assistant_prefill
 * @property-read bool|null $supports_audio_input
 * @property-read bool|null $supports_audio_output
 * @property-read bool|null $supports_code_execution
 * @property-read bool|null $supports_computer_use
 * @property-read bool|null $supports_embedding_image_input
 * @property-read bool|null $supports_file_search
 * @property-read bool|null $supports_function_calling
 * @property-read bool|null $supports_image_input
 * @property-read bool|null $supports_image_size
 * @property-read bool|null $supports_high_reasoning_effort
 * @property-read bool|null $supports_low_reasoning_effort
 * @property-read bool|null $supports_max_reasoning_effort
 * @property-read bool|null $supports_minimal_reasoning_effort
 * @property-read bool|null $supports_multimodal
 * @property-read bool|null $supports_native_streaming
 * @property-read bool|null $supports_native_structured_output
 * @property-read bool|null $supports_none_reasoning_effort
 * @property-read bool|null $supports_nova_canvas_image_edit
 * @property-read bool|null $supports_output_config
 * @property-read bool|null $supports_parallel_function_calling
 * @property-read bool|null $supports_pdf_input
 * @property-read bool|null $supports_preset
 * @property-read bool|null $supports_prompt_caching
 * @property-read bool|null $supports_reasoning
 * @property-read bool|null $supports_response_schema
 * @property-read bool|null $supports_sampling_params
 * @property-read bool|null $supports_service_tier
 * @property-read bool|null $supports_system_messages
 * @property-read bool|null $supports_tool_choice
 * @property-read bool|null $supports_url_context
 * @property-read bool|null $supports_video_input
 * @property-read bool|null $supports_vision
 * @property-read bool|null $supports_web_search
 * @property-read bool|null $supports_xhigh_reasoning_effort
 * @property-read bool|null $use_openai_responses_path
 * @property-read bool|null $uses_embed_content
 * @property-read float|null $annotation_cost_per_page
 * @property-read float|null $cache_creation_input_audio_token_cost
 * @property-read float|null $cache_creation_input_token_cost
 * @property-read float|null $cache_creation_input_token_cost_above_1hr
 * @property-read float|null $cache_creation_input_token_cost_above_1hr_above_200k_tokens
 * @property-read float|null $cache_creation_input_token_cost_above_200k_tokens
 * @property-read float|null $cache_read_input_audio_token_cost
 * @property-read float|null $cache_read_input_image_token_cost
 * @property-read float|null $cache_read_input_token_cost
 * @property-read float|null $cache_read_input_token_cost_above_200k_tokens
 * @property-read float|null $cache_read_input_token_cost_above_200k_tokens_priority
 * @property-read float|null $cache_read_input_token_cost_above_272k_tokens
 * @property-read float|null $cache_read_input_token_cost_above_272k_tokens_priority
 * @property-read float|null $cache_read_input_token_cost_above_512k_tokens
 * @property-read float|null $cache_read_input_token_cost_batches
 * @property-read float|null $cache_read_input_token_cost_flex
 * @property-read float|null $cache_read_input_token_cost_per_audio_token
 * @property-read float|null $cache_read_input_token_cost_priority
 * @property-read float|null $citation_cost_per_token
 * @property-read float|null $code_interpreter_cost_per_session
 * @property-read float|null $input_cost_per_audio_per_second
 * @property-read float|null $input_cost_per_audio_token
 * @property-read float|null $input_cost_per_audio_token_priority
 * @property-read float|null $input_cost_per_character
 * @property-read float|null $input_cost_per_image
 * @property-read float|null $input_cost_per_image_token
 * @property-read float|null $input_cost_per_pixel
 * @property-read float|null $input_cost_per_query
 * @property-read float|null $input_cost_per_request
 * @property-read float|null $input_cost_per_second
 * @property-read float|null $input_cost_per_token
 * @property-read float|null $input_cost_per_token_above_128k_tokens
 * @property-read float|null $input_cost_per_token_above_200k_tokens
 * @property-read float|null $input_cost_per_token_above_200k_tokens_priority
 * @property-read float|null $input_cost_per_token_above_256k_tokens
 * @property-read float|null $input_cost_per_token_above_272k_tokens
 * @property-read float|null $input_cost_per_token_above_272k_tokens_priority
 * @property-read float|null $input_cost_per_token_above_512k_tokens
 * @property-read float|null $input_cost_per_token_batch_requests
 * @property-read float|null $input_cost_per_token_batches
 * @property-read float|null $input_cost_per_token_cache_hit
 * @property-read float|null $input_cost_per_token_flex
 * @property-read float|null $input_cost_per_token_priority
 * @property-read float|null $input_cost_per_video_per_second
 * @property-read float|null $input_cost_per_video_per_second_above_15s_interval
 * @property-read float|null $input_cost_per_video_per_second_above_8s_interval
 * @property-read float|null $input_dbu_cost_per_token
 * @property-read float|null $max_audio_length_hours
 * @property-read float|null $ocr_cost_per_credit
 * @property-read float|null $ocr_cost_per_page
 * @property-read float|null $output_cost_per_audio_token
 * @property-read float|null $output_cost_per_character
 * @property-read float|null $output_cost_per_image
 * @property-read float|null $output_cost_per_image_above_1024_and_1024_pixels
 * @property-read float|null $output_cost_per_image_above_1024_and_1024_pixels_and_premium_image
 * @property-read float|null $output_cost_per_image_above_512_and_512_pixels
 * @property-read float|null $output_cost_per_image_above_512_and_512_pixels_and_premium_image
 * @property-read float|null $output_cost_per_image_premium_image
 * @property-read float|null $output_cost_per_image_token
 * @property-read float|null $output_cost_per_image_token_batches
 * @property-read float|null $output_cost_per_pixel
 * @property-read float|null $output_cost_per_reasoning_token
 * @property-read float|null $output_cost_per_second
 * @property-read float|null $output_cost_per_second_1080p
 * @property-read float|null $output_cost_per_token
 * @property-read float|null $output_cost_per_token_above_128k_tokens
 * @property-read float|null $output_cost_per_token_above_200k_tokens
 * @property-read float|null $output_cost_per_token_above_200k_tokens_priority
 * @property-read float|null $output_cost_per_token_above_256k_tokens
 * @property-read float|null $output_cost_per_token_above_272k_tokens
 * @property-read float|null $output_cost_per_token_above_272k_tokens_priority
 * @property-read float|null $output_cost_per_token_above_512k_tokens
 * @property-read float|null $output_cost_per_token_batches
 * @property-read float|null $output_cost_per_token_flex
 * @property-read float|null $output_cost_per_token_priority
 * @property-read float|null $output_cost_per_video_per_second
 * @property-read float|null $output_dbu_cost_per_token
 * @property-read float|null $regional_processing_uplift_multiplier_eu
 * @property-read float|null $regional_processing_uplift_multiplier_us
 * @property-read int|null $input_cost_per_audio_per_second_above_128k_tokens
 * @property-read int|null $input_cost_per_character_above_128k_tokens
 * @property-read int|null $input_cost_per_image_above_128k_tokens
 * @property-read int|null $input_cost_per_video_per_second_above_128k_tokens
 * @property-read int|null $max_audio_per_prompt
 * @property-read int|null $max_document_chunks_per_query
 * @property-read int|null $max_images_per_prompt
 * @property-read int|null $max_input_tokens
 * @property-read int|null $max_output_tokens
 * @property-read int|null $max_pdf_size_mb
 * @property-read int|null $max_query_tokens
 * @property-read int|null $max_tokens
 * @property-read int|null $max_tokens_per_document_chunk
 * @property-read int|null $max_video_length
 * @property-read int|null $max_videos_per_prompt
 * @property-read int|null $output_cost_per_character_above_128k_tokens
 * @property-read int|null $output_vector_size
 * @property-read int|null $rpm
 * @property-read int|null $tool_use_system_prompt_tokens
 * @property-read int|null $tpm
 * @property-read string|null $audio_transcription_config
 * @property-read string|null $bedrock_output_config_effort_ceiling
 * @property-read string|null $comment
 * @property-read string|null $deprecation_date
 * @property-read string|null $id
 * @property-read string|null $mode
 * @property-read string|null $object
 * @property-read string|null $provider
 * @property-read string|null $source
 * @property-read string|null $web_search_billing_unit
 */
readonly class LiteLlmModelData
{
    public function __construct(
        public string $modelId,
        public array  $data,
        public array  $otherModelIds = [],
    )
    {
    }

    /** Returns the value for $key from the raw data array, or $default when absent. */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /** Returns true when $key is present in the raw data (even when its value is null). */
    public function has(string $key): bool
    {
        return data_has($this->data, $key);
    }

    /**
     * Returns true when $modelId matches this object's primary ID or any alias.
     *
     * Comparison is exact or path-suffix: `openai/gpt-4o` matches `gpt-4o` and vice versa,
     * because LiteLLM sometimes prefixes model IDs with the provider name.
     */
    public function modelIdMatches(string $modelId): bool
    {
        foreach ([$this->modelId, ...$this->otherModelIds] as $id) {
            $matches = $id === $modelId
                || str_ends_with($id, "/$modelId")
                || str_ends_with($modelId, "/$id");

            if ($matches) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a copy of this object with a different primary model ID.
     *
     * The previous primary ID is added to the alias list so it remains matchable.
     */
    public function withModelId(string $modelId): self
    {
        if ($this->modelId === $modelId) {
            return $this;
        }
        $newOtherModelIds = $this->mergeModelIds($modelId, [$this->modelId]);

        return new self(
            modelId: $modelId,
            otherModelIds: $newOtherModelIds,
            data: $this->data
        );
    }

    /**
     * Returns a new instance that merges $other into this object.
     *
     * This object's data takes precedence on overlapping keys. The primary model ID is
     * preserved from this object; $other's ID is moved to the alias list.
     */
    public function mergeWith(self $other): self
    {
        $otherModelIds = [...$other->otherModelIds, $other->modelId];
        $mergedOtherModelIds = $this->mergeModelIds($this->modelId, $otherModelIds);
        $mergedData = Arr::mergeRecursive($this->data, $other->data);
        return new self(
            modelId: $this->modelId,
            otherModelIds: $mergedOtherModelIds,
            data: $mergedData
        );
    }

    private function mergeModelIds(string $currentModelId, array $otherModelIds): array
    {
        $merged = $this->otherModelIds;
        foreach ($otherModelIds as $otherModelId) {
            if ($currentModelId === $otherModelId || in_array($otherModelId, $merged, true)) {
                continue;
            }
            $merged[] = $otherModelId;
        }
        return $merged;
    }

    /**
     * Creates an instance from a raw API response array.
     *
     * Requires non-empty string `id` and `provider` fields.
     * Throws {@see \InvalidArgumentException} on missing or invalid fields — this is
     * intentional because callers validate the raw data before calling this method.
     */
    public static function fromApiData(array $array): self
    {
        // Invalid argument exceptions are enough for this use case, as it is
        // normally only called internally by one of the lite llm data stores, and the data is expected to be valid.
        if (empty($array['id']) || !is_string($array['id'])) {
            throw new \InvalidArgumentException('Model data is missing a valid "id" field');
        }
        if (empty($array['provider']) || !is_string($array['provider'])) {
            throw new \InvalidArgumentException('Model data is missing a valid "provider" field');
        }
        return new self(
            modelId: $array['id'],
            otherModelIds: [],
            data: $array
        );
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __set(string $name, $value): void
    {
        throw new \LogicException('LiteLlmModelData is immutable');
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
}
