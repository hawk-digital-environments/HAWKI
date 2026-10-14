<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\LoginNews;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginNews::class)]
class LoginNewsTest extends TestCase
{
    public function testEntriesAreTrimmedSortedNewestFirstAndInvalidOnesDropped(): void
    {
        static::assertSame([
            ['date' => '2026-10-14', 'title' => 'Release', 'text' => 'New models.'],
            ['date' => '2026-09-01', 'title' => 'Welcome', 'text' => null],
        ], LoginNews::entries([
            ['date' => '2026-09-01', 'title' => ' Welcome ', 'text' => ''],
            // Unknown fields, such as a link, are dropped.
            ['date' => '2026-10-14', 'title' => 'Release', 'text' => 'New models.', 'link' => 'https://example.edu/news'],
            ['date' => '2026-02-30', 'title' => 'No such day'],
            ['date' => '2026-10-14', 'title' => ''],
            ['date' => '2026-10-14', 'title' => str_repeat('x', 81)],
        ]));
        static::assertSame([], LoginNews::entries('not a list'));
    }

    public function testStrictModeRefusesAnInvalidEntry(): void
    {
        $this->expectException(ValidationException::class);
        LoginNews::entries([['date' => '2026-10-14', 'title' => 'Fine'], ['date' => 'yesterday', 'title' => 'Not fine']], strict: true);
    }
}
