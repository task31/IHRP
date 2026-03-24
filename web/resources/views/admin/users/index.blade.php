<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Admin Users
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">User Directory</h3>
                    <a href="{{ route('admin.users.create') }}" class="rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        New User
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Name</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Email</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Role</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Linked consultant</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-3 py-2">{{ $user->name }}</td>
                                    <td class="px-3 py-2">{{ $user->email }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                            {{ str_replace('_', ' ', $user->role) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        @if ($user->consultant)
                                            {{ $user->consultant->full_name }}
                                            @if (! $user->consultant->active)
                                                <span class="text-xs text-amber-700">(inactive)</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($user->active)
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Active</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-700 hover:underline">Edit</a>
                                            @if ($user->active)
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-red-700 hover:underline" type="submit">Deactivate</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-center text-gray-500">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>

        @include('admin.partials.email-inbox', ['inboxMessages' => $inboxMessages])

        {{-- Hash #email-inbox: native scroll is unreliable on same-page navigation; force scroll into view --}}
        <script>
            (function () {
                function scrollToEmailInbox() {
                    if (location.hash !== '#email-inbox') {
                        return;
                    }
                    var el = document.getElementById('email-inbox');
                    if (!el) {
                        return;
                    }
                    requestAnimationFrame(function () {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
                document.addEventListener('DOMContentLoaded', scrollToEmailInbox);
                window.addEventListener('hashchange', scrollToEmailInbox);
            })();
        </script>
    </div>
</x-app-layout>
