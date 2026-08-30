<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a CV.
 *
 * Scores cache against a version id, so these are never edited — a change
 * mints a new version and the old one stays exactly as it was scored.
 *
 * Not tenant-scoped directly: it reaches its workspace through the profile,
 * and a profile is unreachable outside its own workspace.
 */
#[Fillable([
    'profile_id',
    'version',
    'source_type',
    'original_filename',
    'raw_text',
    'structured',
    'content_hash',
])]
class ProfileVersion extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'structured' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public static function hashContent(string $rawText): string
    {
        // Whitespace churn from a re-export is not a content change.
        $normalised = preg_replace('/\s+/u', ' ', trim($rawText)) ?? $rawText;

        return hash('sha256', $normalised);
    }
}
