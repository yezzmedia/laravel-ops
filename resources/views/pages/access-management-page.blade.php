<div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Runtime bridge</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['available'] ? 'Available' : 'Limited' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Super-admin role</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['superAdmin']['roleName'] ?? 'Disabled' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Qualified operators</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->overview['superAdmin']['operatorCount'] }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Minimum: {{ $this->overview['superAdmin']['minimumOperators'] }}</p>
            </div>
        </div>

        @if ($this->overview['error'])
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                {{ $this->overview['error'] }}
            </div>
        @elseif ($this->overview['superAdmin']['enabled'])
            <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
                Super-admin removals are guarded against dropping below the minimum configured operator count.
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Persisted roles</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Role composition, permission breadth, and current assignment counts.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Role</th>
                            <th class="px-5 py-3 font-medium">Permissions</th>
                            <th class="px-5 py-3 font-medium">Assignments</th>
                            <th class="px-5 py-3 font-medium">Permission names</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @forelse ($this->overview['roles'] as $role)
                            <tr>
                                <td class="px-5 py-4 align-top font-medium text-gray-950 dark:text-white">{{ $role['name'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $role['permissionCount'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $role['assignmentCount'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $role['permissionNames'] === [] ? 'n/a' : implode(', ', $role['permissionNames']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No persisted roles are currently available for access management.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
