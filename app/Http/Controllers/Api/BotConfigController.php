<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\DataEntry;
use App\Models\FormStep;
use App\Models\CaptchaService;
use App\Models\Proxy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BotConfigController extends Controller
{
    /**
     * Get full configuration for a website
     */
    public function getWebsiteConfig(int $websiteId): JsonResponse
    {
        $website = Website::with(['formSteps.formFields'])
            ->findOrFail($websiteId);

        return response()->json([
            'success' => true,
            'data' => $website->full_config,
        ]);
    }

    /**
     * Get website configuration by slug (ignores is_active, used for enabling workers)
     */
    public function getWebsiteConfigBySlug(string $slug): JsonResponse
    {
        $website = Website::with(['formSteps.formFields'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $website->full_config,
        ]);
    }

    /**
     * Get all active website configurations
     */
    public function getAllConfigs(): JsonResponse
    {
        $websites = Website::with(['formSteps.formFields'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $websites->map(fn ($w) => $w->full_config),
        ]);
    }

    /**
     * Get ALL website configurations including inactive (for startup initialization)
     */
    public function getAllConfigsIncludingInactive(): JsonResponse
    {
        $websites = Website::with(['formSteps.formFields'])->get();

        return response()->json([
            'success' => true,
            'data' => $websites->map(fn ($w) => array_merge($w->full_config, [
                'is_active' => $w->is_active,
            ])),
        ]);
    }

    /**
     * Get active CAPTCHA services
     */
    public function getCaptchaServices(): JsonResponse
    {
        $services = CaptchaService::active()
            ->byPriority()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'provider' => $s->provider,
                'api_key' => $s->api_key,
                'api_url' => $s->api_url,
                'supported_types' => $s->supported_types,
                'is_default' => $s->is_default,
            ]);

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * Get active proxies
     */
    public function getProxies(): JsonResponse
    {
        $proxies = Proxy::active()
            ->bySuccessRate()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'host' => $p->host,
                'port' => $p->port,
                'username' => $p->username,
                'password' => $p->password,
                'url' => $p->connection_url,
                'is_rotating' => $p->is_rotating,
            ]);

        return response()->json([
            'success' => true,
            'data' => $proxies,
        ]);
    }
}
