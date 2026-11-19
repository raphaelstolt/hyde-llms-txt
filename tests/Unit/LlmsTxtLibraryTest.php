<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

/**
 * Smoke test to verify the llms-txt-php library is loadable and basic API works.
 */
class LlmsTxtLibraryTest extends TestCase
{
    public function test_can_create_document(): void
    {
        $document = new LlmsTxt();
        $document->title('Test Site')
            ->description('A test site.');

        $section = (new Section())
            ->name('Documentation')
            ->addLink(
                (new Link())
                    ->urlTitle('Getting Started')
                    ->url('/docs/getting-started')
                    ->urlDetails('Introduction to the project')
            );

        $document->addSection($section);

        $output = $document->toString();

        $this->assertStringContainsString('# Test Site', $output);
        $this->assertStringContainsString('A test site.', $output);
        $this->assertStringContainsString('## Documentation', $output);
        $this->assertStringContainsString('[Getting Started](/docs/getting-started)', $output);
    }

    public function test_document_serialization_format(): void
    {
        $document = (new LlmsTxt())
            ->title('My Project')
            ->description('Short description.');

        $document->addSection(
            (new Section())
                ->name('Links')
                ->addLink(
                    (new Link())
                        ->urlTitle('Home')
                        ->url('/')
                )
        );

        $output = $document->toString();

        // Verify the output follows llms.txt Markdown structure
        $this->assertMatchesRegularExpression('/^# My Project\s*\n> Short description\./m', $output);
    }
}
