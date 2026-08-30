<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'shakeel@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_user_can_sign_in(): void
    {
        $user = $this->user();

        Livewire::test('pages::login')
            ->set('email', $user->email)
            ->set('password', 'correct-horse-battery')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = $this->user();

        Livewire::test('pages::login')
            ->set('email', $user->email)
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_attempts_are_rate_limited(): void
    {
        $user = $this->user();

        foreach (range(1, 5) as $ignored) {
            Livewire::test('pages::login')
                ->set('email', $user->email)
                ->set('password', 'wrong')
                ->call('login');
        }

        // The sixth attempt is refused even though the password is now right,
        // which is what makes this a throttle rather than a speed bump.
        Livewire::test('pages::login')
            ->set('email', $user->email)
            ->set('password', 'correct-horse-battery')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();

        RateLimiter::clear('login:'.$user->email.'|127.0.0.1');
    }

    public function test_a_signed_in_user_can_sign_out(): void
    {
        $this->actingAs($this->user())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
