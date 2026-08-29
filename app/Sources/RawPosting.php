<?php

declare(strict_types=1);

namespace App\Sources;

use Carbon\CarbonImmutable;

/**
 * One posting exactly as a source handed it over.
 *
 * Deliberately forgiving: almost every field is optional, because aggregators
 * omit different things and a posting missing a salary is still worth scoring.
 * Normalisation and enrichment happen downstream — this type's only job is to
 * get the payload out of the adapter without losing anything.
 */
final readonly class RawPosting
{
    /**
     * @param  array<string, mixed>  $payload  The untouched source response, kept
     *                                         so a parser bug is replayable
     *                                         rather than a data loss.
     */
    public function __construct(
        public string $sourceKey,
        public string $externalId,
        public string $title,
        public ?string $companyName = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?bool $isRemote = null,
        public ?string $applyUrl = null,
        public ?int $salaryMin = null,
        public ?int $salaryMax = null,
        public ?string $salaryCurrency = null,
        public ?string $salaryPeriod = null,
        public ?CarbonImmutable $postedAt = null,
        public array $payload = [],
    ) {}

    /**
     * Identity within a source, before cross-source deduplication runs.
     */
    public function sourceFingerprint(): string
    {
        return hash('xxh128', $this->sourceKey.'|'.$this->externalId);
    }
}
