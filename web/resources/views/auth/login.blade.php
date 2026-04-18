<x-guest-layout>
    <div class="auth-header">
        <div class="eyebrow">Secure Access</div>
        <h1 class="auth-title">Sign in to HR Command</h1>
        <p class="auth-subtitle">Secure payroll and staffing operations access for Matchpointe Group.</p>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="auth-actions">
            <label for="remember_me" class="checkbox-row">
                <input id="remember_me" type="checkbox" name="remember" style="accent-color:var(--accent-400)">
                {{ __('Remember me') }}
            </label>

            @if (Route::has('password.request'))
                <a class="auth-footer-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button>{{ __('Log in') }}</x-primary-button>
    </form>
</x-guest-layout>
