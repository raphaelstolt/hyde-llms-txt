<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\Tests\Integration;

// Bootstrap the test container and config() helper
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Hyde\Pages\DocumentationPage;
use Hyde\Pages\MarkdownPost;
use Hyde\Pages\MarkdownPage;
use Hyde\Foundation\Facades\Pages;
use Stolt\HydeLlmsTxt\BuildTasks\GenerateLlmsTxtTask;
use Hyde\Framework\Features\BuildTasks\BuildTaskSkippedException;
use Mockery;

/**
 * Integration tests for GenerateLlmsTxtTask.
 *
 * Verifies that the build task correctly delegates to ContentMapper
 * and handles the empty-content skip case.
 */
class GenerateLlmsTxtTaskTest extends TestCase
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

    public function test_task_skips_when_no_content(): void
    {
        // Set up config with no content
        $GLOBALS['__hyde_llms_txt_config']['hyde-llms-txt'] = [
            'enabled' => true,
            'title' => 'Empty Site',
            'description' => '',
            'include' => ['docs' => true, 'posts' => true, 'pages' => false],
            'exclude' => [],
        ];

        // Mock empty collections
        $mockPages = Mockery::mock('alias:' . Pages::class);
        $mockPages->shouldReceive('getPages')->with(DocumentationPage::class)
            ->andReturn(\Hyde\Foundation\Kernel\PageCollection::make([]));
        $mockPages->shouldReceive('getPages')->with(MarkdownPost::class)
            ->andReturn(\Hyde\Foundation\Kernel\PageCollection::make([]));
        $mockPages->shouldReceive('getPages')->with(MarkdownPage::class)
            ->andReturn(\Hyde\Foundation\Kernel\PageCollection::make([]));

        // The task should throw BuildTaskSkippedException when no content
        $task = new GenerateLlmsTxtTask();
        $this->expectException(BuildTaskSkippedException::class);
        $this->expectExceptionMessage('No content entries found to generate llms.txt');
        $task->handle();
    }
}
