<?php

declare(strict_types=1);

/**
 * Bootstrap for integration tests.
 * Sets up the config() helper and wires facades to a minimal container.
 */

use Illuminate\Container\Container;

require_once __DIR__ . '/../../vendor/autoload.php';

// Create a minimal container
$container = new Container();
$container->setInstance($container);

// Simple config store
$configStore = new class extends \ArrayObject {
    public function get(?string $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->getArrayCopy();
        }
        $parts = explode('.', $key);
        $value = $this->getArrayCopy();
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public function set(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            // Merge array config
            $current = $this->getArrayCopy();
            foreach ($key as $k => $v) {
                $current[$k] = $v;
            }
            $this->exchangeArray($current);
        } else {
            // Set dot-notation key
            $parts = explode('.', $key);
            $current = $this->getArrayCopy();
            $ref = &$current;
            $last = array_pop($parts);
            foreach ($parts as $part) {
                if (!isset($ref[$part]) || !is_array($ref[$part])) {
                    $ref[$part] = [];
                }
                $ref = &$ref[$part];
            }
            $ref[$last] = $value;
            $this->exchangeArray($current);
        }
    }
};
$container->singleton('config', static fn () => $configStore);

// Wire facades to our container
\Illuminate\Support\Facades\Facade::setFacadeApplication($container);

// Set up a mock HydeKernel so the Pages facade can resolve.
// HydeKernel::getInstance() returns a static singleton. We set it via reflection.
$mockKernel = Mockery::mock(\Hyde\Foundation\HydeKernel::class)->makePartial();
$mockKernel->shouldReceive('pages')->andReturn(new class implements \IteratorAggregate, \Countable {
    public function getIterator(): \Traversable { return new \ArrayIterator([]); }
    public function count(): int { return 0; }
});

// Set the static $instance property
$ref = new \ReflectionProperty(\Hyde\Foundation\HydeKernel::class, 'instance');
$ref->setValue(null, $mockKernel);

// Define config() helper
if (!function_exists('Stolt\HydeLlmsTxt\Tests\Integration\config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        /** @var \ArrayObject $repo */
        $repo = Container::getInstance()->make('config');
        return $repo->get($key, $default);
    }
}

// Expose config store for tests
$GLOBALS['__hyde_llms_txt_config'] = $configStore;
