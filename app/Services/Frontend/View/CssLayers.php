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
 *
 * The order must stay in sync with the statement in `resources/css/app.css`:
 * `legacy` sits directly above `reset` so the new design system (`tokens`,
 * `base`) overrides legacy Blade styles, while `components`/`utilities` and
 * unlayered Svelte scoped styles still win over everything.
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
        // Previous ordering on this branch (legacy ABOVE base, so legacy Blade
        // pages kept their own look over the design system) — restore to revert:
        //   @layer reset, tokens, base, legacy, components, utilities;
        return <<<'blade'
<style>@layer reset, legacy, tokens, base, components, utilities;</style>
blade;
    }
}
