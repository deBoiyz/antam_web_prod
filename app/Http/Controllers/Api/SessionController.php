<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    /**
     * Register a new bot session
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'website_id' => 'nullable|exists:websites,id',
            'worker_hostname' => 'nullable|string',
            'worker_pid' => 'nullable|integer',
        ]);

        $session = BotSession::create([
            'session_id' => Str::uuid()->toString(),
            'website_id' => $request->input('website_id'),
            'status' => 'idle',
            'started_at' => now(),
            'last_activity_at' => now(),
            'worker_hostname' => $request->input('worker_hostname'),
            'worker_pid' => $request->input('worker_pid'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'id' => $session->id,
            ],
        ]);
    }

    /**
     * Update session status
     */
    public function updateStatus(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:idle,running,paused,stopped,error',
            'current_job_id' => 'nullable|integer',
            'system_info' => 'nullable|array',
        ]);

        $session = BotSession::where('session_id', $sessionId)->firstOrFail();

        $updateData = [
            'status' => $request->input('status'),
            'last_activity_at' => now(),
        ];

        if ($request->has('current_job_id')) {
            $updateData['current_job_id'] = $request->input('current_job_id');
        }

        if ($request->has('system_info') && is_array($request->input('system_info'))) {
            $updateData['system_info'] = array_merge(
                $session->system_info ?? [],
                $request->input('system_info')
            );
        }

        $session->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Session status updated',
        ]);
    }

    /**
     * Record job completion
     */
    public function recordCompletion(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'success' => 'required|boolean',
        ]);

        $session = BotSession::where('session_id', $sessionId)->firstOrFail();

        if ($request->input('success')) {
            $session->recordSuccess();
        } else {
            $session->recordFailure();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'processed_count' => $session->processed_count,
                'success_count' => $session->success_count,
                'failure_count' => $session->failure_count,
            ],
        ]);
    }

    /**
     * Heartbeat - keep session alive
     */
    public function heartbeat(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'system_info' => 'nullable|array',
        ]);

        $session = BotSession::where('session_id', $sessionId)->firstOrFail();

        $updateData = ['last_activity_at' => now()];

        if ($request->has('system_info')) {
            $updateData['system_info'] = $request->input('system_info');
        }

        $session->update($updateData);

        // Check if session should stop
        $shouldStop = $session->status === 'stopped';

        return response()->json([
            'success' => true,
            'should_stop' => $shouldStop,
            'status' => $session->status,
        ]);
    }

    /**
     * Unregister session
     */
    public function unregister(string $sessionId): JsonResponse
    {
        $session = BotSession::where('session_id', $sessionId)->firstOrFail();
        $session->markAsStopped();

        return response()->json([
            'success' => true,
            'message' => 'Session unregistered',
        ]);
    }

    /**
     * Cleanup stale sessions (no activity for 2+ minutes)
     */
    public function cleanupStale(): JsonResponse
    {
        $cleanedCount = BotSession::cleanupStale(2);

        return response()->json([
            'success' => true,
            'cleaned_count' => $cleanedCount,
            'message' => $cleanedCount > 0 
                ? "{$cleanedCount} stale session(s) marked as error"
                : 'No stale sessions found',
        ]);
    }

    /**
     * Get active sessions
     */
    public function getActive(): JsonResponse
    {
        $sessions = BotSession::with('website')
            ->active()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }
}
