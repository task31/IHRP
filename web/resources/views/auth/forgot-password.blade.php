<x-guest-layout>
    <div class="auth-header">
        <div class="eyebrow">Password Reset</div>
        <h1 class="auth-title">Reset your password</h1>
        <p class="auth-subtitle">{{ __('Enter your account email and we will send a reset link.') }}</p>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <x-primary-button>
            {{ __('Email Password Reset Link') }}
        </x-primary-button>

        <a class="auth-footer-link" href="{{ route('login') }}">
            {{ __('Back to login') }}
        </a>
    </form>
</x-guest-layout>
