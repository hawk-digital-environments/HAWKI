<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Io\Values;


use App\Services\Ai\Utils\AbstractTagList;

/**
 * Tag list of input or output modalities supported by an AI model.
 *
 * Holds one or more modality strings ('text', 'image', 'video', 'audio'). Well-known
 * modalities have typed convenience methods. The same class is used for both the `input`
 * and `output` JSON columns of {@see \App\Models\Ai\AiModel}.
 *
 * @api
 */
final class AiModelIoMethods extends AbstractTagList
{
    // -------------------------------------------------------
    // Well known methods
    // -------------------------------------------------------

    /**
     * Returns true if the list contains the 'text' method, false otherwise.
     */
    public function hasText(): bool
    {
        return $this->has('text');
    }

    /**
     * Returns true if the list contains the 'image' method, false otherwise.
     */
    public function hasImage(): bool
    {
        return $this->has('image');
    }


    /**
     * Returns true if the list contains the 'video' method, false otherwise.
     */
    public function hasVideo(): bool
    {
        return $this->has('video');
    }

    /**
     * Returns true if the list contains the 'audio' method, false otherwise.
     */
    public function hasAudio(): bool
    {
        return $this->has('audio');
    }
}
