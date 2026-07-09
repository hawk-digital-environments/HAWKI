<?php
declare(strict_types=1);


namespace App\Collections;


use App\Models\Ai\AiTool;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, AiTool>
 */
class AiToolCollection extends Collection
{
    /**
     * Checks if the collection contains a tool with the given name.
     * References the 'name' field of the AiTool model, which is unique per tool.
     */
    public function hasWithName(string $name): bool
    {
        return $this->contains(fn(AiTool $tool) => $tool->name === $name);
    }

    /**
     * Retrieves the tool with the given name from the collection.
     * Returns null if no such tool exists.
     */
    public function getWithName(string $name): AiTool|null
    {
        return $this->first(fn(AiTool $tool) => $tool->name === $name);
    }
}
