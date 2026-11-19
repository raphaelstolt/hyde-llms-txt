<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt;

use Hyde\Framework\Services\BuildTaskService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Stolt\HydeLlmsTxt\BuildTasks\GenerateLlmsTxtTask;

class LlmsTxtServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Merge package configuration if the user has published it.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/hyde-llms-txt.php',
            key: 'hyde-llms-txt'
        );
    }

    /**
     * Bootstrap the service provider and register the build task.
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        // Only register the build task if generation is enabled.
        if ($this->isEnabled()) {
            $this->app->make(BuildTaskService::class)
                ->registerTask(GenerateLlmsTxtTask::class);
        }
    }

    /**
     * Determine if llms.txt generation is enabled.
     */
    private function isEnabled(): bool
    {
        /** @var mixed $value */
        $value = config('hyde-llms-txt.enabled', default: true);
        return $value === true;
    }
}
