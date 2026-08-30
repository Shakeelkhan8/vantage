<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which workspace this user is currently looking at. A UI
            // preference, not membership — membership is workspace_user, and
            // authorisation must always be checked against that.
            $table->foreignId('current_workspace_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('workspaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_workspace_id');
        });
    }
};
