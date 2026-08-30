<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first user and workspace.
 *
 * There is no public registration — this is a single-operator tool, and an
 * open signup form on a public deployment is an invitation rather than a
 * feature.
 */
class InstallVantage extends Command
{
    protected $signature = 'vantage:install
                            {--name= : Your name}
                            {--email= : Login email}
                            {--workspace=Personal : Workspace name}';

    protected $description = 'Create the first user and workspace';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Your name');
        $email = $this->option('email') ?: $this->ask('Login email');
        $workspaceName = $this->option('workspace') ?: 'Personal';
        $password = $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', Password::min(12)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $workspaceName): void {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $workspace = Workspace::query()->create([
                'name' => $workspaceName,
                'slug' => $this->uniqueSlug($workspaceName),
            ]);

            $workspace->users()->attach($user, ['role' => 'owner']);

            $user->forceFill(['current_workspace_id' => $workspace->getKey()])->save();

            $this->components->info("Created user [{$email}] in workspace [{$workspace->name}].");
        });

        return self::SUCCESS;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
