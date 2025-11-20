<?php
declare(strict_types=1);


namespace App\Services\Frontend;


use App\Models\AppCss;
use App\Utils\AbstractCache;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class CssCache extends AbstractCache
{
    /**
     * Forget all CSS caches
     * @return void
     */
    public function forgetAll(): void
    {
        foreach (AppCss::all() as $css) {
            $this->forget($css->name);
        }
    }
}
