<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataEntry;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    /**
     * Get next batch of jobs to process
     */
    public function getNextBatch(Request $request): JsonResponse
    {
        $request->validate([
            'website_id' => 'nullable|exists:websites,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->input('limit', 10);
        $websiteId = $request->input('website_id');

        $query = DataEntry::with('website')
            ->readyToProcess();

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $entries = $query->limit($limit)->get();

        // Mark as queued
        $entryIds = $entries->pluck('id');
        DataEntry::whereIn('id', $entryIds)->update(['status' => 'queued']);

        return response()->json([
            'success' => true,
            'data' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'website_id' => $entry->website_id,
                'websiteSlug' => $entry->website?->slug,
                'identifier' => $entry->identifier,
                'data' => $entry->data,
                'max_attempts' => $entry->max_attempts,
                'attempts' => $entry->attempts,
                'priority' => $entry->priority ?? 0,
                'proxy_id' => $entry->proxy_id,
            ]),
            'count' => $entries->count(),
        ]);
    }

    /**
     * Get a single job by ID
     */
    public function getJob(int $id): JsonResponse
    {
        $entry = DataEntry::with(['website.formSteps.formFields', 'proxy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $entry->id,
                'website_id' => $entry->website_id,
                'identifier' => $entry->identifier,
                'data' => $entry->data,
                'status' => $entry->status,
                'attempts' => $entry->attempts,
                'max_attempts' => $entry->max_attempts,
                'website_config' => $entry->website->full_config,
                'proxy' => $entry->proxy?->config,
            ],
        ]);
    }

    /**
     * Mark job as processing
     */
    public function markProcessing(int $id): JsonResponse
    {
        $entry = DataEntry::findOrFail($id);
        $entry->markAsProcessing();

        return response()->json([
            'success' => true,
            'message' => 'Job marked as processing',
        ]);
    }

    /**
     * Mark job as success
     */
    public function markSuccess(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string',
            'result_data' => 'nullable|array',
            'screenshot_path' => 'nullable|string',
        ]);

        $entry = DataEntry::findOrFail($id);
        $entry->markAsSuccess(
            $request->input('message'),
            $request->input('result_data'),
            $request->input('screenshot_path')
        );

        return response()->json([
            'success' => true,
            'message' => 'Job marked as success',
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markFailed(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'error_message' => 'nullable|string',
            'screenshot_path' => 'nullable|string',
        ]);

        $entry = DataEntry::findOrFail($id);
        $entry->markAsFailed(
            $request->input('error_message'),
            $request->input('screenshot_path')
        );

        return response()->json([
            'success' => true,
            'message' => 'Job marked as failed',
            'can_retry' => $entry->canRetry(),
        ]);
    }

    /**
     * Get job statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $websiteId = $request->input('website_id');

        $query = DataEntry::query();
        
        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'queued' => (clone $query)->where('status', 'queued')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'success' => (clone $query)->where('status', 'success')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];

        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['success'] / $stats['total']) * 100, 2) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
