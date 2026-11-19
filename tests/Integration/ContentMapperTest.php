<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\Tests\Integration;

// Bootstrap the test container and config() helper
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Hyde\Pages\DocumentationPage;
use Hyde\Pages\MarkdownPost;
use Hyde\Pages\MarkdownPage;
use Hyde\Hyde;
use Hyde\Foundation\Facades\Pages;
use Stolt\HydeLlmsTxt\Content\ContentMapper;
use Stolt\LlmsTxt\LlmsTxt;
use Mockery;

/**
 * Integration tests for ContentMapper.
 *
 * Uses test double subclasses of HydePHP page classes and Mockery for facades.
 */
class ContentMapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset config store for each test
        $GLOBALS['__hyde_llms_txt_config']->exchangeArray([]);
        self::$pageCollections = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        self::$pageCollections = [];
    }

    // ---- Test double page classes ----

    /**
     * Test double that extends DocumentationPage.
     */
    private function createDocPage(string $title, string $link, string $routeKey, ?string $description = null, bool $hidden = false): DocumentationPage
    {
        $page = new class($title, $link, $routeKey, $description, $hidden) extends DocumentationPage {
            public function __construct(
                private string $testTitle,
                private string $testLink,
                private string $testRouteKey,
                private ?string $testDescription,
                private bool $testHidden
            ) {
                // Skip parent constructor to avoid Config facade access
            }

            public function getLink(): string { return $this->testLink; }
            public function getRouteKey(): string { return $this->testRouteKey; }

            public function data(?string $key = null, mixed $default = null): mixed
            {
                if ($key === 'hidden') return $this->testHidden;
                if ($key === 'description') return $this->testDescription;
                return $default;
            }
        };

        // Set readonly properties via reflection
        $this->setPageProperties($page, $title, $routeKey);
        return $page;
    }

    /**
     * Test double that extends MarkdownPost.
     */
    private function createPost(string $title, string $link, string $routeKey, ?string $description = null, bool $hidden = false): MarkdownPost
    {
        $page = new class($title, $link, $routeKey, $description, $hidden) extends MarkdownPost {
            public function __construct(
                private string $testTitle,
                private string $testLink,
                private string $testRouteKey,
                public ?string $testDescription,
                private bool $testHidden
            ) {
                // Skip parent constructor
            }

            public function getLink(): string { return $this->testLink; }
            public function getRouteKey(): string { return $this->testRouteKey; }

            public function data(?string $key = null, mixed $default = null): mixed
            {
                if ($key === 'hidden') return $this->testHidden;
                if ($key === 'description') return $this->testDescription;
                return $default;
            }
        };

        // Set readonly properties via reflection
        $this->setPageProperties($page, $title, $routeKey);
        return $page;
    }

    /**
     * Test double that extends MarkdownPage.
     */
    private function createPage(string $title, string $link, string $routeKey, ?string $description = null, bool $hidden = false): MarkdownPage
    {
        $page = new class($title, $link, $routeKey, $description, $hidden) extends MarkdownPage {
            public function __construct(
                private string $testTitle,
                private string $testLink,
                private string $testRouteKey,
                private ?string $testDescription,
                private bool $testHidden
            ) {
                // Skip parent constructor
            }

            public function getLink(): string { return $this->testLink; }
            public function getRouteKey(): string { return $this->testRouteKey; }

            public function data(?string $key = null, mixed $default = null): mixed
            {
                if ($key === 'hidden') return $this->testHidden;
                if ($key === 'description') return $this->testDescription;
                return $default;
            }
        };

        // Set readonly properties via reflection
        $this->setPageProperties($page, $title, $routeKey);
        return $page;
    }

    /**
     * Set readonly properties on a page test double via reflection.
     */
    private function setPageProperties(object $page, string $title, string $identifier): void
    {
        $reflection = new \ReflectionClass(\Hyde\Pages\Concerns\HydePage::class);

        $titleProp = $reflection->getProperty('title');
        $titleProp->setValue($page, $title);

        $identifierProp = $reflection->getProperty('identifier');
        $identifierProp->setValue($page, $identifier);
    }

    // ---- Setup helpers ----

    /** @param array<string, mixed> $cfg */
    private function setupFacades(
        array $cfg,
        array $docs = [],
        array $posts = [],
        array $pages = [],
        ?string $outputDir = null,
        ?string $hydeName = null
    ): void {
        $GLOBALS['__hyde_llms_txt_config']['hyde-llms-txt'] = $cfg;
        if ($hydeName !== null) {
            // Set nested config: hyde.name
            $current = $GLOBALS['__hyde_llms_txt_config']->getArrayCopy();
            $current['hyde'] = array_merge($current['hyde'] ?? [], ['name' => $hydeName]);
            $GLOBALS['__hyde_llms_txt_config']->exchangeArray($current);
        }

        $this->mockPagesFacade(DocumentationPage::class, $docs);
        $this->mockPagesFacade(MarkdownPost::class, $posts);
        $this->mockPagesFacade(MarkdownPage::class, $pages);
        $this->mockPagesFacadeFinal();

        if ($outputDir !== null) {
            $mock = Mockery::mock('alias:' . Hyde::class);
            $mock->shouldReceive('getOutputDirectory')->andReturn($outputDir);
        }
    }

    private static array $pageCollections = [];

    private function mockPagesFacade(string $pageClass, array $pages): void
    {
        // Store collections by class; a single alias mock will be created in setupFacades
        self::$pageCollections[$pageClass] = \Hyde\Foundation\Kernel\PageCollection::make($pages);
    }

    private function mockPagesFacadeFinal(): void
    {
        // Create a single alias mock for all page types
        $mock = Mockery::mock('alias:' . Pages::class);
        foreach (self::$pageCollections as $pageClass => $collection) {
            $mock->shouldReceive('getPages')->with($pageClass)->andReturn($collection);
        }
    }

    // ---- Tests: Basic mapping ----

    public function test_map_produces_document_with_title_and_description(): void
    {
        $docPage = $this->createDocPage('Getting Started', '/docs/getting-started', 'docs/getting-started', 'Learn the basics');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => 'A test site.', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$docPage]
        );

        $mapper = new ContentMapper();
        $document = $mapper->map();

        $this->assertInstanceOf(LlmsTxt::class, $document);
        $output = $document->toString();

        $this->assertStringContainsString('# Test Site', $output);
        $this->assertStringContainsString('A test site.', $output);
        $this->assertStringContainsString('## Documentation', $output);
        $this->assertStringContainsString('[Getting Started](/docs/getting-started)', $output);
        $this->assertStringContainsString('Learn the basics', $output);
    }

    public function test_map_uses_hyde_name_as_title_fallback(): void
    {
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => null, 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            hydeName: 'My Hyde Site'
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('# My Hyde Site', $output);
    }

    public function test_map_includes_blog_posts(): void
    {
        $post = $this->createPost('First Post', '/posts/first-post', 'posts/first-post', 'My first blog post');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            posts: [$post]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('## Blog Posts', $output);
        $this->assertStringContainsString('[First Post](/posts/first-post)', $output);
        $this->assertStringContainsString('My first blog post', $output);
    }

    public function test_map_includes_standard_pages_when_enabled(): void
    {
        $page = $this->createPage('About', '/about', 'about', 'About this project');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => true], 'exclude' => []],
            pages: [$page]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('## Pages', $output);
        $this->assertStringContainsString('[About](/about)', $output);
    }

    // ---- Tests: Filtering ----

    public function test_map_excludes_hidden_pages(): void
    {
        $visible = $this->createDocPage('Visible Page', '/docs/visible', 'docs/visible', 'Visible');
        $hidden  = $this->createDocPage('Hidden Page', '/docs/hidden', 'docs/hidden', 'Hidden', hidden: true);

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$visible, $hidden]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('[Visible Page](/docs/visible)', $output);
        $this->assertStringNotContainsString('Hidden Page', $output);
    }

    public function test_map_excludes_routes_in_exclude_list(): void
    {
        $page1 = $this->createDocPage('Public Doc', '/docs/public', 'docs/public');
        $page2 = $this->createDocPage('Internal Doc', '/docs/internal', 'docs/internal');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => ['docs/internal']],
            docs: [$page1, $page2]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('[Public Doc](/docs/public)', $output);
        $this->assertStringNotContainsString('Internal Doc', $output);
    }

    public function test_map_skips_docs_when_disabled(): void
    {
        $docPage = $this->createDocPage('Should Not Appear', '/docs/should-not-appear', 'docs/should-not-appear');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => false, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$docPage]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringNotContainsString('Documentation', $output);
        $this->assertStringNotContainsString('Should Not Appear', $output);
    }

    // ---- Tests: Deduplication and sorting ----

    public function test_map_deduplicates_urls(): void
    {
        $page1 = $this->createDocPage('First Entry', '/docs/duplicate', 'docs/duplicate-1');
        $page2 = $this->createDocPage('Second Entry', '/docs/duplicate', 'docs/duplicate-2');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$page1, $page2]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertEquals(1, substr_count($output, '/docs/duplicate'), 'Duplicate URL should appear only once');
    }

    public function test_map_sorts_links_by_title(): void
    {
        $zPage = $this->createDocPage('Zebra', '/docs/zebra', 'docs/zebra');
        $aPage = $this->createDocPage('Apple', '/docs/apple', 'docs/apple');
        $mPage = $this->createDocPage('Mango', '/docs/mango', 'docs/mango');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$zPage, $aPage, $mPage]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertLessThan(strpos($output, 'Mango'), strpos($output, 'Apple'), 'Apple before Mango');
        $this->assertLessThan(strpos($output, 'Zebra'), strpos($output, 'Mango'), 'Mango before Zebra');
    }

    // ---- Tests: isEmpty ----

    public function test_isempty_returns_true_for_empty_site(): void
    {
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []]
        );
        $this->assertTrue((new ContentMapper())->isEmpty());
    }

    public function test_isempty_returns_false_when_content_exists(): void
    {
        $docPage = $this->createDocPage('Some Page', '/docs/some-page', 'docs/some-page');
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$docPage]
        );
        $this->assertFalse((new ContentMapper())->isEmpty());
    }

    // ---- Tests: Write ----

    public function test_write_creates_llms_txt_file(): void
    {
        $tempDir = sys_get_temp_dir() . '/hyde-llms-txt-test-' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $docPage = $this->createDocPage('Getting Started', '/docs/getting-started', 'docs/getting-started', 'Introduction');
            $this->setupFacades(
                cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => 'Test description.', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
                docs: [$docPage],
                outputDir: $tempDir
            );

            (new ContentMapper())->write();
            $content = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'llms.txt');

            $this->assertStringContainsString('# Test Site', $content);
            $this->assertStringContainsString('Test description.', $content);
            $this->assertStringContainsString('[Getting Started](/docs/getting-started)', $content);
        } finally {
            @unlink($tempDir . DIRECTORY_SEPARATOR . 'llms.txt');
            @rmdir($tempDir);
        }
    }

    public function test_write_is_idempotent(): void
    {
        $tempDir = sys_get_temp_dir() . '/hyde-llms-txt-test-' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $docPage = $this->createDocPage('Guide', '/docs/guide', 'docs/guide');
            $this->setupFacades(
                cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
                docs: [$docPage],
                outputDir: $tempDir
            );

            $mapper = new ContentMapper();
            $mapper->write();
            $first  = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'llms.txt');
            $mapper->write();
            $second = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'llms.txt');

            $this->assertEquals($first, $second, 'Repeated writes should produce identical output');
        } finally {
            @unlink($tempDir . DIRECTORY_SEPARATOR . 'llms.txt');
            @rmdir($tempDir);
        }
    }

    // ---- Tests: Edge cases ----

    public function test_map_handles_missing_description(): void
    {
        $docPage = $this->createDocPage('No Description', '/docs/no-desc', 'docs/no-desc');
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$docPage]
        );
        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('[No Description](/docs/no-desc)', $output);
    }

    public function test_map_handles_special_characters_in_title(): void
    {
        $docPage = $this->createDocPage('API & CLI "Reference"', '/docs/api-reference', 'docs/api-reference', 'Special chars: < > &');
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$docPage]
        );
        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('API & CLI "Reference"', $output);
    }

    public function test_map_skips_pages_without_valid_urls(): void
    {
        $noUrl = $this->createDocPage('No URL', '', 'docs/no-url');
        $valid = $this->createDocPage('Valid Page', '/docs/valid', 'docs/valid');
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            docs: [$noUrl, $valid]
        );
        $output = (new ContentMapper())->map()->toString();
        $this->assertStringNotContainsString('No URL', $output);
        $this->assertStringContainsString('[Valid Page](/docs/valid)', $output);
    }

    public function test_map_uses_post_description_property_as_fallback(): void
    {
        $post = $this->createPost('Post Title', '/posts/post-title', 'posts/post-title', 'Fallback description from property');
        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Test Site', 'description' => '', 'include' => ['docs' => true, 'posts' => true, 'pages' => false], 'exclude' => []],
            posts: [$post]
        );
        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('Fallback description from property', $output);
    }

    public function test_map_generates_valid_llms_txt_structure(): void
    {
        $doc  = $this->createDocPage('API Reference', '/docs/api', 'docs/api', 'API docs');
        $post = $this->createPost('Release Notes', '/posts/release', 'posts/release', 'Latest release');
        $page = $this->createPage('About', '/about', 'about', 'About us');

        $this->setupFacades(
            cfg: ['enabled' => true, 'title' => 'Multi Section Site', 'description' => 'Has docs, posts, and pages.', 'include' => ['docs' => true, 'posts' => true, 'pages' => true], 'exclude' => []],
            docs: [$doc],
            posts: [$post],
            pages: [$page]
        );

        $output = (new ContentMapper())->map()->toString();
        $this->assertStringContainsString('# Multi Section Site', $output);
        $this->assertStringContainsString('## Documentation', $output);
        $this->assertStringContainsString('## Blog Posts', $output);
        $this->assertStringContainsString('## Pages', $output);
    }
}
