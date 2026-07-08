<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\ModelTypes\Values;


interface WellKnownModelTypes
{
    /**
     * Your default LLM model type. This is the model type when you just want to chat with the AI.
     */
    public const string CHAT = 'chat';
    /**
     * The model type for generating images. This is the model type when you want to generate images from text prompts.
     */
    public const string IMAGE_GENERATION = 'image_generation';
    /**
     * The model type for generating videos. This is the model type when you want to generate videos from text prompts.
     */
    public const string VIDEO_GENERATION = 'video_generation';
}
