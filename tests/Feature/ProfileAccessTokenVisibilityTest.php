<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
class ProfileAccessTokenVisibilityTest extends TestCase
{
    /**
     * The profile module view as rendered by HomeController::index for the /profile route.
     */
    private function renderProfileModule(): string
    {
        return view('modules.profile', [
            'slug' => null,
            'userData' => ['convs' => [], 'rooms' => []],
            'activeModule' => 'profile',
            'activeOverlay' => false,
            'announcements' => [],
        ])->render();
    }

    public function testItShowsAccessTokenUiWhenExternalAccessAndUserTokenCreationAreEnabled(): void
    {
        config(['external_access.enabled' => true, 'external_access.allow_user_token' => true]);

        $html = $this->renderProfileModule();

        self::assertStringContainsString('toggleAccessTokensPanel(true)', $html);
        self::assertStringContainsString('id="access-token-modal"', $html);
    }

    public function testItHidesAccessTokenUiWhenUserTokenCreationIsDisabled(): void
    {
        config(['external_access.enabled' => true, 'external_access.allow_user_token' => false]);

        $html = $this->renderProfileModule();

        self::assertStringNotContainsString('toggleAccessTokensPanel(true)', $html);
        self::assertStringNotContainsString('id="access-token-modal"', $html);
    }

    public function testItHidesAccessTokenUiWhenExternalAccessIsDisabled(): void
    {
        config(['external_access.enabled' => false, 'external_access.allow_user_token' => true]);

        $html = $this->renderProfileModule();

        self::assertStringNotContainsString('toggleAccessTokensPanel(true)', $html);
        self::assertStringNotContainsString('id="access-token-modal"', $html);
    }
}
