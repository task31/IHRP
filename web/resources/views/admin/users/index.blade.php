<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">
            Admin Users
        </h2>
    </x-slot>

    <div >
        <div >
            <div class="card-base">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold" style="color:var(--fg-1)">User Directory</h3>
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                        New User
                    </a>
                </div>

                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Linked consultant</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td style="color:var(--fg-1);font-weight:500;">{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge neutral">
                                            {{ str_replace('_', ' ', $user->role) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($user->consultant)
                                            {{ $user->consultant->full_name }}
                                            @if (! $user->consultant->active)
                                                <span style="font-size:11px;color:var(--warn-400);">(inactive)</span>
                                            @endif
                                        @else
                                            <span style="color:var(--fg-4)">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->active)
                                            <span class="badge ok">Active</span>
                                        @else
                                            <span class="badge bad">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a>
                                            @if ($user->active)
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger btn-sm" type="submit">Deactivate</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:24px;text-align:center;color:var(--fg-3);">No users found.</td>
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
