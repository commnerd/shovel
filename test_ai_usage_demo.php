<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Services\AI\AIUsageService;

echo "🤖 AI Usage Metrics Demo\n";
echo "========================\n\n";

try {
    $aiUsageService = new AIUsageService();
    $metrics = $aiUsageService->getUsageMetrics();

    echo "📊 Current AI Usage Metrics:\n";
    echo "Status: " . $metrics['status'] . "\n";
    echo "Last Updated: " . $metrics['last_updated'] . "\n\n";

    if ($metrics['status'] === 'success') {
        echo "✅ API Usage (from OpenAI):\n";
        if ($metrics['api_usage']) {
            echo "  - Total Requests: " . $metrics['api_usage']['total_requests'] . "\n";
            echo "  - Total Tokens: " . number_format($metrics['api_usage']['total_tokens']) . "\n";
            echo "  - Total Cost: $" . $metrics['api_usage']['total_cost'] . "\n";
            echo "  - Period: " . ($metrics['api_usage']['period'] ?? 'Daily') . "\n";
        } else {
            echo "  - No API usage data available\n";
        }

        echo "\n📈 Local Usage (from logs):\n";
        echo "Today:\n";
        echo "  - Requests: " . $metrics['local_usage']['today']['requests'] . "\n";
        echo "  - Successful: " . $metrics['local_usage']['today']['successful_requests'] . "\n";
        echo "  - Failed: " . $metrics['local_usage']['today']['failed_requests'] . "\n";
        echo "  - Estimated Tokens: " . number_format($metrics['local_usage']['today']['tokens_estimated']) . "\n";
        echo "  - Estimated Cost: $" . $metrics['local_usage']['today']['cost_estimated'] . "\n";

        echo "\nThis Month:\n";
        echo "  - Total Requests: " . $metrics['local_usage']['month']['requests'] . "\n";
        echo "  - Estimated Tokens: " . number_format($metrics['local_usage']['month']['tokens_estimated']) . "\n";
        echo "  - Estimated Cost: $" . $metrics['local_usage']['month']['cost_estimated'] . "\n";

        if ($metrics['quota_info']) {
            echo "\n💰 Quota Information:\n";
            echo "  - Hard Limit: $" . $metrics['quota_info']['hard_limit_usd'] . "\n";
            echo "  - Soft Limit: $" . $metrics['quota_info']['soft_limit_usd'] . "\n";
            echo "  - Payment Method: " . ($metrics['quota_info']['has_payment_method'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "❌ Error: " . ($metrics['error'] ?? 'Unknown error') . "\n";
        echo "\n📈 Local Usage (from logs):\n";
        echo "Today:\n";
        echo "  - Requests: " . $metrics['local_usage']['today']['requests'] . "\n";
        echo "  - Successful: " . $metrics['local_usage']['today']['successful_requests'] . "\n";
        echo "  - Failed: " . $metrics['local_usage']['today']['failed_requests'] . "\n";
        echo "  - Estimated Cost: $" . $metrics['local_usage']['today']['cost_estimated'] . "\n";
    }

    echo "\n🧪 Testing Usage Logging:\n";
    $aiUsageService->logUsage('openai', 'gpt-3.5-turbo', 500, 0.00075);
    echo "  - Logged test usage: 500 tokens, $0.00075 cost\n";

    $aiUsageService->logError('openai', 'gpt-3.5-turbo', 'Test error for demo');
    echo "  - Logged test error\n";

    echo "\n✅ Demo completed successfully!\n";

} catch (\Exception $e) {
    echo "❌ Error running demo: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
