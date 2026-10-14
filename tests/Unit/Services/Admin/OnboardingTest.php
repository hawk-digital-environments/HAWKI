<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\Onboarding;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Onboarding::class)]
class OnboardingTest extends TestCase
{
    public function testStepsKeepTheirOrderAndInvalidOnesAreDropped(): void
    {
        static::assertSame([
            ['type' => 'custom', 'emoji' => '📜', 'title' => 'House rules', 'text' => 'Be kind.', 'action' => null],
            ['type' => 'builtin', 'step' => 'groups', 'emoji' => null, 'title' => null, 'text' => null, 'action' => null],
            ['type' => 'builtin', 'step' => 'encryption', 'emoji' => '🔐', 'title' => 'Sicher', 'text' => null, 'action' => null],
        ], Onboarding::steps([
            ['type' => 'custom', 'emoji' => ' 📜 ', 'title' => ' House rules ', 'text' => 'Be kind.', 'action' => ''],
            ['type' => 'builtin', 'step' => 'groups'],
            // A built-in step appears once, the passkey step is never part of the list.
            ['type' => 'builtin', 'step' => 'groups'],
            ['type' => 'builtin', 'step' => 'passkey'],
            ['type' => 'custom', 'title' => 'No text', 'text' => ''],
            ['type' => 'custom', 'title' => str_repeat('x', 81), 'text' => 'Too long a title.'],
            ['type' => 'unknown'],
            // A built-in step may replace any of its texts; the rest stays translated.
            ['type' => 'builtin', 'step' => 'encryption', 'emoji' => '🔐', 'title' => ' Sicher ', 'text' => ''],
        ]));
        static::assertSame([], Onboarding::steps(null));
    }

    public function testABuiltInStepsTextsHaveTheSameLimitsAsACustomStep(): void
    {
        static::assertSame([], Onboarding::steps([['type' => 'builtin', 'step' => 'groups', 'title' => str_repeat('x', 81)]]));
    }

    public function testStrictModeRefusesAnInvalidStep(): void
    {
        $this->expectException(ValidationException::class);
        Onboarding::steps([['type' => 'builtin', 'step' => 'encryption'], ['type' => 'builtin', 'step' => 'encryption']], strict: true);
    }

    public function testTheDefaultIsTheBuiltInWelcome(): void
    {
        // Read from the config file itself: the running configuration carries whatever the local database overrides.
        $default = (require config_path('hawki.php'))['branding']['onboarding'];

        static::assertSame([['type' => 'builtin', 'step' => 'encryption', 'emoji' => null, 'title' => null, 'text' => null, 'action' => null], ['type' => 'builtin', 'step' => 'groups', 'emoji' => null, 'title' => null, 'text' => null, 'action' => null]], Onboarding::steps($default));
    }
}
