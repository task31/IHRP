<x-guest-layout>
    <div class="auth-header">
        <div class="eyebrow">Verify Email</div>
        <h1 class="auth-title">Check your inbox</h1>
        <p class="auth-subtitle">{{ __('Verify your email address before getting started.') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="flash-banner flash-success" style="margin-bottom:16px;">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="stack-sm">
        <p class="auth-subtitle">
            {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you did not receive the email, we can send another.') }}
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="auth-footer-link">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
