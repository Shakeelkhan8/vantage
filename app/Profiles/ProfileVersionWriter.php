<?php

declare(strict_types=1);

namespace App\Profiles;

use App\Models\Profile;
use App\Models\ProfileVersion;
use Illuminate\Support\Facades\DB;

/**
 * Records a new version of a CV, or reuses the existing one when nothing
 * actually changed.
 *
 * The reuse is the point. Scores cache against a version id, so minting a new
 * version for a re-uploaded but identical CV would discard every cached score
 * and re-pay for all of them.
 */
final class ProfileVersionWriter
{
    public function write(
        Profile $profile,
        string $rawText,
        string $sourceType = 'paste',
        ?string $originalFilename = null,
    ): ProfileVersion {
        $hash = ProfileVersion::hashContent($rawText);

        return DB::transaction(function () use ($profile, $rawText, $sourceType, $originalFilename, $hash) {
            // Serialise concurrent uploads for this profile so two of them
            // cannot claim the same version number.
            $locked = Profile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ProfileVersion::query()
                ->where('profile_id', $locked->getKey())
                ->where('content_hash', $hash)
                ->first();

            if ($existing !== null) {
                $this->makeCurrent($locked, $existing);

                return $existing;
            }

            $nextVersion = (int) ProfileVersion::query()
                ->where('profile_id', $locked->getKey())
                ->max('version') + 1;

            $version = ProfileVersion::query()->create([
                'profile_id' => $locked->getKey(),
                'version' => $nextVersion,
                'source_type' => $sourceType,
                'original_filename' => $originalFilename,
                'raw_text' => $rawText,
                'content_hash' => $hash,
            ]);

            $this->makeCurrent($locked, $version);

            return $version;
        });
    }

    private function makeCurrent(Profile $profile, ProfileVersion $version): void
    {
        if ($profile->current_version_id === $version->getKey()) {
            return;
        }

        $profile->forceFill(['current_version_id' => $version->getKey()])->save();
    }
}
