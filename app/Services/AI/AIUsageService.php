<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIUsageService
{
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key');
        $this->baseUrl = config('ai.providers.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Get current usage metrics from OpenAI API
     */
    public function getUsageMetrics(): array
    {
        try {
            // Try to get usage from API first
            $apiUsage = $this->getApiUsage();

            // Get local usage tracking
            $localUsage = $this->getLocalUsage();

            // Get quota information
            $quotaInfo = $this->getQuotaInfo();

            return [
                'api_usage' => $apiUsage,
                'local_usage' => $localUsage,
                'quota_info' => $quotaInfo,
                'status' => 'success',
                'last_updated' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch AI usage metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return local usage only if API fails
            return [
                'api_usage' => null,
                'local_usage' => $this->getLocalUsage(),
                'quota_info' => null,
                'status' => 'error',
                'error' => 'Unable to fetch API usage metrics',
                'last_updated' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get usage data from OpenAI API
     */
    private function getApiUsage(): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        // Cache API usage for 5 minutes to avoid rate limiting
        return Cache::remember('ai_usage_api', 300, function () {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/usage', [
                'date' => now()->format('Y-m-d'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'total_requests' => $data['total_requests'] ?? 0,
                    'total_tokens' => $data['total_tokens'] ?? 0,
                    'total_cost' => $data['total_cost'] ?? 0,
                    'date' => $data['date'] ?? now()->format('Y-m-d'),
                ];
            }

            // If usage endpoint fails, try to get billing info
            return $this->getBillingUsage();
        });
    }

    /**
     * Get billing usage information
     */
    private function getBillingUsage(): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/billing/usage', [
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
                'end_date' => now()->endOfMonth()->format('Y-m-d'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'total_requests' => $data['total_requests'] ?? 0,
                    'total_tokens' => $data['total_tokens'] ?? 0,
                    'total_cost' => $data['total_cost'] ?? 0,
                    'period' => 'monthly',
                    'start_date' => $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $data['end_date'] ?? now()->endOfMonth()->format('Y-m-d'),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch billing usage', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get quota information
     */
    private function getQuotaInfo(): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/billing/subscription');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'hard_limit_usd' => $data['hard_limit_usd'] ?? null,
                    'soft_limit_usd' => $data['soft_limit_usd'] ?? null,
                    'system_hard_limit_usd' => $data['system_hard_limit_usd'] ?? null,
                    'system_soft_limit_usd' => $data['system_soft_limit_usd'] ?? null,
                    'access_until' => $data['access_until'] ?? null,
                    'has_payment_method' => $data['has_payment_method'] ?? false,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch quota info', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get local usage tracking from logs/database
     */
    private function getLocalUsage(): array
    {
        // Get today's usage from logs
        $todayUsage = $this->getTodayLocalUsage();

        // Get this month's usage
        $monthUsage = $this->getMonthLocalUsage();

        // Get recent AI requests count
        $recentRequests = $this->getRecentRequestsCount();

        return [
            'today' => $todayUsage,
            'month' => $monthUsage,
            'recent_requests' => $recentRequests,
        ];
    }

    /**
     * Get today's local usage
     */
    private function getTodayLocalUsage(): array
    {
        // Count AI requests from logs today
        $logFile = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');

        if (!file_exists($logFile)) {
            return [
                'requests' => 0,
                'tokens_estimated' => 0,
                'cost_estimated' => 0,
            ];
        }

        $logContent = file_get_contents($logFile);
        $aiRequestCount = substr_count($logContent, 'AI request completed');
        $aiErrorCount = substr_count($logContent, 'AI request failed');

        // Estimate tokens (rough calculation based on average request size)
        $estimatedTokens = ($aiRequestCount + $aiErrorCount) * 500; // Average 500 tokens per request

        // Estimate cost (rough calculation for gpt-3.5-turbo)
        $estimatedCost = $estimatedTokens * 0.0000015; // $0.0015 per 1K tokens

        return [
            'requests' => $aiRequestCount + $aiErrorCount,
            'successful_requests' => $aiRequestCount,
            'failed_requests' => $aiErrorCount,
            'tokens_estimated' => $estimatedTokens,
            'cost_estimated' => round($estimatedCost, 4),
        ];
    }

    /**
     * Get this month's local usage
     */
    private function getMonthLocalUsage(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $totalRequests = 0;
        $totalTokens = 0;
        $totalCost = 0;

        // Count logs for each day of the month
        for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $logFile = storage_path('logs/laravel-' . $date->format('Y-m-d') . '.log');

            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $dailyRequests = substr_count($logContent, 'AI request completed') + substr_count($logContent, 'AI request failed');
                $totalRequests += $dailyRequests;
            }
        }

        $totalTokens = $totalRequests * 500; // Estimate
        $totalCost = $totalTokens * 0.0000015; // Estimate

        return [
            'requests' => $totalRequests,
            'tokens_estimated' => $totalTokens,
            'cost_estimated' => round($totalCost, 4),
        ];
    }

    /**
     * Get recent requests count (last 24 hours)
     */
    private function getRecentRequestsCount(): int
    {
        $logFile = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');

        if (!file_exists($logFile)) {
            return 0;
        }

        $logContent = file_get_contents($logFile);
        return substr_count($logContent, 'AI request completed') + substr_count($logContent, 'AI request failed');
    }

    /**
     * Log AI usage for tracking
     */
    public function logUsage(string $provider, string $model, int $tokens = 0, float $cost = 0): void
    {
        Log::info('AI request completed', [
            'provider' => $provider,
            'model' => $model,
            'tokens' => $tokens,
            'cost' => $cost,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log AI error for tracking
     */
    public function logError(string $provider, string $model, string $error): void
    {
        Log::error('AI request failed', [
            'provider' => $provider,
            'model' => $model,
            'error' => $error,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
