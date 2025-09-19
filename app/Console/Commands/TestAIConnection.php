<?php

namespace App\Console\Commands;

use App\Services\AI\AIManager;
use Illuminate\Console\Command;

class TestAIConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test {provider?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI provider connections';

    /**
     * Execute the console command.
     */
    public function handle(AIManager $ai)
    {
        $provider = $this->argument('provider');

        $this->info('Testing AI Provider Connections...');
        $this->newLine();

        if ($provider) {
            $this->testSingleProvider($ai, $provider);
        } else {
            $this->testAllProviders($ai);
        }
    }

    /**
     * Test a single provider.
     */
    protected function testSingleProvider(AIManager $ai, string $providerName): void
    {
        $this->info("Testing {$providerName} provider...");

        try {
            $result = $ai->testProvider($providerName);

            if ($result['success']) {
                $this->info("✅ {$providerName}: ".$result['message']);
                if (isset($result['response_time'])) {
                    $this->line('   Response time: '.round($result['response_time'], 3).'s');
                }
                if (isset($result['tokens_used'])) {
                    $this->line('   Tokens used: '.$result['tokens_used']);
                }
            } else {
                $this->error("❌ {$providerName}: ".$result['message']);
            }
        } catch (\Exception $e) {
            $this->error("❌ {$providerName}: ".$e->getMessage());
        }
    }

    /**
     * Test all available providers.
     */
    protected function testAllProviders(AIManager $ai): void
    {
        $providers = $ai->getAvailableProviders();

        foreach ($providers as $name => $info) {
            if (isset($info['error'])) {
                $this->error("❌ {$name}: ".$info['error']);

                continue;
            }

            if (! $info['configured']) {
                $this->warn("⚠️  {$name}: Not configured (missing API key)");

                continue;
            }

            $this->testSingleProvider($ai, $name);
        }

        $this->newLine();
        $this->info('Test completed!');

        if (! $ai->hasConfiguredProvider()) {
            $this->warn('No AI providers are properly configured. Please set up API keys in your .env file.');
        }
    }
}
