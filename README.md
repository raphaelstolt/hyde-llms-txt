# hyde-llms-txt

Generate an `llms.txt` file from your HydePHP site's content during a static-site build.

## Why?

An `llms.txt` file provides AI agents and LLMs with a structured overview of a website's content in Markdown. Keeping
that overview up to date by hand can be tedious as your documentation and blog grow.

`hyde-llms-txt` discovers your HydePHP site's documentation pages, blog posts, and optionally standard pages, then
generates the file during each build using [stolt/llms-txt-php](https://github.com/raphaelstolt/llms-txt-php).

> [!NOTE]
> There is no evidence that search engines or chat assistants use `llms.txt` for ranking or citations. Treat it
> as  context for AI coding agents, not as an SEO tool.

## Requirements

- HydePHP 2.0+

## Installation

```bash
composer require stolt/hyde-llms-txt
```

The service provider is auto-discovered. No additional setup is required.

## Usage

Build your HydePHP site as usual. The package generates `llms.txt` in the build output directory root when there is
eligible content. Documentation pages and blog posts are included by default; standard pages can be enabled in the
configuration.

## Configuration

To customise the output, create `config/hyde-llms-txt.php` in your HydePHP project:

```php
<?php

return [
    'enabled' => true,
    'title' => 'My Website',
    'description' => 'Documentation and articles.',
    'include' => [
        'docs' => true,
        'posts' => true,
        'pages' => false,
    ],
    'exclude' => [],
];
```

### Configuration options

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Enable/disable automatic generation |
| `title` | `null` | Document title (falls back to site name) |
| `description` | `''` | Short description below the title |
| `include.docs` | `true` | Include documentation pages |
| `include.posts` | `true` | Include blog posts |
| `include.pages` | `false` | Include standard pages |
| `exclude` | `[]` | Array of route keys to exclude, e.g. `docs/installation` |

## How it works

After the site is compiled, a post-build task discovers the enabled content types and groups their links into sections.
Pages with `hidden: true` front matter, excluded route keys, and pages without a URL are omitted. Links are sorted by
title and deduplicated by URL within each section. If no eligible content is found, the task skips generation.
When blog posts are the only eligible content, their link descriptions are omitted; the configured site description
is retained.

## Example output

```markdown
# My Website

> Documentation and articles.

## Documentation

- [Getting Started](/docs/getting-started): Introduction to the project
- [Installation](/docs/installation): How to install and configure

## Blog Posts

- [Release Notes](/posts/release-notes): Latest changes and updates
```

## Serving llms.txt

After building the site, deploy the entire output directory as your web server's document root (or configure your
static hosting provider to publish it). HydePHP defaults to `_site`, so the generated file is `_site/llms.txt` and
will be available at `https://example.com/llms.txt`. If you change HydePHP's `output_directory`, publish that directory
instead. No application route is needed; the web server serves the file as a static asset.

To preview the built site locally, run this from your HydePHP project directory:

```bash
php hyde build
php -S localhost:8000 -t _site
```

Visit `http://localhost:8000/llms.txt` or check the deployed URL with `curl -i https://example.com/llms.txt`.
Ensure your host includes `.txt` files and does not rewrite the request to another page. Generation must be enabled
and there must be eligible content for the file to be created.

## Related projects

- [HydePHP](https://hydephp.com) — The static-site generator this extension integrates with.
- [llms-txt-php](https://github.com/raphaelstolt/llms-txt-php) — The library used to generate `llms.txt`.

## AI disclaimer

This extension is developed with the assistance of Artificial Intelligence (AI). AI tools are used throughout the
development process, including code generation, documentation, testing, and maintenance.

While reasonable care is taken to review and validate AI-generated contributions, AI-generated code is not inherently
correct, secure, or free of defects. All contributions remain subject to human review and project quality standards.

As with any software, use this package at your own risk. No guarantee is made regarding its fitness for a particular
purpose, and the maintainers assume no liability for damages resulting from its use.

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](CONTRIBUTING.md)
for more advice.

## License

This extension is licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).
