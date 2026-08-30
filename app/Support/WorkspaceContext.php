<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * The active workspace for the current request, command or queued job.
 *
 * Web requests resolve it from the authenticated user. Queue workers and
 * scheduled commands have no authenticated user at all, so they set it
 * explicitly — which is why this exists rather than reading auth() directly
 * from the global scope.
 */
final class WorkspaceContext
{
    private ?int $workspaceId = null;

    private bool $overridden = false;

    public function id(): ?int
    {
        if ($this->overridden) {
            return $this->workspaceId;
        }

        return Auth::user()?->current_workspace_id;
    }

    public function set(?int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->workspaceId = null;
        $this->overridden = false;
    }

    /**
     * Run a callback with the context pinned to one workspace, restoring
     * whatever was there before — including nothing.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(?int $workspaceId, Closure $callback): mixed
    {
        $previousId = $this->workspaceId;
        $previouslyOverridden = $this->overridden;

        $this->set($workspaceId);

        try {
            return $callback();
        } finally {
            $this->workspaceId = $previousId;
            $this->overridden = $previouslyOverridden;
        }
    }
}
