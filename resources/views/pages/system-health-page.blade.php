<div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Failing checks</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['failingCount'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Warnings</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['warningCount'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Passed checks</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['passedCount'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Last refresh</p>
                <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $this->summary['completedAt'] }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Mode: {{ str($this->summary['accessMode'])->headline() }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Doctor checks</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Curated diagnostics posture from approved health sources.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Check</th>
                            <th class="px-5 py-3 font-medium">Package</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @foreach ($this->summary['checks'] as $check)
                            <tr>
                                <td class="px-5 py-4 align-top font-medium text-gray-950 dark:text-white">{{ $check['key'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $check['package'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ str($check['status'])->headline() }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $check['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->showsRuntime)
            <div class="grid gap-6 xl:grid-cols-3">
                @foreach ($this->runtime as $section)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>

                        <div class="mt-4 grid gap-4">
                            @foreach ($section['items'] as $item)
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                                    <div class="flex items-center justify-between gap-4">
                                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $item['value'] }}</p>
                                    </div>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
