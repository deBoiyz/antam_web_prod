<x-filament-panels::page>
    {{-- Loading Overlay --}}
    <div 
        wire:loading.flex 
        wire:target="startBot, stopBot, pauseBot, resumeBot, queueAllPendingJobs, reloadConfig, syncWebsites, pauseWebsite, resumeWebsite, queueWebsiteJobs, clearWebsiteQueue, disableWebsite, enableWebsite"
        class="fixed inset-0 z-50 bg-gray-900/50 dark:bg-gray-900/80 items-center justify-center"
    >
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl flex items-center gap-4">
            <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
            <span class="text-lg font-medium text-gray-700 dark:text-gray-300">
                Processing...
            </span>
        </div>
    </div>

    <div class="space-y-6" wire:poll.5s="refreshBotStatus">

        {{-- Control Panel Section (with connection banner inside) --}}
        <x-filament::section collapsible>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-500" />
                    <span>Control Panel</span>
                </div>
            </x-slot>

            {{-- Connection Status Banner --}}
            <div class="flex items-center justify-between p-4 rounded-xl border {{ $this->isConnected ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }}">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <div class="w-4 h-4 rounded-full {{ $this->isConnected ? 'bg-green-500' : 'bg-red-500' }}"></div>
                        @if($this->isConnected)
                            <div class="absolute inset-0 w-4 h-4 rounded-full bg-green-500 animate-ping opacity-75"></div>
                        @endif
                    </div>
                    <div>
                        <span class="font-semibold text-lg {{ $this->isConnected ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                            Bot Engine: {{ $this->isConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                        @if($this->lastError && !$this->isConnected)
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $this->lastError }}</p>
                        @endif
                    </div>
                </div>
                
                @if($this->isConnected)
                    <div class="flex items-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Status:</span>
                            @if($this->isWorkerRunning())
                                @if($this->isWorkerPaused())
                                    <x-filament::badge color="warning" size="lg">
                                        <span class="flex items-center">
                                            <x-heroicon-m-pause class="w-4 h-4 mr-1" />
                                            Paused
                                        </span>
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="success" size="lg">
                                        <span class="flex items-center">
                                            <x-heroicon-m-play class="w-4 h-4 mr-1" />
                                            Running
                                        </span>
                                    </x-filament::badge>
                                @endif
                            @else
                                <x-filament::badge color="gray" size="lg">
                                    <span class="flex items-center">
                                        <x-heroicon-m-stop class="w-4 h-4 mr-1" />
                                        Stopped
                                    </span>
                                </x-filament::badge>
                            @endif
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Workers:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->workerStatus['workerCount'] ?? 0 }}</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Session:</span>
                            <span class="font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ Str::limit($this->workerStatus['sessionId'] ?? 'None', 40) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
        
        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4">
            <x-filament::section class="!p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-orange-100 dark:bg-orange-900/30">
                        <x-heroicon-o-clock class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $this->getPendingCount() }}</p>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="!p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <x-heroicon-o-queue-list class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">In Queue</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->getQueuedCount() }}</p>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="!p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <x-heroicon-o-play class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Processing</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $this->getProcessingCount() }}</p>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="!p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->getSuccessCount() }}</p>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section class="!p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30">
                        <x-heroicon-o-x-circle class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->getFailedCount() }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>
        
        {{-- Website Workers & Queues Section --}}
        @if($this->isConnected)
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-server-stack class="w-5 h-5 text-gray-500" />
                        <span>Website Workers & Queues</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-filament::button 
                            size="sm" 
                            color="gray"
                            wire:click="queueAllPendingJobs"
                            wire:loading.attr="disabled"
                            :disabled="!$this->isConnected || $this->actionInProgress"
                        >
                            <span class="flex items-center">
                                <x-heroicon-m-queue-list class="w-4 h-4 mr-1" />
                                Queue Pending
                            </span>
                        </x-filament::button>

                        <x-filament::button 
                            size="sm" 
                            color="gray"
                            x-on:click="$wire.mountAction('confirmReloadConfig')"
                            wire:loading.attr="disabled"
                            :disabled="!$this->isConnected || $this->actionInProgress"
                        >
                            <span class="flex items-center">
                                <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                                Reload Config
                            </span>
                        </x-filament::button>

                        <x-filament::button 
                            size="sm" 
                            color="gray"
                            wire:click="syncWebsites"
                            wire:loading.attr="disabled"
                            :disabled="!$this->isConnected || $this->actionInProgress"
                        >
                            <span class="flex items-center">
                                <x-heroicon-m-arrow-path-rounded-square class="w-4 h-4 mr-1" />
                                Sync Websites
                            </span>
                        </x-filament::button>

                        <x-filament::button 
                            size="sm" 
                            color="danger"
                            :disabled="($this->getTotalQueueStats()['queued'] ?? 0) == 0"
                            x-on:click="$wire.mountAction('confirmClearAllQueues')"
                        >
                            <span class="flex items-center">
                                <x-heroicon-m-trash class="w-4 h-4 mr-1" />
                                Clear All Queues
                            </span>
                        </x-filament::button>
                    </div>
                </div>
            </x-slot>
            
            @php
                $websitesWithStats = $this->getWebsitesWithStats();
            @endphp
            
            @if(empty($websitesWithStats))
                <div class="text-center py-12 text-gray-500">
                    <x-heroicon-o-globe-alt class="w-16 h-16 mx-auto mb-4 opacity-30"/>
                    <p class="text-lg font-medium">No Active Websites</p>
                    <p class="text-sm mt-1">Configure websites in the Settings to start processing.</p>
                </div>
            @else
                <div class="max-h-[500px] overflow-y-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                                <th class="text-left py-3 px-4 font-medium text-sm text-gray-600 dark:text-gray-400">Website</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Worker Status</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Config</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-orange-600 dark:text-orange-400">Pending</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-blue-600 dark:text-blue-400">In Queue</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-purple-600 dark:text-purple-400">Active</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-green-600 dark:text-green-400">Completed</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-red-600 dark:text-red-400">Failed</th>
                                <th class="text-center py-3 px-4 font-medium text-sm text-gray-600 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($websitesWithStats as $website)
                                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold">
                                                {{ strtoupper(substr($website['name'], 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-950 dark:text-white">{{ $website['name'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $website['slug'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        @php
                                            $status = $website['workerStatus'];
                                            $statusColor = \App\Filament\Pages\BotControl::getStatusColor($status);
                                            $statusLabel = \App\Filament\Pages\BotControl::getWorkerStatusLabel($status);
                                        @endphp
                                        <x-filament::badge :color="$statusColor">
                                            {{ $statusLabel }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <div class="text-xs">
                                            <div class="text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">{{ $website['concurrency'] }}</span> concurrent
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-500">
                                                {{ $website['max_jobs_per_minute'] }}/min limit
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <span class="text-md font-medium">{{ $website['pending'] }}</span>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <span class="text-md font-medium">{{ $website['queued'] }}</span>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <span class="text-md font-medium">{{ $website['processing'] }}</span>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <span class="text-md font-medium">{{ $website['success'] }}</span>
                                    </td>
                                    <td class="py-4 px-3 text-center">
                                        <span class="text-md font-medium">{{ $website['failed'] }}</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center justify-center gap-1">
                                            {{-- Add worker button (if no worker exists) --}}
                                            @if($website['workerStatus'] === 'not_started')
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-plus" 
                                                    color="success"
                                                    size="sm"
                                                    wire:click="addWorker('{{ $website['slug'] }}', {{ $website['concurrency'] }}, {{ $website['max_jobs_per_minute'] }})"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Start worker"
                                                />
                                            @endif
                                            
                                            {{-- Queue pending jobs button --}}
                                            @if($website['pending'] > 0)
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-plus-circle" 
                                                    color="info"
                                                    size="sm"
                                                    wire:click="queueWebsiteJobs({{ $website['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Queue pending jobs"
                                                />
                                            @endif
                                            
                                            {{-- Pause/Resume worker button (only show if active) --}}
                                            @if($website['workerStatus'] === 'running')
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-pause" 
                                                    color="warning"
                                                    size="sm"
                                                    wire:click="pauseWebsite('{{ $website['slug'] }}')"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Pause worker"
                                                />
                                            @elseif($website['workerStatus'] === 'paused')
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-play" 
                                                    color="success"
                                                    size="sm"
                                                    wire:click="resumeWebsite('{{ $website['slug'] }}')"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Resume worker"
                                                />
                                            @endif
                                            
                                            {{-- Clear queue button --}}
                                            @if(($website['queued'] ?? 0) > 0)
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-trash" 
                                                    color="danger"
                                                    size="sm"
                                                    x-on:click="$wire.mountAction('confirmClearQueue', { slug: '{{ $website['slug'] }}', name: '{{ addslashes($website['name']) }}' })"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Clear queue"
                                                />
                                            @endif
                                            
                                            {{-- Enable worker button (if disabled) --}}
                                            @if($website['workerStatus'] === 'disabled')
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-play-circle" 
                                                    color="success"
                                                    size="sm"
                                                    wire:click="enableWebsite('{{ $website['slug'] }}', {{ $website['concurrency'] }}, {{ $website['max_jobs_per_minute'] }})"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Enable worker"
                                                />
                                            @endif
                                            
                                            {{-- Disable worker button (if active and has worker) --}}
                                            @if(in_array($website['workerStatus'], ['running', 'paused', 'idle', 'not_started']) && $website['is_active'])
                                                <x-filament::icon-button 
                                                    icon="heroicon-m-power" 
                                                    color="gray"
                                                    size="sm"
                                                    x-on:click="$wire.mountAction('confirmDisableWorker', { slug: '{{ $website['slug'] }}', name: '{{ addslashes($website['name']) }}' })"
                                                    wire:loading.attr="disabled"
                                                    tooltip="Disable worker"
                                                />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="sticky bottom-0 z-10">
                            <tr class="bg-gray-50 dark:bg-white/5 font-semibold border-t border-gray-200 dark:border-white/10">
                                <td class="py-3 px-4" colspan="3">
                                    <div class="flex items-center gap-2 text-gray-950 dark:text-white">
                                        <x-heroicon-o-calculator class="w-5 h-5 text-gray-500" />
                                        <span>Total ({{ count($websitesWithStats) }} websites)</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center text-md">
                                    {{ collect($websitesWithStats)->sum('pending') }}
                                </td>
                                <td class="py-3 px-3 text-center text-md">
                                    {{ collect($websitesWithStats)->sum('queued') }}
                                </td>
                                <td class="py-3 px-3 text-center text-md">
                                    {{ collect($websitesWithStats)->sum('processing') }}
                                </td>
                                <td class="py-3 px-3 text-center text-md">
                                    {{ collect($websitesWithStats)->sum('success') }}
                                </td>
                                <td class="py-3 px-3 text-center text-md">
                                    {{ collect($websitesWithStats)->sum('failed') }}
                                </td>
                                <td class="py-3 px-4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-filament::section>
        @endif
        
        {{-- Active Bot Sessions Section --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-cpu-chip class="w-5 h-5 text-gray-500" />
                        <span>Active Bot Sessions</span>
                        @if(count($this->activeSessions) > 0)
                            <x-filament::badge color="primary" size="sm">
                                {{ count($this->activeSessions) }}
                            </x-filament::badge>
                        @endif
                    </div>
                    @if(count($this->activeSessions) > 0)
                        <x-filament::button 
                            size="sm" 
                            color="gray" 
                            wire:click="cleanupStaleSessions"
                        >
                            <span class="flex items-center">
                                <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                                Cleanup Stale Sessions
                            </span>
                        </x-filament::button>
                    @endif
                </div>
            </x-slot>
            
            @if(empty($this->activeSessions))
                <div class="text-center py-12 text-gray-500">
                    <x-heroicon-o-cpu-chip class="w-16 h-16 mx-auto mb-4 opacity-30"/>
                    <p class="text-lg font-medium">No Active Sessions</p>
                    <p class="text-sm mt-1">Sessions will appear here when the bot engine is running.</p>
                </div>
            @else
                <div class="max-h-[400px] overflow-y-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                                <th class="text-left py-3 px-4 font-medium text-sm text-gray-600 dark:text-gray-400">Session</th>
                                <th class="text-left py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Website</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Status</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Processed</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Success</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Failed</th>
                                <th class="text-center py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Rate</th>
                                <th class="text-left py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Uptime</th>
                                <th class="text-left py-3 px-3 font-medium text-sm text-gray-600 dark:text-gray-400">Last Activity</th>
                                <th class="text-center py-3 px-4 font-medium text-sm text-gray-600 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($this->activeSessions as $session)
                                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/5 {{ $session['is_stale'] ? 'opacity-60' : '' }}">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs bg-gray-100 dark:bg-white/5 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">
                                                {{ Str::limit($session['session_id'], 12) }}
                                            </span>
                                            @if($session['is_stale'])
                                                <x-filament::badge color="warning" size="sm">Stale</x-filament::badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-gray-950 dark:text-white">
                                        {{ $session['website_name'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @php
                                            $statusColor = \App\Filament\Pages\BotControl::getStatusColor($session['status']);
                                        @endphp
                                        <x-filament::badge :color="$statusColor">
                                            {{ ucfirst($session['status']) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-3 px-3 text-center font-medium text-gray-950 dark:text-white">
                                        {{ $session['processed_count'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center text-green-600 dark:text-green-400 font-medium">
                                        {{ $session['success_count'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center text-red-600 dark:text-red-400 font-medium">
                                        {{ $session['failure_count'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @php
                                            $rate = $session['success_rate'];
                                            $rateColor = $rate >= 80 ? 'text-green-600 dark:text-green-400' : ($rate >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                        @endphp
                                        <span class="font-medium {{ $rateColor }}">{{ $rate }}%</span>
                                    </td>
                                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                                        {{ $session['uptime'] ?? '-' }}
                                    </td>
                                    <td class="py-3 px-3 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ $session['last_activity'] ?? '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if(in_array($session['status'], ['running', 'paused', 'idle']))
                                            <x-filament::icon-button 
                                                icon="heroicon-m-stop" 
                                                color="danger"
                                                size="sm"
                                                x-on:click="$wire.mountAction('confirmStopSession', { id: {{ $session['id'] }}, sessionId: '{{ Str::limit($session['session_id'], 12) }}' })"
                                                wire:loading.attr="disabled"
                                                tooltip="Stop session"
                                            />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
