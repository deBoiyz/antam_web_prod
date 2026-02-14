<x-filament-panels::page>
    {{-- Summary Statistics --}}
    @php
        $stats = $this->getStats();
        $successRate = $stats['total'] > 0
            ? round(($stats['success'] / $stats['total']) * 100, 1)
            : 0;
    @endphp

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Total Entries</p>
            </div>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-500 dark:text-gray-400">{{ number_format($stats['pending']) }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Pending</p>
            </div>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['in_progress']) }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">In Progress</p>
            </div>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['success']) }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Success</p>
            </div>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($stats['failed']) }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Failed</p>
            </div>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $successRate }}%</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Success Rate</p>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
