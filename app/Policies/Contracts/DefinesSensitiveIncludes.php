<?php

declare(strict_types=1);

namespace App\Policies\Contracts;

/**
 * Marks a policy as declaring which relationship include paths (schema field
 * names) are restricted to a privileged tier and therefore require per-model
 * authorization when a client requests them via the `?include` query parameter.
 *
 * The `view{Field}` policy method naming convention is assumed for each
 * declared field (e.g. a field `attachments` is gated by `viewAttachments`).
 */
interface DefinesSensitiveIncludes
{
    /**
     * @return list<string>
     */
    public function sensitiveIncludes(): array;
}
