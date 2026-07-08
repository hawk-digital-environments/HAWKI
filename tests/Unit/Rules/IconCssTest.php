<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\IconCss;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IconCssTest extends TestCase
{
    private const DOTS_PATTERN = 'background-color: #E5E5F7; opacity: 0.8; background: radial-gradient(#444CF7 15%, transparent 16%) 0 0, radial-gradient(#444CF7 15%, transparent 16%) 5px 5px, radial-gradient(#444CF733 15%, transparent 20%) 0 1px, radial-gradient(#444CF733 15%, transparent 20%) 5px 6px; background-size: 10px 10px;';

    #[DataProvider('validInputs')]
    public function test_accepts_valid_css(string $css): void
    {
        $this->assertEmpty($this->validate($css), "Expected acceptance of: {$css}");
    }

    public static function validInputs(): array
    {
        return [
            'dots pattern' => [self::DOTS_PATTERN],
            'linear gradient' => ['background: linear-gradient(135deg, rgb(73,66,215), rgb(101,34,195));'],
            'hex color' => ['background-color: #ff0000;'],
            'named color' => ['background-color: red;'],
            'opacity' => ['opacity: 0.5;'],
            'multiple properties' => ['color: #fff; background-color: #000; padding: 4px;'],
            'no trailing semicolon' => ['background-color: red'],
            'font-family with quotes' => ['font-family: "Arial", sans-serif;'],
            'border radius' => ['border-radius: 8px;'],
            'rgba' => ['background-color: rgba(0, 0, 0, 0.4);'],
            'repeating conic' => ['background: repeating-conic-gradient(#444cf7 0% 25%, #e5e5f7 0% 50%) 50% / 10px 10px;'],
            'relative url absolute path' => ['background-image: url(/storage/assistant_avatars/foo.png);'],
            'relative url double quoted' => ['background: url("/storage/x.png");'],
            'relative url single quoted' => ["background: url('x.png');"],
            'empty string' => [''],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_css(string $css): void
    {
        $this->assertNotEmpty($this->validate($css), "Expected rejection of: {$css}");
    }

    public static function invalidInputs(): array
    {
        return [
            'external https url' => ['background: url(https://evil.test/x.png);'],
            'external http url' => ['background: url(http://evil.test/x.png);'],
            'protocol-relative url' => ['background: url(//evil.test/x.png);'],
            'data uri' => ['background: url(data:image/png;base64,AAAA);'],
            'empty url' => ['background: url();'],
            'image()' => ['background: image(x);'],
            'image-set()' => ['background: image-set(x);'],
            '@import' => ['@import url("x");'],
            'disallowed property position' => ['position: fixed;'],
            'disallowed property z-index' => ['z-index: 9999;'],
            'disallowed property display' => ['display: none;'],
            'disallowed property visibility' => ['visibility: hidden;'],
            'disallowed property pointer-events' => ['pointer-events: none;'],
            'disallowed property transform' => ['transform: translateX(10px);'],
            'expression()' => ['color: expression(alert(1));'],
            'javascript scheme' => ['background: javascript:alert(1);'],
            'vbscript scheme' => ['background: vbscript:foo;'],
            'html markup close' => ['</style>'],
            'html markup open' => ['<style>'],
            'braces' => ['{ color: red; }'],
            'comment block' => ['/* comment */ background: red;'],
            'ampersand obfuscation' => ['background: &#x75;rl(x);'],
            'empty value' => ['background:;'],
            'no colon' => ['red'],
            'too long' => [str_repeat('a', 1001)],
            'trailing junk after gradient' => ['background: red; position: absolute;'],
        ];
    }

    /**
     * @return string[] captured failure messages (empty means the value passed).
     */
    private function validate(mixed $value): array
    {
        $failures = [];

        (new IconCss)->validate('icon_css', $value, static function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        return $failures;
    }
}
