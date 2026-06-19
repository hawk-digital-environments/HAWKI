<?php

namespace App\Services\Frontend\View;

use Illuminate\View\Component;

class CssLayers extends Component
{
    public function render(): string
    {
        // Declare the CSS layers in the desired order.
        // This has to be done in the HTML so it is loaded before any of the CSS files that use the layers are loaded.
        return <<<'blade'
<style>@layer reset, legacy, tokens, base, components, utilities;</style>
blade;
    }
}
