<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            // Explicit rather than relying on the ambient workspace context,
            // so a test that forgets to set one fails loudly instead of
            // silently attaching to whatever happened to be active.
            'workspace_id' => Workspace::factory(),
            'name' => fake()->jobTitle().' profile',
            'is_default' => false,
        ];
    }
}
