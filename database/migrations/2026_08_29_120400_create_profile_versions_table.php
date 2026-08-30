<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Versions are immutable. Scores cache against a version id, so
        // editing one in place would silently invalidate every cached score
        // that pointed at it — or worse, leave them pointing at content that
        // no longer exists.
        Schema::create('profile_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('source_type');            // upload | paste
            $table->string('original_filename')->nullable();
            $table->longText('raw_text');
            $table->jsonb('structured')->nullable();  // filled in later by extraction
            $table->char('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['profile_id', 'version']);
            // Re-uploading an unchanged CV should reuse the existing version
            // rather than mint a new one and re-pay for every score.
            $table->unique(['profile_id', 'content_hash']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('name')
                ->constrained('profile_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('profile_versions');
    }
};
