<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Profile;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function context(): WorkspaceContext
    {
        return app(WorkspaceContext::class);
    }

    public function test_queries_only_return_rows_from_the_active_workspace(): void
    {
        $mine = Workspace::factory()->create();
        $theirs = Workspace::factory()->create();

        Profile::factory()->for($mine)->create(['name' => 'Mine']);
        Profile::factory()->for($theirs)->create(['name' => 'Theirs']);

        $this->context()->run($mine->id, function () {
            $profiles = Profile::query()->get();

            $this->assertCount(1, $profiles);
            $this->assertSame('Mine', $profiles->first()->name);
        });
    }

    public function test_a_profile_from_another_workspace_is_not_findable_by_id(): void
    {
        $mine = Workspace::factory()->create();
        $theirs = Workspace::factory()->create();

        $foreign = Profile::factory()->for($theirs)->create();

        $this->context()->run($mine->id, function () use ($foreign) {
            // The important case: guessing an id must not be a way in.
            $this->assertNull(Profile::query()->find($foreign->id));
        });
    }

    public function test_no_workspace_context_returns_nothing_rather_than_everything(): void
    {
        Profile::factory()->for(Workspace::factory())->create();
        Profile::factory()->for(Workspace::factory())->create();

        $this->context()->run(null, function () {
            // A missing tenant must fail closed. If this ever returns rows,
            // every unauthenticated path becomes a cross-tenant read.
            $this->assertCount(0, Profile::query()->get());
        });
    }

    public function test_creating_without_a_workspace_context_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $this->context()->run(null, function () {
            Profile::query()->create(['name' => 'Orphan']);
        });
    }

    public function test_created_records_are_stamped_with_the_active_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $this->context()->run($workspace->id, function () use ($workspace) {
            $profile = Profile::query()->create(['name' => 'Stamped']);

            $this->assertSame($workspace->id, $profile->workspace_id);
        });
    }

    public function test_across_workspaces_bypasses_the_scope(): void
    {
        Profile::factory()->for(Workspace::factory())->create();
        Profile::factory()->for(Workspace::factory())->create();

        $this->context()->run(null, function () {
            $this->assertCount(2, Profile::query()->acrossWorkspaces()->get());
        });
    }

    public function test_context_is_restored_after_running_in_another_workspace(): void
    {
        $outer = Workspace::factory()->create();
        $inner = Workspace::factory()->create();

        $this->context()->run($outer->id, function () use ($inner, $outer) {
            $this->context()->run($inner->id, fn () => $this->assertSame($inner->id, current_workspace_id()));

            $this->assertSame($outer->id, current_workspace_id());
        });
    }
}
