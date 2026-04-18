<x-guest-layout>
    <div style="margin-bottom:20px;border-radius:var(--radius-md);border:1px solid rgba(34,211,238,0.15);background:rgba(34,211,238,0.06);padding:16px">
        <div class="eyebrow">Matchpointe Group</div>
        <h2 style="margin-top:4px;font-size:16px;font-weight:600;color:var(--fg-1)">Internal HR Portal Login</h2>
        <p style="margin-top:4px;font-size:13px;color:var(--fg-3)">Secure payroll and staffing operations access.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div style="margin-bottom:14px">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div style="margin-bottom:14px">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div style="margin-bottom:20px">
            <label for="remember_me" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--fg-2)">
                <input id="remember_me" type="checkbox" name="remember" style="accent-color:var(--accent-400)">
                {{ __('Remember me') }}
            </label>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between">
            @if (Route::has('password.request'))
                <a style="font-size:12px;color:var(--accent-400);text-decoration:none" href="{{ route('password.request') }}"
                   onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button>{{ __('Log in') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
