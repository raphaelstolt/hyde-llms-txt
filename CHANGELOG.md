# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed

- Normalize whitespace in link descriptions to prevent multiline excerpts from breaking `llms.txt` formatting.
- Omit post link descriptions when blog posts are the only eligible content, while retaining the site description.

## [0.9.0] - 2026-11-19

### Added

- Automatic generation of `llms.txt` after a HydePHP site build.
- Discovery and grouping of published documentation pages, blog posts, and standard pages.
- Configuration for the document title, description, included content types, and excluded routes.
- Exclusion of pages hidden from navigation.
- Automatic service provider discovery.