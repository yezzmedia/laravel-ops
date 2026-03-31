<div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Declared permissions</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ count($this->overview['permissions']) }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Store ready</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['store']['ready'] ? 'Yes' : 'No' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending migrations</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['store']['pendingMigrations'] ? 'Yes' : 'No' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Runtime bridge</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['available'] ? 'Available' : 'Limited' }}</p>
            </div>
        </div>

        @if ($this->overview['error'])
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                {{ $this->overview['error'] }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Declared permissions</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-oriented visibility for foundation-declared permissions and their package ownership.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Permission</th>
                            <th class="px-5 py-3 font-medium">Package</th>
                            <th class="px-5 py-3 font-medium">Synced</th>
                            <th class="px-5 py-3 font-medium">Role hints</th>
                            <th class="px-5 py-3 font-medium">Assigned roles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @foreach ($this->overview['permissions'] as $permission)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $permission['name'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $permission['label'] }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $permission['package'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $permission['synced'] ? 'Yes' : 'No' }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $permission['roleHints'] === [] ? 'n/a' : implode(', ', $permission['roleHints']) }}
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $permission['assignedRoles'] === [] ? 'n/a' : implode(', ', $permission['assignedRoles']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Role relationships</h2>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->overview['roles'] as $role)
                    <div class="px-5 py-4">
                        <p class="font-medium text-gray-950 dark:text-white">{{ $role['name'] }}</p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                            {{ $role['permissionNames'] === [] ? 'No permissions are currently assigned.' : implode(', ', $role['permissionNames']) }}
                        </p>
                    </div>
                @empty
                    <div class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                        No persisted role relationships are currently available.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
