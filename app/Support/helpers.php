<?php

declare(strict_types=1);

use App\Models\Workspace;
use App\Support\WorkspaceContext;

if (! function_exists('current_workspace_id')) {
    /**
     * The active workspace id, or null when there is no workspace context.
     *
     * Null is not "all workspaces" — the global scope treats it as "nothing".
     */
    function current_workspace_id(): ?int
    {
        return app(WorkspaceContext::class)->id();
    }
}

if (! function_exists('current_workspace')) {
    function current_workspace(): ?Workspace
    {
        $id = current_workspace_id();

        return $id === null ? null : Workspace::find($id);
    }
}
