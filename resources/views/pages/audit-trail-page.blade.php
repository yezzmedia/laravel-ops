<div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Backend</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['backend'] ? 'Activitylog' : 'Unavailable' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Recent entries</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['activityCount'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ str($this->summary['status'])->headline() }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Recent audit activity</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Privileged and operator-visible activity from the configured audit backend.</p>
            </div>

            @if ($this->summary['status'] === 'unavailable')
                <div class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                    No supported audit backend is currently installed.
                </div>
            @elseif ($this->summary['status'] === 'degraded')
                <div class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                    The audit backend is present, but recent activity could not be read.
                </div>
            @elseif ($this->summary['status'] === 'empty')
                <div class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                    No recent audit entries are currently available.
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($this->summary['items'] as $item)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $item->description }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->loggedAt ?? 'n/a' }}</p>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>Event: {{ $item->event ?? 'n/a' }}</span>
                                <span>Log: {{ $item->logName ?? 'n/a' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
