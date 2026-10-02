<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        // Throttle per email+IP: without it the admin login is an unthrottled password
        // oracle, since every other admin surface is now behind it.
        $throttleKey = 'login:'.mb_strtolower($this->email).'|'.$this->ipAddress();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '
                    .RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], remember: true)) {
            RateLimiter::hit($throttleKey, 60);

            // One generic message for both "no such user" and "wrong password" so the form
            // cannot be used to enumerate which email addresses have accounts.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // New session id on privilege change, so a fixated pre-login session cannot be
        // walked backwards into the admin area. Resolved from the container rather than
        // off the request, which keeps it working under Livewire's synthetic requests.
        session()->regenerate();

        $this->redirectIntended(default: route('admin.lots'));
    }

    public function render(): View
    {
        return view('livewire.auth.login')->layout('components.layouts.app', [
            'title' => 'Sign in | ParkEasy',
        ]);
    }

    private function ipAddress(): string
    {
        return (string) request()->ip();
    }
}
