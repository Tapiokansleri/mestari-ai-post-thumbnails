# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.1] - 2026-06-29

### Fixed

- OpenAI `gpt-image-1` compatibility: removed unsupported `response_format` parameter
- Image API now handles both URL and base64 responses

## [1.0.0] - 2026-06-29

### Added

- OpenAI DALL·E 3 thumbnail generation from post titles
- Settings page with API key and extra prompt fields
- List of posts missing featured images with per-post generate button
- Automatic crop/resize to 1000×625 (400:250 ratio)
- GitHub-based plugin updater
- Manual **Check for updates** button on settings page

[1.0.1]: https://github.com/Tapiokansleri/mestari-ai-post-thumbnails/releases/tag/v1.0.1
[1.0.0]: https://github.com/Tapiokansleri/mestari-ai-post-thumbnails/releases/tag/v1.0.0
