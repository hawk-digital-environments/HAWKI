<?php
declare(strict_types=1);


namespace App\Services\Frontend\View;


use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Blade component that renders a `<svelte-snippet>` custom element.
 *
 * This is the primary bridge between Blade templates and Svelte components.
 * It lets you drop a fully interactive Svelte component into any Blade view
 * without writing any JavaScript.
 *
 * ## Basic usage
 *
 * ```blade
 * <x-svelte type="InputModelSelector" />
 * ```
 *
 * ## Passing props
 *
 * Use the `:props` binding to pass a PHP array — it will be JSON-encoded
 * automatically. Any other attributes (e.g. `class`, `id`, `data-*`) are
 * forwarded verbatim to the rendered HTML element.
 *
 * ```blade
 * <x-svelte
 *     type="InputModelSelector"
 *     :props="['chatId' => 42, 'readonly' => true]"
 *     class="my-class"
 * />
 * ```
 *
 * Renders as:
 *
 * ```html
 * <svelte-snippet
 *     type="InputModelSelector"
 *     props="{&quot;chatId&quot;:42,&quot;readonly&quot;:true}"
 *     class="my-class"
 * ></svelte-snippet>
 * ```
 *
 * ## Adding a new snippet
 *
 * Create a `.svelte` file in `resources/js/svelte/snippets/` and use its
 * filename (without the extension) as the `type` attribute:
 *
 * ```
 * resources/js/svelte/snippets/MyWidget.svelte  →  type="MyWidget"
 * ```
 *
 * @see ./resources/js/svelte/svelteSnippetLoader.ts for the client-side
 * implementation that mounts and tears down these components automatically.
 */
class SvelteComponent extends Component
{
    public function __construct(
        /**
         * Filename (without extension) of the Svelte snippet to mount, e.g.
         * `InputModelSelector` for `snippets/InputModelSelector.svelte`.
         */
        private readonly string $type,
        /**
         * Props forwarded to the Svelte component as a JSON-encoded `props`
         * attribute. Use `:props="[...]"` in Blade to pass a PHP array.
         * Other HTML attributes (class, id, data-*, …) are passed separately
         * as normal Blade attributes and are not included here.
         */
        private readonly array  $props = []
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function render(): \Closure
    {
        return function () {
            $attributesString = $this->attributesForTag();
            return "<svelte-snippet $attributesString></svelte-snippet>";
        };
    }

    private function attributesForTag(): string
    {
        $attr = array_merge(
            [
                'type' => $this->type,
                'props' => $this->props
            ],
            $this->attributes->all(),
        );

        $attributes = [];
        foreach ($attr as $key => $value) {
            $kebabKey = Str::kebab($key);
            if (is_array($value) || is_object($value)) {
                $escapedValue = htmlspecialchars(json_encode($value, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
            } else {
                $escapedValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }
            $attributes[] = "$kebabKey=\"$escapedValue\"";
        }

        return implode(' ', $attributes);
    }
}
