<x-filament-panels::page>
    <div class="space-y-6" wire:poll.5s="refreshBotStatus">
        {{-- Bot Engine Connection Status --}}
        <div class="flex items-center justify-between p-4 rounded-lg {{ $this->isConnected ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full {{ $this->isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}"></div>
                <span class="font-medium {{ $this->isConnected ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                    Bot Engine: {{ $this->isConnected ? 'Connected' : 'Disconnected' }}
                </span>
            </div>
            @if($this->isConnected)
                <span class="text-sm text-gray-500">
                    Worker: 
                    @if($this->isWorkerRunning())
                        @if($this->isWorkerPaused())
                            <span class="text-yellow-600 font-medium">Paused</span>
                        @else
                            <span class="text-green-600 font-medium">Running</span>
                        @endif
                    @else
                        <span class="text-gray-500 font-medium">Stopped</span>
                    @endif
                </span>
            @endif
        </div>

        {{ $this->form }}
        
        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::section>
                <x-slot name="heading">Pending Entries</x-slot>
                <div class="text-3xl font-bold text-warning-500">
                    {{ $this->getPendingCount() }}
                </div>
                <p class="text-sm text-gray-500">Waiting to be queued</p>
            </x-filament::section>
            
            <x-filament::section>
                <x-slot name="heading">Queued Entries</x-slot>
                <div class="text-3xl font-bold text-info-500">
                    {{ $this->getQueuedCount() }}
                </div>
                <p class="text-sm text-gray-500">Ready for processing</p>
            </x-filament::section>
            
            <x-filament::section>
                <x-slot name="heading">Active Sessions</x-slot>
                <div class="text-3xl font-bold text-success-500">
                    {{ $this->getActiveSessions()->count() }}
                </div>
                <p class="text-sm text-gray-500">Bot workers running</p>
            </x-filament::section>
            
            @if($this->isConnected && !empty($this->getQueueStatus()))
            <x-filament::section>
                <x-slot name="heading">Queue Status</x-slot>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500">Waiting:</span>
                        <span class="font-bold">{{ $this->getQueueStatus()['waiting'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Active:</span>
                        <span class="font-bold text-blue-600">{{ $this->getQueueStatus()['active'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Completed:</span>
                        <span class="font-bold text-green-600">{{ $this->getQueueStatus()['completed'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Failed:</span>
                        <span class="font-bold text-red-600">{{ $this->getQueueStatus()['failed'] ?? 0 }}</span>
                    </div>
                </div>
            </x-filament::section>
            @endif
        </div>
        
        {{-- Active Bot Sessions --}}
        <x-filament::section>
            <x-slot name="heading">Active Bot Sessions</x-slot>
            
            @if($this->getActiveSessions()->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-cpu-chip class="w-12 h-12 mx-auto mb-2 opacity-50"/>
                    <p>No active bot sessions</p>
                    <p class="text-sm">Click "Start Bot" to begin processing</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Session ID</th>
                                <th class="text-left py-2">Website</th>
                                <th class="text-left py-2">Status</th>
                                <th class="text-left py-2">Processed</th>
                                <th class="text-left py-2">Success</th>
                                <th class="text-left py-2">Failed</th>
                                <th class="text-left py-2">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->getActiveSessions() as $session)
                                <tr class="border-b">
                                    <td class="py-2 font-mono text-xs">{{ Str::limit($session->session_id, 15) }}</td>
                                    <td class="py-2">{{ $session->website?->name ?? 'All' }}</td>
                                    <td class="py-2">
                                        <x-filament::badge :color="match($session->status) {
                                            'running' => 'success',
                                            'paused' => 'warning',
                                            'idle' => 'gray',
                                            default => 'gray'
                                        }">
                                            {{ ucfirst($session->status) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-2">{{ $session->processed_count }}</td>
                                    <td class="py-2 text-success-600">{{ $session->success_count }}</td>
                                    <td class="py-2 text-danger-600">{{ $session->failure_count }}</td>
                                    <td class="py-2 text-gray-500">{{ $session->last_activity_at?->diffForHumans() ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
        
        {{-- Bot Engine Info --}}
        @if($this->isConnected && !empty($this->getWorkerStatus()))
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Bot Engine Details</x-slot>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Session ID:</span>
                    <span class="font-mono block text-xs">{{ $this->getWorkerStatus()['sessionId'] ?? 'None' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Website Configs:</span>
                    <span class="font-bold">{{ $this->getWorkerStatus()['websiteConfigs'] ?? 0 }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Proxies Available:</span>
                    <span class="font-bold">{{ $this->getWorkerStatus()['proxies']['total'] ?? 0 }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Healthy Proxies:</span>
                    <span class="font-bold text-green-600">{{ $this->getWorkerStatus()['proxies']['healthy'] ?? 0 }}</span>
                </div>
            </div>
        </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
