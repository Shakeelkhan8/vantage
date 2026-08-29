<?php

declare(strict_types=1);

namespace App\Sources\Contracts;

use App\Sources\RawPosting;
use App\Sources\SourceCursor;

/**
 * A place job postings come from.
 *
 * Every source — an ATS board, an aggregator API, a parsed alert email, a
 * pasted URL — implements this and nothing else. The ingestion pipeline does
 * not know or care which is which, so adding a source never means touching
 * dedupe, filtering or scoring.
 */
interface JobSource
{
    /**
     * Stable identifier, used as the `sources.key` column and in cursors.
     */
    public function key(): string;

    /**
     * Human-readable name for the dashboard.
     */
    public function label(): string;

    /**
     * Whether this source can resume from a cursor instead of refetching
     * everything. Aggregators generally can; a pasted URL cannot.
     */
    public function supportsIncremental(): bool;

    /**
     * Yield postings as they arrive.
     *
     * Generators, not arrays: an aggregator page can hold thousands of
     * postings and there is no reason to hold them all in memory at once.
     *
     * @return iterable<int, RawPosting>
     */
    public function fetch(SourceCursor $cursor): iterable;
}
