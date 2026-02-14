<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobLog;
use App\Models\DataEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    /**
     * Log job start
     */
    public function logStart(Request $request): JsonResponse
    {
        $request->validate([
            'data_entry_id' => 'required|exists:data_entries,id',
            'proxy_id' => 'nullable|exists:proxies,id',
            'session_id' => 'nullable|string',
        ]);

        $entry = DataEntry::findOrFail($request->input('data_entry_id'));

        $log = JobLog::logStart(
            $entry,
            $request->input('proxy_id'),
            $request->input('session_id')
        );

        return response()->json([
            'success' => true,
            'data' => ['log_id' => $log->id],
        ]);
    }

    /**
     * Log step completion
     */
    public function logStep(Request $request): JsonResponse
    {
        $request->validate([
            'data_entry_id' => 'required|exists:data_entries,id',
            'step_number' => 'required|integer',
            'step_name' => 'required|string',
            'duration_ms' => 'required|integer',
            'message' => 'nullable|string',
            'details' => 'nullable|array',
        ]);

        $entry = DataEntry::findOrFail($request->input('data_entry_id'));

        $log = JobLog::logStepCompleted(
            $entry,
            $request->input('step_number'),
            $request->input('step_name'),
            $request->input('duration_ms'),
            $request->input('message'),
            $request->input('details')
        );

        return response()->json([
            'success' => true,
            'data' => ['log_id' => $log->id],
        ]);
    }

    /**
     * Log success
     */
    public function logSuccess(Request $request): JsonResponse
    {
        $request->validate([
            'data_entry_id' => 'required|exists:data_entries,id',
            'message' => 'required|string',
            'details' => 'nullable|array',
            'screenshot_path' => 'nullable|string',
            'duration_ms' => 'nullable|integer',
        ]);

        $entry = DataEntry::findOrFail($request->input('data_entry_id'));

        $log = JobLog::logSuccess(
            $entry,
            $request->input('message'),
            $request->input('details'),
            $request->input('screenshot_path'),
            $request->input('duration_ms')
        );

        return response()->json([
            'success' => true,
            'data' => ['log_id' => $log->id],
        ]);
    }

    /**
     * Log failure
     */
    public function logFailure(Request $request): JsonResponse
    {
        $request->validate([
            'data_entry_id' => 'required|exists:data_entries,id',
            'message' => 'required|string',
            'details' => 'nullable|array',
            'screenshot_path' => 'nullable|string',
            'duration_ms' => 'nullable|integer',
        ]);

        $entry = DataEntry::findOrFail($request->input('data_entry_id'));

        $log = JobLog::logFailure(
            $entry,
            $request->input('message'),
            $request->input('details'),
            $request->input('screenshot_path'),
            $request->input('duration_ms')
        );

        return response()->json([
            'success' => true,
            'data' => ['log_id' => $log->id],
        ]);
    }

    /**
     * Get recent logs
     */
    public function getRecent(Request $request): JsonResponse
    {
        $request->validate([
            'website_id' => 'nullable|exists:websites,id',
            'data_entry_id' => 'nullable|exists:data_entries,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = JobLog::with(['website', 'dataEntry'])
            ->recent($request->input('limit', 50));

        if ($request->has('website_id')) {
            $query->where('website_id', $request->input('website_id'));
        }

        if ($request->has('data_entry_id')) {
            $query->where('data_entry_id', $request->input('data_entry_id'));
        }

        $logs = $query->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
