<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($this->email).'|'.request()->ip();

        // Without this the login form is an offline password cracker with a
        // network connection.
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '
                    .RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, decaySeconds: 60);

            // Deliberately does not say which of the two was wrong.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(route('dashboard'), navigate: true);
    }
}; ?>

<div class="auth-shell">
    <form wire:submit="login" class="card auth-card">
        <h1 class="auth-title">Vantage</h1>
        <p class="auth-sub">Sign in to your workspace.</p>

        <label class="field">
            <span class="field-label">Email</span>
            <input type="email" wire:model="email" autocomplete="username" autofocus required>
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="field">
            <span class="field-label">Password</span>
            <input type="password" wire:model="password" autocomplete="current-password" required>
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="checkbox">
            <input type="checkbox" wire:model="remember">
            <span>Keep me signed in</span>
        </label>

        <button type="submit" class="btn btn-primary">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>

        <p class="auth-note">
            There is no public sign-up. Create the first account with
            <code>php artisan vantage:install</code>.
        </p>
    </form>
</div>
