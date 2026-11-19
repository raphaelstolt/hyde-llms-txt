<?php

declare(strict_types=1);

namespace Stolt\HydeLlmsTxt\Content;

use Hyde\Hyde;
use Hyde\Pages\Concerns\HydePage;
use Hyde\Pages\DocumentationPage;
use Hyde\Pages\MarkdownPage;
use Hyde\Pages\MarkdownPost;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

/**
 * Maps HydePHP page collections to llms.txt entries.
 *
 * This class handles content discovery, filtering, metadata extraction,
 * and serialization — all independent of the build lifecycle so it can
 * be unit tested in isolation.
 */
class ContentMapper
{
    /**
     * Map all published pages to an llms.txt document.
     */
    public function map(): LlmsTxt
    {
        $config = $this->getConfig();
        $title = $this->resolveTitle($config);

        $document = new LlmsTxt();
        $document->title($title);

        $this->addDescription($document, $config);

        $sections = $this->collectSections($config);

        foreach ($sections as $sectionName => $links) {
            $document->addSection($this->buildSection($sectionName, $links));
        }

        return $document;
    }

    /**
     * Write the mapped document to the build output directory.
     */
    public function write(): void
    {
        $document = $this->map();
        $outputDirectory = Hyde::getOutputDirectory();
        $filePath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'llms.txt';

        $document->toFile($filePath);
    }

    /**
     * Check if there are any entries to generate.
     */
    public function isEmpty(): bool
    {
        $config = $this->getConfig();

        return ! $this->hasDocs($config)
            && ! $this->hasPosts($config)
            && ! $this->hasPages($config);
    }

    /**
     * Resolve the document title from config or HydePHP site name.
     */
    private function resolveTitle(array $config): string
    {
        $title = $config['title'] ?? null;

        if (is_string($title) && $title !== '') {
            return $title;
        }

        /** @var string */
        return config('hyde.name', default: 'Site Documentation');
    }

    /**
     * Add description to the document if configured.
     */
    private function addDescription(LlmsTxt $document, array $config): void
    {
        $description = $config['description'] ?? '';

        if (is_string($description) && $description !== '') {
            $document->description($description);
        }
    }

    /**
     * Collect all section name-to-links mappings.
     *
     * @return array<string, array<int, array{title: string, url: string, description: string}>>
     */
    private function collectSections(array $config): array
    {
        $sections = [];

        if ($this->shouldIncludeDocs($config)) {
            $docs = $this->mapPages(DocumentationPage::class);
            if ($docs !== []) {
                $sections['Documentation'] = $docs;
            }
        }

        if ($this->shouldIncludePosts($config)) {
            $posts = $this->mapPages(MarkdownPost::class);
            if ($posts !== []) {
                $sections['Blog Posts'] = $posts;
            }
        }

        if ($this->shouldIncludePages($config)) {
            $pages = $this->mapPages(MarkdownPage::class);
            if ($pages !== []) {
                $sections['Pages'] = $pages;
            }
        }

        return $sections;
    }

    /**
     * Build a Section from sorted and deduplicated links.
     *
     * @param array<int, array{title: string, url: string, description: string}> $links
     */
    private function buildSection(string $name, array $links): Section
    {
        $section = (new Section())->name($name);

        // Sort links by title for deterministic output
        usort($links, static fn (array $a, array $b) => strcmp($a['title'], $b['title']));

        // Deduplicate by URL
        /** @var array<int, string> $seenUrls */
        $seenUrls = [];
        foreach ($links as $linkData) {
            $url = $linkData['url'];

            if (in_array($url, $seenUrls, strict: true)) {
                continue;
            }
            $seenUrls[] = $url;

            $title = $linkData['title'];
            $description = $linkData['description'];

            $link = (new Link())
                ->urlTitle($title)
                ->url($url);

            if ($description !== '') {
                $link->urlDetails($description);
            }

            $section->addLink($link);
        }

        return $section;
    }

    /**
     * Check if docs should be included.
     */
    private function shouldIncludeDocs(array $config): bool
    {
        return ($config['include']['docs'] ?? true) === true
            && class_exists(DocumentationPage::class);
    }

    /**
     * Check if posts should be included.
     */
    private function shouldIncludePosts(array $config): bool
    {
        return ($config['include']['posts'] ?? true) === true
            && class_exists(MarkdownPost::class);
    }

    /**
     * Check if pages should be included.
     */
    private function shouldIncludePages(array $config): bool
    {
        return ($config['include']['pages'] ?? false) === true
            && class_exists(MarkdownPage::class);
    }

    /**
     * Check if there are visible docs.
     */
    private function hasDocs(array $config): bool
    {
        return $this->shouldIncludeDocs($config)
            && $this->countPages(DocumentationPage::class) > 0;
    }

    /**
     * Check if there are visible posts.
     */
    private function hasPosts(array $config): bool
    {
        return $this->shouldIncludePosts($config)
            && $this->countPages(MarkdownPost::class) > 0;
    }

    /**
     * Check if there are visible pages.
     */
    private function hasPages(array $config): bool
    {
        return $this->shouldIncludePages($config)
            && $this->countPages(MarkdownPage::class) > 0;
    }

    /**
     * Get the package configuration.
     */
    private function getConfig(): array
    {
        /** @var array{enabled: bool, title: string|null, description: string, include: array{docs: bool, posts: bool, pages: bool}, exclude: array<int, string>} */
        return config('hyde-llms-txt', default: [
            'enabled' => true,
            'title' => null,
            'description' => '',
            'include' => [
                'docs' => true,
                'posts' => true,
                'pages' => false,
            ],
            'exclude' => [],
        ]);
    }

    /**
     * Map pages of a given type to link entries.
     *
     * @param class-string<HydePage> $pageClass
     * @return array<int, array{title: string, url: string, description: string}>
     */
    private function mapPages(string $pageClass): array
    {
        $pages = $pageClass::all();
        $config = $this->getConfig();
        /** @var array<int, string> $excludedRoutes */
        $excludedRoutes = $config['exclude'] ?? [];
        $links = [];

        foreach ($pages as $page) {
            // Skip pages hidden from navigation
            if ($this->isHidden($page)) {
                continue;
            }

            // Skip excluded routes
            $routeKey = $page->getRouteKey();
            if (in_array($routeKey, $excludedRoutes, strict: true)) {
                continue;
            }

            // Get the link (public URL path)
            $url = $page->getLink();

            // Skip pages without valid URLs
            if ($url === '') {
                continue;
            }

            $title = $page->title ?? $page->getIdentifier();
            $description = $this->getDescription($page);

            $links[] = [
                'title' => $title,
                'url' => $url,
                'description' => $description ?? '',
            ];
        }

        return $links;
    }

    /**
     * Count visible pages of a given type.
     *
     * @param class-string<HydePage> $pageClass
     */
    private function countPages(string $pageClass): int
    {
        return count($this->mapPages($pageClass));
    }

    /**
     * Check if a page is hidden from navigation.
     */
    private function isHidden(HydePage $page): bool
    {
        // Check the navigation hidden flag in front matter
        /** @var bool $hidden */
        $hidden = $page->data('hidden', default: false);
        return $hidden === true;
    }

    /**
     * Get the description for a page, with fallbacks.
     *
     * Fallback order:
     * 1. `description` front matter field
     * 2. For MarkdownPost: the `description` property
     * 3. null (no description)
     */
    private function getDescription(HydePage $page): ?string
    {
        // Try description from front matter
        $description = $page->data('description');

        if ($description !== null && $description !== '') {
            return (string) $description;
        }

        // For MarkdownPost, try the description property
        if ($page instanceof MarkdownPost) {
            $postDesc = $page->description;
            if ($postDesc !== null && $postDesc !== '') {
                return $postDesc;
            }
        }

        return null;
    }
}
