<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\View;

use App\Services\Frontend\View\SvelteComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SvelteComponent::class)]
class SvelteComponentTest extends TestCase
{
    // =========================================================================
    // render — element structure
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new SvelteComponent('MyComponent');
        static::assertInstanceOf(SvelteComponent::class, $sut);
    }

    public function testItRendersASvelteSnippetElement(): void
    {
        $html = $this->render('MyComponent');
        static::assertStringStartsWith('<svelte-snippet', $html);
        static::assertStringEndsWith('></svelte-snippet>', $html);
    }

    public function testItRendersTheTypeAttribute(): void
    {
        $html = $this->render('InputModelSelector');
        static::assertStringContainsString('type="InputModelSelector"', $html);
    }

    public function testItRendersEmptyPropsWhenNoneProvided(): void
    {
        // PHP json_encodes an empty PHP array as "[]", not "{}".
        $html = $this->render('MyComponent');
        static::assertStringContainsString('props="[]"', $html);
    }

    // =========================================================================
    // render — props encoding
    // =========================================================================

    public function testItJsonEncodesProps(): void
    {
        $html = $this->render('MyComponent', ['chatId' => 42, 'readonly' => true]);
        $expectedEncoded = htmlspecialchars(json_encode(['chatId' => 42, 'readonly' => true]), ENT_QUOTES, 'UTF-8');
        static::assertStringContainsString('props="' . $expectedEncoded . '"', $html);
    }

    public function testItHtmlEscapesPropsValues(): void
    {
        // Props containing quotes or angle brackets must be escaped to prevent XSS.
        $html = $this->render('MyComponent', ['value' => '<script>alert(1)</script>']);
        static::assertStringNotContainsString('<script>', $html);
        static::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testItHtmlEscapesDoubleQuotesInProps(): void
    {
        // The value's double quotes are JSON-encoded as \" and then each " becomes &quot;
        // so the raw ' "hello" ' string never appears unescaped in the HTML attribute.
        $html = $this->render('MyComponent', ['label' => 'say "hello"']);
        static::assertStringNotContainsString('"hello"', $html);
        static::assertStringContainsString('\&quot;hello\&quot;', $html);
    }

    // =========================================================================
    // render — extra Blade attributes
    // =========================================================================

    public function testItForwardsExtraHtmlAttributes(): void
    {
        $html = $this->render('MyComponent', [], ['class' => 'my-class']);
        static::assertStringContainsString('class="my-class"', $html);
    }

    public function testItForwardsMultipleExtraAttributes(): void
    {
        $html = $this->render('MyComponent', [], ['class' => 'foo', 'id' => 'bar']);
        static::assertStringContainsString('class="foo"', $html);
        static::assertStringContainsString('id="bar"', $html);
    }

    public function testItConvertsExtraAttributeKeysToKebabCase(): void
    {
        $html = $this->render('MyComponent', [], ['dataFoo' => 'bar']);
        static::assertStringContainsString('data-foo="bar"', $html);
    }

    public function testItHtmlEscapesExtraAttributeValues(): void
    {
        $html = $this->render('MyComponent', [], ['title' => '<b>unsafe</b>']);
        static::assertStringNotContainsString('<b>', $html);
        static::assertStringContainsString('&lt;b&gt;', $html);
    }

    // =========================================================================
    // render — attribute key casing
    // =========================================================================

    public function testItConvertsTypeToKebabCase(): void
    {
        // 'type' is already lowercase, so it stays unchanged.
        $html = $this->render('MyComponent');
        static::assertStringContainsString('type="', $html);
    }

    public function testItConvertsPropsKeyToKebabCase(): void
    {
        // 'props' is already lowercase, so it stays unchanged.
        $html = $this->render('MyComponent');
        static::assertStringContainsString('props="', $html);
    }

    // =========================================================================
    // render — returns a callable
    // =========================================================================

    public function testItRenderReturnsAClosure(): void
    {
        $sut = new SvelteComponent('MyComponent');
        $sut->withAttributes([]);
        static::assertInstanceOf(\Closure::class, $sut->render());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function render(string $type, array $props = [], array $attributes = []): string
    {
        $sut = new SvelteComponent($type, $props);
        $sut->withAttributes($attributes);
        $closure = $sut->render();
        return $closure();
    }
}
