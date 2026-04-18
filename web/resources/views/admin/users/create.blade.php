<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">
            Create User
        </h2>
    </x-slot>

    <div >
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="card-base">
                <form method="POST" action="{{ route('admin.users.store') }}" class="stack">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email') }}" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="Password" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select role</option>
                            @foreach (['admin', 'account_manager'] as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ str_replace('_', ' ', $role) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="consultant_id" value="Consultant Link (Optional)" />
                        <select id="consultant_id" name="consultant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">No linked consultant</option>
                            @foreach ($consultants as $consultant)
                                <option value="{{ $consultant->id }}" @selected((string) old('consultant_id') === (string) $consultant->id)>
                                    {{ $consultant->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('consultant_id')" class="mt-2" />
                        <p style="margin-top:4px;font-size:11px;color:var(--fg-3)">For <strong>account managers</strong> only. Ignored if role is admin.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="active" name="active" type="checkbox" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" />
                        <label for="active" class="text-sm text-gray-700">Active account</label>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <x-primary-button>Create User</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
