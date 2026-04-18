<x-guest-layout>
    <div class="auth-header">
        <div class="eyebrow">Confirmation Required</div>
        <h1 class="auth-title">Confirm your password</h1>
        <p class="auth-subtitle">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        <div class="field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button>{{ __('Confirm') }}</x-primary-button>
    </form>
</x-guest-layout>
