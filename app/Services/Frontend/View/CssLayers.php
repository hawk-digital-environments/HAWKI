<?php

namespace App\Services\Frontend\View;

use Illuminate\View\Component;

/**
 * Blade component `<x-css-layers />` that declares the CSS cascade-layer order.
 *
 * The `@layer` declaration must appear in the HTML before any stylesheet that uses
 * these layers; otherwise browsers process layer order on a first-seen basis and
 * the specificity hierarchy becomes unpredictable. Placing this component at the top
 * of the `<head>` guarantees a stable, explicit layer order:
 * `reset → legacy → tokens → base → components → utilities`.
 */
class CssLayers extends Component
{
    /**
     * Renders a `<style>` tag containing the `@layer` order declaration.
     * Returns a raw string (not a view) because no dynamic data is needed.
     */
    public function render(): string
    {
        // Declare the CSS layers in the desired order.
        // This has to be done in the HTML so it is loaded before any of the CSS files that use the layers are loaded.
        return <<<'blade'
<style>@layer reset, legacy, tokens, base, components, utilities;</style>
blade;
    }
}
