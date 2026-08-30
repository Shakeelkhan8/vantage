<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Models\Profile;
use App\Models\ProfileVersion;
use App\Models\Workspace;
use App\Profiles\ProfileVersionWriter;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileVersioningTest extends TestCase
{
    use RefreshDatabase;

    private Profile $profile;

    private ProfileVersionWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::factory()->create();
        $this->profile = Profile::factory()->for($workspace)->create();
        $this->writer = app(ProfileVersionWriter::class);

        app(WorkspaceContext::class)->set($workspace->id);
    }

    public function test_the_first_write_creates_version_one_and_becomes_current(): void
    {
        $version = $this->writer->write($this->profile, 'Senior Backend Engineer, five years.');

        $this->assertSame(1, $version->version);
        $this->assertSame($version->id, $this->profile->fresh()->current_version_id);
    }

    public function test_changed_content_creates_a_new_version_and_moves_current(): void
    {
        $first = $this->writer->write($this->profile, 'Original CV text.');
        $second = $this->writer->write($this->profile, 'Rewritten CV text, now with a ledger.');

        $this->assertSame(2, $second->version);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($second->id, $this->profile->fresh()->current_version_id);
        $this->assertSame(2, ProfileVersion::query()->where('profile_id', $this->profile->id)->count());
    }

    public function test_identical_content_reuses_the_existing_version(): void
    {
        $first = $this->writer->write($this->profile, 'Unchanged CV text.');
        $second = $this->writer->write($this->profile, 'Unchanged CV text.');

        // This is the whole point of hashing: scores cache against a version
        // id, so minting a new one here would discard every cached score.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProfileVersion::query()->where('profile_id', $this->profile->id)->count());
    }

    public function test_whitespace_differences_do_not_count_as_a_change(): void
    {
        $first = $this->writer->write($this->profile, "Senior Backend Engineer\n\nFive years.");
        $second = $this->writer->write($this->profile, "Senior Backend Engineer    \n\n\n  Five years.  ");

        $this->assertSame($first->id, $second->id);
    }

    public function test_older_versions_survive_intact(): void
    {
        $first = $this->writer->write($this->profile, 'Version one body text.');
        $this->writer->write($this->profile, 'Version two body text.');

        $this->assertSame('Version one body text.', $first->fresh()->raw_text);
    }

    public function test_versions_are_numbered_per_profile(): void
    {
        $other = Profile::factory()->for($this->profile->workspace)->create();

        $this->writer->write($this->profile, 'Profile one CV.');
        $otherFirst = $this->writer->write($other, 'Profile two CV.');

        $this->assertSame(1, $otherFirst->version);
    }
}
