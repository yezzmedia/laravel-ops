<div class="grid gap-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">Feature inventory</p>
            <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ count($this->features) }}</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Registered platform features with package ownership and related operator entry points.</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Features</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Feature</th>
                            <th class="px-5 py-3 font-medium">Package</th>
                            <th class="px-5 py-3 font-medium">Description</th>
                            <th class="px-5 py-3 font-medium">Related entry points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @forelse ($this->features as $feature)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $feature['label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $feature['name'] }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    <p>{{ $feature['package'] }}</p>
                                    @if ($feature['packageDescription'])
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $feature['packageDescription'] }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    {{ $feature['description'] ?? 'No feature description is currently registered.' }}
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">
                                    @if ($feature['entryPoints'] === [])
                                        <span class="text-gray-400 dark:text-gray-500">No package pages</span>
                                    @else
                                        {{ implode(', ', $feature['entryPoints']) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No platform features are currently registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
