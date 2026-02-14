<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proxy;
use App\Models\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProxyController extends Controller
{
    /**
     * Get next available proxy
     */
    public function getNext(Request $request): JsonResponse
    {
        $excludeIds = $request->input('exclude_ids', []);

        $proxy = Proxy::active()
            ->whereNotIn('id', $excludeIds)
            ->bySuccessRate()
            ->first();

        if (!$proxy) {
            return response()->json([
                'success' => false,
                'message' => 'No available proxies',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $proxy->config,
        ]);
    }

    /**
     * Report proxy success
     */
    public function reportSuccess(int $id): JsonResponse
    {
        $proxy = Proxy::findOrFail($id);
        $proxy->incrementSuccess();

        return response()->json([
            'success' => true,
            'message' => 'Proxy success recorded',
        ]);
    }

    /**
     * Report proxy failure
     */
    public function reportFailure(int $id): JsonResponse
    {
        $proxy = Proxy::findOrFail($id);
        $proxy->incrementFailure();

        // Disable proxy if failure rate is too high
        if ($proxy->success_rate < 20 && ($proxy->success_count + $proxy->failure_count) >= 10) {
            $proxy->update(['is_active' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proxy failure recorded',
            'disabled' => !$proxy->is_active,
        ]);
    }

    /**
     * Report CAPTCHA service success
     */
    public function reportCaptchaSuccess(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'solve_time' => 'nullable|integer',
        ]);

        $service = CaptchaService::findOrFail($id);
        $service->incrementSuccess($request->input('solve_time'));

        return response()->json([
            'success' => true,
            'message' => 'CAPTCHA success recorded',
        ]);
    }

    /**
     * Report CAPTCHA service failure
     */
    public function reportCaptchaFailure(int $id): JsonResponse
    {
        $service = CaptchaService::findOrFail($id);
        $service->incrementFailure();

        return response()->json([
            'success' => true,
            'message' => 'CAPTCHA failure recorded',
        ]);
    }
}
