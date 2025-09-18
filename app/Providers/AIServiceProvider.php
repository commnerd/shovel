<?php

namespace App\Providers;

use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIManager::class, function ($app) {
            return new AIManager($app);
        });

        $this->app->alias(AIManager::class, 'ai');

        // Bind the default provider
        $this->app->bind(AIProviderInterface::class, function ($app) {
            return $app->make(AIManager::class)->provider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            AIManager::class,
            'ai',
            AIProviderInterface::class,
        ];
    }
}
