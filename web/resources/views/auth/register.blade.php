<x-guest-layout>
    <div class="auth-header">
        <div class="eyebrow">Create Access</div>
        <h1 class="auth-title">Register a new account</h1>
        <p class="auth-subtitle">{{ __('Create a Matchpointe HR Command user profile.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="field">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="field">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="auth-actions">
            <a class="auth-footer-link" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button>{{ __('Register') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
