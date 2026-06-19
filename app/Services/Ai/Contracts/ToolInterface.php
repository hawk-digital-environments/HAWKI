<?php
declare(strict_types=1);


namespace App\Services\Ai\Contracts;


use App\Services\Ai\Tools\AbstractTool;

interface ToolInterface
{
    /**
     * This method tells HAWKI, which "capability" this tool provides.
     * You can think of a capability as a "feature" or "functionality" that the tool offers.
     * If your model is linked to this tool you can use {@see AiModel::$capabilities} to check for it.
     * @return string|null
     */
    public function capability(): string|null;

    /**
     * Must return the unique name of this tool, e.g. "get_current_weather".
     * @return string
     * @see AbstractTool::name() for more details and suggestions on how to name your tools.
     */
    public function getName(): string;

    /**
     * Must return the description of this tool, e.g. "Get the current weather in a given location".
     * @return string|null
     * @see AbstractTool::description() for more details on how to write good descriptions.
     */
    public function getDescription(): ?string;
}
