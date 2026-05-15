<?php

namespace App\Services;

use App\Models\AdminAuditLog;

class AuditLogService
{
    public function log(
        string $entityType,
        string|int|null $entityId,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?string $notes = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'actor_id'    => auth()->id(),
            'entity_type' => $entityType,
            'entity_id'   => (string) $entityId,
            'action'      => $action,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'notes'       => $notes,
            'created_at'  => now(),
        ]);
    }

    public function logTokenChange(string $tokenId, array $before, array $after, string $action): AdminAuditLog
    {
        return $this->log('Token', $tokenId, $action, $before, $after);
    }

    public function logUserChange(int $userId, array $before, array $after, string $action): AdminAuditLog
    {
        return $this->log('User', $userId, $action, $before, $after);
    }
}
