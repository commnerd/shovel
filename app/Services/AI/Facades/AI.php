<?php

namespace App\Services\AI\Facades;

use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Contracts\AITaskResponse;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AIProviderInterface provider(string $name = null)
 * @method static AIResponse chat(array $messages, array $options = [])
 * @method static AITaskResponse generateTasks(string $projectDescription, array $schema = [], array $options = [])
 * @method static string analyzeProject(string $projectDescription, array $existingTasks = [], array $options = [])
 * @method static array suggestTaskImprovements(array $tasks, array $options = [])
 * @method static bool hasConfiguredProvider()
 * @method static array getAvailableProviders()
 * @method static array testProvider(string $name = null)
 *
 * @see AIManager
 */
class AI extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ai';
    }
}
