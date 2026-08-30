<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Workspace;
use App\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Marks a model as tenant-owned: every query is constrained to the active
 * workspace, and every insert is stamped with it.
 *
 * Writing without a workspace context throws rather than inserting a row with
 * a null workspace_id, which would be invisible to every tenant and belong to
 * none of them.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (self $model): void {
            if ($model->workspace_id !== null) {
                return;
            }

            $workspaceId = current_workspace_id();

            if ($workspaceId === null) {
                throw new RuntimeException(
                    'Cannot create '.static::class.' without an active workspace. '
                    .'Set one with WorkspaceContext::run() first.'
                );
            }

            $model->workspace_id = $workspaceId;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Escape the tenant scope.
     *
     * Only for genuinely cross-tenant work — platform administration,
     * maintenance commands, aggregate reporting. Never reachable from a
     * request handler.
     */
    public function scopeAcrossWorkspaces(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WorkspaceScope::class);
    }
}
