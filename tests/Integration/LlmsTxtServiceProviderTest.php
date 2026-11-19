<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\Tests\Integration;

// Bootstrap the test container and config() helper
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Stolt\HydeLlmsTxt\LlmsTxtServiceProvider;
use Stolt\HydeLlmsTxt\BuildTasks\GenerateLlmsTxtTask;
use Hyde\Framework\Services\BuildTaskService;
use Mockery;

/**
 * Integration tests for LlmsTxtServiceProvider.
 *
 * Verifies that the service provider registers the build task when enabled
 * and skips registration when disabled.
 */
class LlmsTxtServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__hyde_llms_txt_config']->exchangeArray([]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_provider_registers_task_when_enabled(): void
    {
        // Set enabled = true
        $GLOBALS['__hyde_llms_txt_config']['hyde-llms-txt'] = ['enabled' => true];

        // Mock BuildTaskService
        $mockService = Mockery::mock(BuildTaskService::class);
        $mockService->shouldReceive('registerTask')
            ->with(GenerateLlmsTxtTask::class)
            ->once();

        // Create a minimal container with the mock
        $container = \Illuminate\Container\Container::getInstance();
        $container->instance(BuildTaskService::class, $mockService);

        $provider = new LlmsTxtServiceProvider($container);
        $provider->register();
        $provider->boot();

        // If we reach here without error, the task was registered
        $this->assertTrue(true);
    }

    public function test_provider_does_not_register_task_when_disabled(): void
    {
        // Set enabled = false
        $GLOBALS['__hyde_llms_txt_config']['hyde-llms-txt'] = ['enabled' => false];

        // Mock BuildTaskService - should NOT be called
        $mockService = Mockery::mock(BuildTaskService::class);
        $mockService->shouldNotReceive('registerTask');

        $container = \Illuminate\Container\Container::getInstance();
        $container->instance(BuildTaskService::class, $mockService);

        $provider = new LlmsTxtServiceProvider($container);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_provider_merges_config(): void
    {
        $GLOBALS['__hyde_llms_txt_config']->exchangeArray([]);

        $container = \Illuminate\Container\Container::getInstance();

        $provider = new LlmsTxtServiceProvider($container);
        $provider->register();

        // After mergeConfigFrom, the config key should exist
        $config = config('hyde-llms-txt');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
    }
}
