<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;

use App\Services\SyncLog\Handlers\Contract\FullSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;

/**
 * Similar to {@see AbstractSyncLogHandler} but for static resources,
 * i.e. resources that will only be transferred once and never updated again.
 * Examples are languages, default system prompts, etc.
 *
 * @template T
 * @implements SyncLogHandlerInterface<T>
 * @implements FullSyncLogHandlerInterface<T>
 */
abstract class AbstractStaticSyncLogHandler implements SyncLogHandlerInterface, FullSyncLogHandlerInterface
{
    use SyncLogHandlerUtilTrait;
}
