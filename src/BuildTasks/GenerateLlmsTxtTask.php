<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\BuildTasks;

use Hyde\Framework\Features\BuildTasks\BuildTaskSkippedException;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;
use Stolt\HydeLlmsTxt\Content\ContentMapper;

/**
 * Post-build task that generates llms.txt from site content.
 *
 * Runs after the site is compiled and writes llms.txt to the build
 * output directory root, so it is served at the site's root URL.
 */
class GenerateLlmsTxtTask extends PostBuildTask
{
    public static string $message = 'Generating llms.txt';

    /**
     * @throws BuildTaskSkippedException
     */
    public function handle(): void
    {
        $mapper = new ContentMapper();

        // Skip if there are no entries to generate.
        if ($mapper->isEmpty()) {
            $this->skip('No content entries found to generate llms.txt');
            return;
        }

        $mapper->write();
        $this->info('llms.txt generated successfully.');
    }
}
