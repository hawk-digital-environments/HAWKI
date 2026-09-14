<?php
declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class AdminAudit
{
    public function record(string $action, string $type, string|int|null $id, array $changes = []): void
    {
        DB::table('admin_audit_log')->insert([
            'user_id' => auth()->id(), 'action' => $action, 'resource_type' => $type,
            'resource_id' => $id, 'changes' => json_encode($this->redact($changes), JSON_THROW_ON_ERROR),
            'ip' => app()->runningInConsole() ? null : request()->ip(), 'created_at' => now(),
        ]);
    }

    private function redact(array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (preg_match('/key|secret|password|token|additional_config|content|prompt|description/i', (string)$key)) {
                $changes[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $changes[$key] = $this->redact($value);
            }
        }
        return $changes;
    }
}
