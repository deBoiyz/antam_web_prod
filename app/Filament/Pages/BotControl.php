<?php

namespace App\Filament\Pages;

use App\Models\BotSession;
use App\Models\DataEntry;
use App\Models\Website;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotControl extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Bot Control';

    protected static string $view = 'filament.pages.bot-control';

    // Bot engine connection settings
    protected string $botEngineUrl = 'http://localhost:3001';

    // State properties
    public bool $isConnected = false;
    public bool $isLoading = false;
    public array $workerStatus = [];
    public array $queueStatus = [];
    public array $websitesWithStats = [];
    public array $activeSessions = [];
    public ?string $lastError = null;

    // Action locks to prevent double-click
    public bool $actionInProgress = false;
    public ?string $currentAction = null;

    // Form state
    public ?int $selectedWebsite = null;

    public function mount(): void
    {
        $this->botEngineUrl = config('services.bot_engine.url', 'http://localhost:3001');
        $this->refreshBotStatus();
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Get header actions - only start/stop/pause/resume
     */
    protected function getHeaderActions(): array
    {
        $isRunning = $this->isWorkerRunning();
        $isPaused = $this->isWorkerPaused();

        return [
            Action::make('startBot')
                ->label(fn () => $this->currentAction === 'starting' ? 'Starting...' : 'Start Bot')
                ->icon('heroicon-o-play')
                ->color('success')
                ->disabled(fn () => !$this->isConnected || $isRunning || $this->actionInProgress)
                ->action(fn () => $this->startBot())
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'startBot',
                ]),

            Action::make('stopBot')
                ->label(fn () => $this->currentAction === 'stopping' ? 'Stopping...' : 'Stop Bot')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->disabled(fn () => !$this->isConnected || !$isRunning || $this->actionInProgress)
                ->requiresConfirmation()
                ->modalHeading('Stop Bot Engine?')
                ->modalDescription('This will stop all workers and close all browser instances. Pending jobs will remain in queue.')
                ->action(fn () => $this->stopBot())
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'stopBot',
                ]),

            Action::make('pauseBot')
                ->label(fn () => $this->currentAction === 'pausing' ? 'Pausing...' : 'Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->disabled(fn () => !$this->isConnected || !$isRunning || $isPaused || $this->actionInProgress)
                ->action(fn () => $this->pauseBot())
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'pauseBot',
                ]),

            Action::make('resumeBot')
                ->label(fn () => $this->currentAction === 'resuming' ? 'Resuming...' : 'Resume')
                ->icon('heroicon-o-play-pause')
                ->color('info')
                ->disabled(fn () => !$this->isConnected || !$isPaused || $this->actionInProgress)
                ->action(fn () => $this->resumeBot())
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'resumeBot',
                ]),
        ];
    }

    /**
     * Refresh bot status - called by polling
     */
    public function refreshBotStatus(): void
    {
        $originalTimeLimit = (int) ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $this->checkConnection();
            
            if ($this->isConnected) {
                $this->fetchWorkerStatus();
                $this->fetchQueueStatus();
            }
            
            $this->loadWebsitesWithStats();
            $this->loadActiveSessions();
        } catch (\Exception $e) {
            Log::warning('Error refreshing bot status: ' . $e->getMessage());
        } finally {
            set_time_limit($originalTimeLimit);
        }
    }

    /**
     * Check connection to bot engine
     */
    protected function checkConnection(): void
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get("{$this->botEngineUrl}/health");
            $this->isConnected = $response->successful();
            
            if ($this->isConnected) {
                $this->lastError = null;
            }
        } catch (\Exception $e) {
            $this->isConnected = false;
            $this->lastError = 'Cannot connect to Bot Engine: ' . $e->getMessage();
            Log::debug('Bot engine connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch worker status from bot engine
     */
    protected function fetchWorkerStatus(): void
    {
        try {
            $response = Http::connectTimeout(3)->timeout(10)->get("{$this->botEngineUrl}/status");
            
            if ($response->successful()) {
                $data = $response->json();
                $this->workerStatus = $data['worker'] ?? [];
                
                // Also get queue data from status endpoint
                if (isset($data['queue'])) {
                    $this->queueStatus = $data['queue']['total'] ?? $data['queue'] ?? [];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch worker status: ' . $e->getMessage());
        }
    }

    /**
     * Fetch queue status from bot engine
     */
    protected function fetchQueueStatus(): void
    {
        try {
            $response = Http::connectTimeout(3)->timeout(10)->get("{$this->botEngineUrl}/queue");
            
            if ($response->successful()) {
                $data = $response->json();
                $this->queueStatus = $data['total'] ?? $data ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch queue status: ' . $e->getMessage());
        }
    }

    /**
     * Load websites with their stats
     */
    protected function loadWebsitesWithStats(): void
    {
        // Get ALL websites (both active and inactive) to show in the list
        $websites = Website::all();
        $queueData = [];
        $workerData = [];

        // Fetch queue status per website if connected
        if ($this->isConnected) {
            try {
                $response = Http::connectTimeout(3)->timeout(10)->get("{$this->botEngineUrl}/status");
                if ($response->successful()) {
                    $data = $response->json();
                    $queueData = $data['queue']['websites'] ?? [];
                    $workerData = $data['worker']['workers'] ?? [];
                }
            } catch (\Exception $e) {
                Log::debug('Failed to fetch website queue status: ' . $e->getMessage());
            }
        }

        $this->websitesWithStats = $websites->map(function ($website) use ($queueData, $workerData) {
            $workerInfo = $workerData[$website->slug] ?? null;
            
            return [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
                'is_active' => $website->is_active,
                'concurrency' => $website->concurrency_limit ?? 2,
                'max_jobs_per_minute' => $website->max_jobs_per_minute ?? 10,
                'pending' => $website->pending_entries_count,
                'queued' => $website->queued_entries_count,
                'processing' => $website->processing_entries_count,
                'success' => $website->success_entries_count,
                'failed' => $website->failed_entries_count,
                'cancelled' => $website->cancelled_entries_count,
                'engine_queue' => $queueData[$website->slug] ?? [
                    'waiting' => 0,
                    'active' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'delayed' => 0,
                ],
                'worker' => $workerInfo,
                'workerStatus' => $this->determineWorkerStatus($workerInfo, $website),
            ];
        })->toArray();
    }

    /**
     * Determine worker status string
     * Priority: is_active=false means ALWAYS disabled, regardless of worker state
     */
    protected function determineWorkerStatus(?array $workerInfo, ?Website $website = null): string
    {
        // FIRST: Check if website is disabled in database - this is the source of truth
        if ($website && !$website->is_active) {
            return 'disabled';
        }
        
        // No worker info means worker hasn't been created yet
        if (!$workerInfo) {
            return 'not_started';
        }
        
        if ($workerInfo['isPaused'] ?? false) {
            return 'paused';
        }
        
        if ($workerInfo['isRunning'] ?? false) {
            return 'running';
        }
        
        return 'idle';
    }

    /**
     * Load active bot sessions
     */
    protected function loadActiveSessions(): void
    {
        $this->activeSessions = BotSession::with('website')
            ->active()
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($session) {
                $isStale = $session->last_activity_at && 
                           $session->last_activity_at->diffInMinutes(now()) > 2;
                
                return [
                    'id' => $session->id,
                    'session_id' => $session->session_id,
                    'website_name' => $session->website?->name ?? 'All Websites',
                    'status' => $session->status,
                    'processed_count' => $session->processed_count,
                    'success_count' => $session->success_count,
                    'failure_count' => $session->failure_count,
                    'success_rate' => $session->success_rate,
                    'uptime' => $session->uptime,
                    'last_activity' => $session->last_activity_at?->diffForHumans(),
                    'last_activity_at' => $session->last_activity_at,
                    'worker_hostname' => $session->worker_hostname,
                    'is_stale' => $isStale,
                ];
            })
            ->toArray();
    }

    /**
     * Check if worker is running
     */
    public function isWorkerRunning(): bool
    {
        return $this->workerStatus['isRunning'] ?? false;
    }

    /**
     * Check if worker is paused
     */
    public function isWorkerPaused(): bool
    {
        return $this->workerStatus['isPaused'] ?? false;
    }

    /**
     * Get pending entries count
     */
    public function getPendingCount(): int
    {
        $query = DataEntry::where('status', 'pending');
        if ($this->selectedWebsite) {
            $query->where('website_id', $this->selectedWebsite);
        }
        return $query->count();
    }

    /**
     * Get queued entries count
     */
    public function getQueuedCount(): int
    {
        $query = DataEntry::where('status', 'queued');
        if ($this->selectedWebsite) {
            $query->where('website_id', $this->selectedWebsite);
        }
        return $query->count();
    }

    /**
     * Get processing entries count
     */
    public function getProcessingCount(): int
    {
        $query = DataEntry::where('status', 'processing');
        if ($this->selectedWebsite) {
            $query->where('website_id', $this->selectedWebsite);
        }
        return $query->count();
    }

    /**
     * Get total queue stats from database
     */
    public function getTotalQueueStats(): array
    {
        return [
            'pending' => DataEntry::where('status', 'pending')->count(),
            'queued' => DataEntry::where('status', 'queued')->count(),
            'processing' => DataEntry::where('status', 'processing')->count(),
            'success' => DataEntry::where('status', 'success')->count(),
            'failed' => DataEntry::where('status', 'failed')->count(),
            'cancelled' => DataEntry::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get success count from database
     */
    public function getSuccessCount(): int
    {
        $query = DataEntry::where('status', 'success');
        if ($this->selectedWebsite) {
            $query->where('website_id', $this->selectedWebsite);
        }
        return $query->count();
    }

    /**
     * Get failed count from database
     */
    public function getFailedCount(): int
    {
        $query = DataEntry::where('status', 'failed');
        if ($this->selectedWebsite) {
            $query->where('website_id', $this->selectedWebsite);
        }
        return $query->count();
    }

    /**
     * Get active sessions as collection for blade
     */
    public function getActiveSessions()
    {
        return collect($this->activeSessions);
    }

    /**
     * Get websites with stats for blade
     */
    public function getWebsitesWithStats(): array
    {
        return $this->websitesWithStats;
    }

    /**
     * Execute action with loading state
     * Extends PHP max_execution_time so HTTP client can timeout gracefully
     * (prevents PHP fatal error from killing the process before Guzzle can handle it)
     */
    protected function executeAction(string $actionName, callable $callback): void
    {
        if ($this->actionInProgress) {
            Notification::make()
                ->title('Action In Progress')
                ->body('Please wait for the current action to complete.')
                ->warning()
                ->send();
            return;
        }
        
        $this->actionInProgress = true;
        $this->currentAction = $actionName;

        // Extend PHP execution time so Guzzle can timeout first (catchable)
        // instead of PHP killing the process (fatal, uncatchable)
        $originalTimeLimit = (int) ini_get('max_execution_time');
        set_time_limit(300); // 5 minutes - HTTP timeouts are much shorter

        try {
            $callback();
        } catch (\Symfony\Component\ErrorHandler\Error\FatalError $e) {
            Notification::make()
                ->title('Operation Timed Out')
                ->body('The operation took too long and was terminated. The bot engine may still be processing in the background.')
                ->danger()
                ->duration(10000)
                ->send();
            Log::error("Fatal error in {$actionName}: " . $e->getMessage());
        } catch (\Error $e) {
            Notification::make()
                ->title('Unexpected Error')
                ->body('An unexpected error occurred: ' . \Illuminate\Support\Str::limit($e->getMessage(), 150))
                ->danger()
                ->duration(10000)
                ->send();
            Log::error("Error in {$actionName}: " . $e->getMessage());
        } catch (\Exception $e) {
            Notification::make()
                ->title('Operation Failed')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 150))
                ->danger()
                ->duration(10000)
                ->send();
            Log::error("Exception in {$actionName}: " . $e->getMessage());
        } finally {
            $this->actionInProgress = false;
            $this->currentAction = null;
            set_time_limit($originalTimeLimit); // Restore original limit
            $this->refreshBotStatus();
        }
    }

    /**
     * Start bot engine
     */
    public function startBot(): void
    {
        $this->executeAction('starting', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/start");
                
                if ($response->successful()) {
                    Notification::make()
                        ->title('Bot Started')
                        ->body('All workers have been started successfully.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Start Bot')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Stop bot engine
     */
    public function stopBot(): void
    {
        $this->executeAction('stopping', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(60)->post("{$this->botEngineUrl}/stop");
                
                if ($response->successful()) {
                    // Mark all active sessions as stopped
                    BotSession::active()->update(['status' => 'stopped']);
                    
                    Notification::make()
                        ->title('Bot Stopped')
                        ->body('All workers have been stopped.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Stop Bot')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Pause bot engine
     */
    public function pauseBot(): void
    {
        if (!$this->isWorkerRunning()) {
            Notification::make()
                ->title('Cannot Pause')
                ->body('Bot is not currently running.')
                ->warning()
                ->send();
            return;
        }
        
        $this->executeAction('pausing', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/pause");
                
                if ($response->successful()) {
                    // Update session status
                    BotSession::where('status', 'running')->update(['status' => 'paused']);
                    
                    Notification::make()
                        ->title('Bot Paused')
                        ->body('All workers have been paused.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Pause Bot')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Resume bot engine
     */
    public function resumeBot(): void
    {
        if (!$this->isWorkerPaused()) {
            Notification::make()
                ->title('Cannot Resume')
                ->body('Bot is not currently paused.')
                ->warning()
                ->send();
            return;
        }
        
        $this->executeAction('resuming', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/resume");
                
                if ($response->successful()) {
                    // Update session status
                    BotSession::where('status', 'paused')->update(['status' => 'running']);
                    
                    Notification::make()
                        ->title('Bot Resumed')
                        ->body('All workers have been resumed.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Resume Bot')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Queue all pending jobs
     */
    public function queueAllPendingJobs(): void
    {
        $this->executeAction('queueing', function () {
            try {
                // Fetch pending entries directly from database (avoid deadlock with single-threaded dev server)
                $query = DataEntry::with('website')->readyToProcess();
                
                if ($this->selectedWebsite) {
                    $query->where('website_id', $this->selectedWebsite);
                }
                
                $entries = $query->limit(100)->get();
                
                if ($entries->isEmpty()) {
                    Notification::make()
                        ->title('No Pending Jobs')
                        ->body('There are no pending jobs to queue.')
                        ->info()
                        ->send();
                    return;
                }
                
                // Group by website slug and send to bot engine
                $totalQueued = 0;
                $grouped = $entries->groupBy(fn ($entry) => $entry->website?->slug);
                
                foreach ($grouped as $slug => $websiteEntries) {
                    if (!$slug) continue;
                    
                    $response = Http::timeout(30)->post("{$this->botEngineUrl}/jobs/batch", [
                        'entries' => $websiteEntries->map(fn ($entry) => [
                            'id' => $entry->id,
                            'identifier' => $entry->identifier,
                            'data' => $entry->data,
                            'priority' => $entry->priority ?? 0,
                            'max_attempts' => $entry->max_attempts,
                        ])->values()->toArray(),
                        'websiteSlug' => $slug,
                    ]);
                    
                    if ($response->successful()) {
                        // Mark entries as queued
                        DataEntry::whereIn('id', $websiteEntries->pluck('id'))->update(['status' => 'queued']);
                        $totalQueued += $websiteEntries->count();
                    }
                }
                
                Notification::make()
                    ->title('Jobs Queued')
                    ->body("{$totalQueued} jobs have been added to the queue.")
                    ->success()
                    ->send();

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Notification::make()
                    ->title('Connection Timeout')
                    ->body('Bot engine is taking too long to respond. It may be processing a large number of jobs. Please try again in a moment.')
                    ->danger()
                    ->duration(10000)
                    ->send();
                Log::error('Queue jobs timeout: ' . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Queue Jobs')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Reload configuration (graceful - only updates changed workers)
     */
    public function reloadConfig(): void
    {
        $this->executeAction('reloading', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(60)->post("{$this->botEngineUrl}/reload");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $added = $data['added'] ?? 0;
                    $updated = $data['updated'] ?? 0;
                    $removed = $data['removed'] ?? 0;
                    
                    Notification::make()
                        ->title('Configuration Reloaded')
                        ->body("Graceful reload complete: {$added} added, {$updated} updated, {$removed} removed.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Notification::make()
                    ->title('Connection Timeout')
                    ->body('Bot engine is taking too long to reload configuration. The reload may still be in progress. Please check status in a moment.')
                    ->warning()
                    ->duration(10000)
                    ->send();
                Log::error('Reload config timeout: ' . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Reload Configuration')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Sync websites - detect and add new websites without affecting running workers
     */
    public function syncWebsites(): void
    {
        $this->executeAction('syncing', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(60)->post("{$this->botEngineUrl}/sync");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $workerCount = $data['workerCount'] ?? 0;
                    $websites = $data['websites'] ?? [];
                    
                    Notification::make()
                        ->title('Websites Synced')
                        ->body("Sync complete. {$workerCount} workers active: " . implode(', ', $websites))
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Notification::make()
                    ->title('Connection Timeout')
                    ->body('Bot engine is taking too long to sync websites. The sync may still be in progress. Please check status in a moment.')
                    ->warning()
                    ->duration(10000)
                    ->send();
                Log::error('Sync websites timeout: ' . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Sync Websites')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Force reload - stops all workers and recreates them
     */
    public function forceReload(): void
    {
        $this->executeAction('force_reloading', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(120)->post("{$this->botEngineUrl}/force-reload");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $workerCount = $data['workerCount'] ?? 0;
                    
                    Notification::make()
                        ->title('Force Reload Complete')
                        ->body("All workers recreated. {$workerCount} workers active.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Force Reload')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Pause specific website worker
     */
    public function pauseWebsite(string $slug): void
    {
        $this->executeAction('pausing_' . $slug, function () use ($slug) {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/website/{$slug}/pause");
                
                if ($response->successful()) {
                    Notification::make()
                        ->title('Worker Paused')
                        ->body("Worker for {$slug} has been paused.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Pause Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Resume specific website worker
     */
    public function resumeWebsite(string $slug): void
    {
        $this->executeAction('resuming_' . $slug, function () use ($slug) {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/website/{$slug}/resume");
                
                if ($response->successful()) {
                    Notification::make()
                        ->title('Worker Resumed')
                        ->body("Worker for {$slug} has been resumed.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Resume Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Queue jobs for specific website
     */
    public function queueWebsiteJobs(int $websiteId): void
    {
        $website = Website::find($websiteId);
        if (!$website) {
            Notification::make()
                ->title('Website Not Found')
                ->danger()
                ->send();
            return;
        }

        $this->executeAction('queueing_' . $website->slug, function () use ($website) {
            try {
                // Fetch pending entries directly from database (avoid deadlock with single-threaded dev server)
                $entries = DataEntry::with('website')
                    ->readyToProcess()
                    ->where('website_id', $website->id)
                    ->limit(50)
                    ->get();
                
                if ($entries->isEmpty()) {
                    Notification::make()
                        ->title('No Pending Jobs')
                        ->body("No pending jobs for {$website->name}.")
                        ->info()
                        ->send();
                    return;
                }
                
                // Send directly to bot engine via /jobs/batch
                $response = Http::timeout(30)->post("{$this->botEngineUrl}/jobs/batch", [
                    'entries' => $entries->map(fn ($entry) => [
                        'id' => $entry->id,
                        'identifier' => $entry->identifier,
                        'data' => $entry->data,
                        'priority' => $entry->priority ?? 0,
                        'max_attempts' => $entry->max_attempts,
                    ])->values()->toArray(),
                    'websiteSlug' => $website->slug,
                ]);
                
                if ($response->successful()) {
                    // Mark entries as queued
                    DataEntry::whereIn('id', $entries->pluck('id'))->update(['status' => 'queued']);
                    $fetched = $entries->count();
        
                    Notification::make()
                        ->title('Jobs Queued')
                        ->body("{$fetched} jobs queued for {$website->name}.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Notification::make()
                    ->title('Connection Timeout')
                    ->body("Bot engine is taking too long to process jobs for {$website->name}. The operation is running in the background. Please check status in a moment.")
                    ->warning()
                    ->duration(10000)
                    ->send();
                Log::error("Queue jobs timeout for {$website->slug}: " . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Queue Jobs')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Clear queue for specific website
     */
    public function clearWebsiteQueue(string $slug): void
    {
        $this->executeAction('clearing_' . $slug, function () use ($slug) {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->delete("{$this->botEngineUrl}/website/{$slug}/queue");
                
                if ($response->successful()) {
                    // Reset data entries status back to pending
                    $website = Website::where('slug', $slug)->first();
                    if ($website) {
                        DataEntry::where('website_id', $website->id)
                            ->where('status', 'queued')
                            ->update(['status' => 'pending']);
                    }
                    
                    Notification::make()
                        ->title('Queue Cleared')
                        ->body("Queue for {$slug} has been cleared.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Clear Queue')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Clear all queues
     */
    public function clearAllQueues(): void
    {
        $this->executeAction('clearing_all', function () {
            try {
                $response = Http::connectTimeout(10)->timeout(120)->delete("{$this->botEngineUrl}/queue");
                
                if ($response->successful()) {
                    // Reset all queued entries back to pending
                    DataEntry::where('status', 'queued')
                        ->update(['status' => 'pending']);
                    
                    Notification::make()
                        ->title('All Queues Cleared')
                        ->body('All job queues have been cleared.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Clear Queues')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Stop a specific session
     */
    public function stopSession(int $sessionId): void
    {
        try {
            $session = BotSession::find($sessionId);
            if ($session) {
                $session->markAsStopped();
                
                Notification::make()
                    ->title('Session Stopped')
                    ->body("Session has been marked as stopped.")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Stop Session')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->refreshBotStatus();
        }
    }

    /**
     * Mark stale sessions as error
     */
    public function cleanupStaleSessions(): void
    {
        try {
            $staleSessions = BotSession::active()
                ->where('last_activity_at', '<', now()->subMinutes(2))
                ->get();
            
            $staleCount = 0;
            foreach ($staleSessions as $session) {
                $session->markAsError('Session became unresponsive');
                $staleCount++;
            }
            
            if ($staleCount > 0) {
                Notification::make()
                    ->title('Stale Sessions Cleaned')
                    ->body("{$staleCount} stale sessions have been marked as error.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('No Stale Sessions')
                    ->body('All sessions are responding normally.')
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Cleanup Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->refreshBotStatus();
        }
    }

    /**
     * Get status color for badges
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'running' => 'success',
            'paused' => 'warning',
            'disabled' => 'gray',
            'idle' => 'gray',
            'stopped' => 'gray',
            'error' => 'danger',
            'not_started' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get worker status label
     */
    public static function getWorkerStatusLabel(string $status): string
    {
        return match ($status) {
            'running' => 'Running',
            'paused' => 'Paused',
            'disabled' => 'Disabled',
            'idle' => 'Idle',
            'stopped' => 'Stopped',
            'error' => 'Error',
            'not_started' => 'Not Started',
            default => ucfirst($status),
        };
    }

    /**
     * Add a worker for a specific website
     * Validates max 2 workers per website constraint
     */
    public function addWorker(string $slug, int $concurrency = 2, int $maxJobsPerMinute = 10): void
    {
        // Check if already has a worker (max 1 worker per website in current architecture)
        // In multi-worker per website scenario, we could track count
        $website = Website::where('slug', $slug)->first();
        
        if (!$website) {
            Notification::make()
                ->title('Website Not Found')
                ->body("Website with slug '{$slug}' was not found.")
                ->danger()
                ->send();
            return;
        }
        
        // Validate concurrency limit (max 2 concurrent jobs per website to prevent detection)
        if ($concurrency > 2) {
            Notification::make()
                ->title('Concurrency Limit Exceeded')
                ->body('Maximum concurrency per website is 2 to avoid detection.')
                ->warning()
                ->send();
            $concurrency = 2;
        }

        $this->executeAction('adding_worker_' . $slug, function () use ($slug, $concurrency, $maxJobsPerMinute) {
            try {
                $response = Http::connectTimeout(10)->timeout(60)->post("{$this->botEngineUrl}/website/{$slug}/worker", [
                    'concurrency' => $concurrency,
                    'maxJobsPerMinute' => $maxJobsPerMinute,
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if ($data['status'] === 'exists') {
                        Notification::make()
                            ->title('Worker Already Exists')
                            ->body("A worker for {$slug} is already running.")
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Worker Added')
                            ->body("Worker for {$slug} has been created with concurrency {$concurrency}.")
                            ->success()
                            ->send();
                    }
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Notification::make()
                    ->title('Connection Timeout')
                    ->body("Bot engine is taking too long to add worker for {$slug}. The operation may still be in progress. Please check status in a moment.")
                    ->warning()
                    ->duration(10000)
                    ->send();
                Log::error("Add worker timeout for {$slug}: " . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Add Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Disable worker for a website (pause without removing)
     */
    public function disableWebsite(string $slug): void
    {
        $this->executeAction('disabling_' . $slug, function () use ($slug) {
            try {
                // First mark website as inactive in Laravel (this is the source of truth)
                $website = Website::where('slug', $slug)->first();
                if ($website) {
                    $website->update(['is_active' => false]);
                }
                
                // Then try to pause the worker in bot engine (if it exists)
                try {
                    $response = Http::connectTimeout(10)->timeout(30)->post("{$this->botEngineUrl}/website/{$slug}/pause");
                    // We don't care if this fails - the important thing is is_active=false
                } catch (\Exception $e) {
                    // Worker might not exist, that's fine
                    Log::info("Could not pause worker for {$slug} (may not exist): " . $e->getMessage());
                }
                
                Notification::make()
                    ->title('Worker Disabled')
                    ->body("Worker for {$slug} has been marked as inactive.")
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Disable Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Enable worker for a website (create if not exists, or resume if paused)
     */
    public function enableWebsite(string $slug, int $concurrency = 2, int $maxJobsPerMinute = 10): void
    {
        $this->executeAction('enabling_' . $slug, function () use ($slug, $concurrency, $maxJobsPerMinute) {
            try {
                // First mark website as active in Laravel (this is the source of truth)
                // This must be done FIRST so bot engine can fetch the config
                $website = Website::where('slug', $slug)->first();
                if (!$website) {
                    throw new \Exception("Website {$slug} not found");
                }
                $website->update(['is_active' => true]);
                
                // Check if worker exists in bot engine
                $workerExists = false;
                try {
                    $checkResponse = Http::connectTimeout(5)->timeout(10)->get("{$this->botEngineUrl}/website/{$slug}/worker");
                    $workerExists = $checkResponse->successful() && $checkResponse->json('exists');
                } catch (\Exception $e) {
                    // Could not check, assume worker doesn't exist
                    Log::info("Could not check worker status for {$slug}: " . $e->getMessage());
                }
                
                if ($workerExists) {
                    // Worker exists, just resume it
                    $response = Http::connectTimeout(10)->timeout(120)->post("{$this->botEngineUrl}/website/{$slug}/resume");
                    
                    if ($response->successful()) {
                        Notification::make()
                            ->title('Worker Enabled')
                            ->body("Worker for {$slug} has been resumed and marked as active.")
                            ->success()
                            ->send();
                    } else {
                        throw new \Exception($response->json('error') ?? 'Failed to resume worker');
                    }
                } else {
                    // Worker doesn't exist, create it
                    $response = Http::connectTimeout(10)->timeout(120)->post("{$this->botEngineUrl}/website/{$slug}/worker", [
                        'concurrency' => $concurrency,
                        'maxJobsPerMinute' => $maxJobsPerMinute,
                    ]);
                    
                    if ($response->successful()) {
                        Notification::make()
                            ->title('Worker Enabled')
                            ->body("Worker for {$slug} has been created and started.")
                            ->success()
                            ->send();
                    } else {
                        // Rollback is_active if worker creation failed
                        $website->update(['is_active' => false]);
                        throw new \Exception($response->json('error') ?? 'Failed to create worker');
                    }
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Rollback is_active on connection failure
                $website = Website::where('slug', $slug)->first();
                if ($website) {
                    $website->update(['is_active' => false]);
                }
                
                Notification::make()
                    ->title('Connection Timeout')
                    ->body("Bot engine is taking too long to enable worker for {$slug}. The operation may have timed out. Please check if the bot engine is running and try again.")
                    ->danger()
                    ->duration(10000)
                    ->send();
                Log::error("Enable worker timeout for {$slug}: " . $e->getMessage());
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Enable Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Remove a worker completely (advanced, for cleanup only)
     */
    public function removeWorker(string $slug): void
    {
        $this->executeAction('removing_worker_' . $slug, function () use ($slug) {
            try {
                $response = Http::connectTimeout(10)->timeout(30)->delete("{$this->botEngineUrl}/website/{$slug}/worker");
                
                if ($response->successful()) {
                    Notification::make()
                        ->title('Worker Removed')
                        ->body("Worker for {$slug} has been completely removed from bot engine.")
                        ->warning()
                        ->send();
                } else {
                    throw new \Exception($response->json('error') ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Failed to Remove Worker')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * Get worker count for a website
     * Used for validation (max 2 workers per website)
     */
    public function getWorkerCountForWebsite(string $slug): int
    {
        // In current architecture, each website has at most 1 worker
        // The concurrency setting controls parallel jobs within that worker
        if (isset($this->workerStatus['workers'][$slug])) {
            return 1;
        }
        return 0;
    }

    /**
     * Check if website can have more workers
     */
    public function canAddWorker(string $slug): bool
    {
        return $this->getWorkerCountForWebsite($slug) < 1;
    }

    /**
     * Confirmation action for clearing all queues
     */
    public function confirmClearAllQueuesAction(): Action
    {
        return Action::make('confirmClearAllQueues')
            ->requiresConfirmation()
            ->modalHeading('Clear All Queues?')
            ->modalDescription('This will remove ALL jobs from all queues. All queued entries will be reset to pending status. This action cannot be undone.')
            ->modalSubmitActionLabel('Yes, Clear All')
            ->color('danger')
            ->action(fn () => $this->clearAllQueues());
    }
    
    /**
     * Confirmation action for reloading config
     */
    public function confirmReloadConfigAction(): Action
    {
        return Action::make('confirmReloadConfig')
            ->requiresConfirmation()
            ->modalHeading('Reload Configuration?')
            ->modalDescription('This will gracefully reload website configurations. Only changed workers will be recreated — running jobs will not be interrupted.')
            ->modalSubmitActionLabel('Yes, Reload')
            ->color('warning')
            ->action(fn () => $this->reloadConfig());
    }

    /**
     * Confirmation action for clearing specific queue
     */
    public function confirmClearQueueAction(): Action
    {
        return Action::make('confirmClearQueue')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Clear Queue?')
            ->modalDescription(fn (array $arguments) => "Clear all jobs in queue for {$arguments['name']}? Entries will be reset to pending status.")
            ->modalSubmitActionLabel('Yes, Clear Queue')
            ->color('danger')
            ->action(fn (array $arguments) => $this->clearWebsiteQueue($arguments['slug']));
    }
    
    /**
     * Confirmation action for disabling worker
     */
    public function confirmDisableWorkerAction(): Action
    {
        return Action::make('confirmDisableWorker')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Disable Worker?')
            ->modalDescription(fn (array $arguments) => "Disable worker for {$arguments['name']}? The worker will be paused but not removed. You can re-enable it anytime.")
            ->modalSubmitActionLabel('Yes, Disable')
            ->color('gray')
            ->action(fn (array $arguments) => $this->disableWebsite($arguments['slug']));
    }

    /**
     * Confirmation action for stopping a session
     */
    public function confirmStopSessionAction(): Action
    {
        return Action::make('confirmStopSession')
            ->requiresConfirmation()
            ->modalHeading('Stop Session?')
            ->modalDescription(fn (array $arguments) => "Are you sure you want to stop session {$arguments['sessionId']}? This will mark the session as stopped.")
            ->modalSubmitActionLabel('Yes, Stop Session')
            ->color('danger')
            ->action(fn (array $arguments) => $this->stopSession((int) $arguments['id']));
    }
}
