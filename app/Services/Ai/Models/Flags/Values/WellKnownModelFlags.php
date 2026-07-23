<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Flags\Values;

// Flags are used to indicate special properties of a model
// The FEATURE_ flags should generally not be shown to the user, but can be used to filter models and UI elements.
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;

interface WellKnownModelFlags
{
    /**
     * Indicates that a model is open-source and can be used freely.
     */
    public const string OPEN_WEIGHTS = 'open-weights';
    /**
     * Indicates that a model has a low carbon footprint and is environmentally friendly.
     */
    public const string ECO_FRIENDLY = 'eco-friendly';
    /**
     * Indicates that a model is self-hosted and is run by the university.
     * This also indicates that we don't have issues with data-privacy and that the model is not a black box.
     */
    public const string SELF_HOSTED = 'self-hosted';
    /**
     * Indicates that a model is multi-modal, meaning it can process and generate multiple types of data, such as text, images, and audio.
     */
    public const string MULTI_MODAL = 'multi-modal';
    /**
     * This flag indicates that a model has a strong capability in creative writing, meaning it can generate imaginative and original content, such as stories, poems, and other forms of creative text.
     */
    public const string STRENGTH_CREATIVE_WRITING = 'strength-creative-writing';
    /**
     * This flag indicates that a model has a strong capability in code generation, meaning it can generate code snippets, scripts, and other forms of programming-related content.
     */
    public const string STRENGTH_CODE_GENERATION = 'strength-code-generation';
    /**
     * This flag indicates that a model has a strong capability in mathematical reasoning, meaning it can solve mathematical problems, perform calculations, and understand mathematical concepts.
     */
    public const string STRENGTH_MATH = 'strength-math';
    /**
     * This flag indicates that a model has a strong capability in role-playing, meaning it can simulate characters, scenarios, and dialogues for interactive storytelling or gaming experiences.
     */
    public const string STRENGTH_ROLE_PLAYING = 'strength-role-playing';
    /**
     * This flag indicates that a model has a strong capability in reasoning, meaning it can understand complex problems, make logical inferences, and provide well-thought-out solutions or explanations.
     */
    public const string FEATURE_REASONING = 'strength-reasoning';
    /**
     * Indicates that a model supports streaming output, which allows for real-time generation of text as it is being produced.
     */
    public const string FEATURE_STREAMING = 'feature-streaming';
    /**
     * Indicates that a model has sampling parameters that can be adjusted to control the randomness of the output.
     * Sampling parameters include temperature, top-p, and other parameters that affect the diversity of the model's output.
     * {@see AiModelParameters} for a list of all sampling parameters supported by HAWKI.
     */
    public const string FEATURE_SAMPLING_PARAMETERS = 'feature-sampling-parameters';
    /**
     * Indicates that a model supports a response schema, which allows for structured output in a predefined format.
     * This can be useful for applications that require specific data structures or formats in the model's output.
     */
    public const string FEATURE_RESPONSE_SCHEMA = 'feature-response-schema';
    /**
     * Indicates that a model supports prompt caching, which allows for the reuse of previously generated prompts to improve performance and reduce latency.
     */
    public const string FEATURE_PROMPT_CACHING = 'feature-prompt-caching';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "none", meaning that the model will not perform any reasoning at all.
     */
    public const string FEATURE_REASONING_NONE = 'feature-reasoning-none';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "minimal", meaning that the model will perform minimal reasoning.
     */
    public const string FEATURE_REASONING_MINIMAL = 'feature-reasoning-minimal';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "low", meaning that the model will perform low reasoning.
     */
    public const string FEATURE_REASONING_LOW = 'feature-reasoning-low';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "medium", meaning that the model will perform medium reasoning.
     */
    public const string FEATURE_REASONING_MEDIUM = 'feature-reasoning-medium';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "high", meaning that the model will perform high reasoning.
     */
    public const string FEATURE_REASONING_HIGH = 'feature-reasoning-high';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "xhigh", meaning that the model will perform extra high reasoning.
     */
    public const string FEATURE_REASONING_X_HIGH = 'feature-reasoning-xhigh';
    /**
     * Indicates that the "reasoning" effort of the model can be adjusted AND that it can be set to "max", meaning that the model will perform maximum reasoning.
     */
    public const string FEATURE_REASONING_MAX = 'feature-reasoning-max';
}
