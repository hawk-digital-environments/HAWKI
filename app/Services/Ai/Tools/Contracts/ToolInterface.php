<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Contracts;


use App\Services\Ai\Tools\AbstractTool;
use Laravel\Ai\Contracts\Tool;

interface ToolInterface extends Tool
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
     * Where possible I would suggest using a "private namespace" for your tools, e.g. "my_app_get_current_weather",
     * to avoid name clashes with other tools.
     * @return string
     * @see AbstractTool::name() for more details and suggestions on how to name your tools.
     */
    public function name(): string;
}
