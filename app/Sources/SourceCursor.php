<?php

declare(strict_types=1);

namespace App\Sources;

use Carbon\CarbonImmutable;

/**
 * Where a source got to on its last run.
 *
 * Persisted on `sources.cursor` and handed back on the next fetch so an
 * incremental source can resume instead of re-pulling everything. Sources that
 * cannot resume simply ignore it.
 */
final readonly class SourceCursor
{
    /**
     * @param  array<string, mixed>  $state  Source-specific bookmark — a page
     *                                       number, an opaque token, a last-seen
     *                                       external id. Shape is the adapter's
     *                                       business, not the pipeline's.
     */
    public function __construct(
        public ?CarbonImmutable $lastRunAt = null,
        public array $state = [],
    ) {}

    public static function fresh(): self
    {
        return new self;
    }

    public function isFirstRun(): bool
    {
        return $this->lastRunAt === null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function advance(array $state, ?CarbonImmutable $at = null): self
    {
        return new self(
            lastRunAt: $at ?? CarbonImmutable::now(),
            state: $state,
        );
    }
}
