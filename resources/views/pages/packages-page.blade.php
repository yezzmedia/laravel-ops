<div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Registered packages</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ count($this->packages) }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Enabled packages</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ collect($this->packages)->where('enabled', true)->count() }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Packages with features</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ collect($this->packages)->where('featureCount', '>', 0)->count() }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Platform packages</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Curated package readiness, ownership, and operator-facing entry points.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Package</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Features</th>
                            <th class="px-5 py-3 font-medium">Priority</th>
                            <th class="px-5 py-3 font-medium">Entry points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @forelse ($this->packages as $package)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $package['name'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $package['description'] }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $package['enabled'] ? 'Enabled' : 'Disabled' }}
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $package['featureCount'] }}
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $package['priority'] ?? 'n/a' }}
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    @if ($package['entryPoints'] === [])
                                        <span class="text-gray-400 dark:text-gray-500">No package pages</span>
                                    @else
                                        {{ implode(', ', $package['entryPoints']) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No platform packages are currently registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
