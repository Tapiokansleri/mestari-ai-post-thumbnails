# Mestari AI Post Thumbnails

WordPress plugin that generates blog featured images from post titles using the [OpenAI Images API](https://platform.openai.com/docs/guides/images) (DALL·E 3).

Repository: [github.com/Tapiokansleri/mestari-ai-post-thumbnails](https://github.com/Tapiokansleri/mestari-ai-post-thumbnails)

## Features

- Generate **1000×625 px** featured images (400:250 ratio) from a post title using OpenAI **gpt-image-1**
- Settings page for your **OpenAI API key**
- **Extra prompt** field for brand/style instructions on every image
- Lists published posts **missing a featured image** with one-click generation
- Images are downloaded to the **Media Library** and set as the **post thumbnail**
- **Automatic updates** from GitHub releases
- **Check for updates** button on the settings page

## Requirements

- WordPress 6.0+
- PHP 7.4+
- OpenAI API key with billing enabled
- GD or Imagick for image cropping

## Installation

### From GitHub release (recommended)

1. Download `mestari-ai-post-thumbnails.zip` from the [latest release](https://github.com/Tapiokansleri/mestari-ai-post-thumbnails/releases/latest)
2. In WordPress go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate **Mestari AI Post Thumbnails**

### Manual install

Clone or copy this repository into `wp-content/plugins/mestari-ai-post-thumbnails/` and activate the plugin.

## Usage

1. Go to **Settings → AI Thumbnails**
2. Enter your OpenAI API key and save
3. Optionally add extra prompt instructions (colours, style, industry, etc.)
4. Click **Generate thumbnail** next to any post missing a featured image

Generation usually takes 15–60 seconds per image. Each request uses the OpenAI Images API and is billed by OpenAI.

## Updates

The plugin checks [GitHub Releases](https://github.com/Tapiokansleri/mestari-ai-post-thumbnails/releases) for new versions. When a newer release exists, it appears under **Dashboard → Updates** like any other plugin.

You can also click **Check for updates** on the settings page to force a check.

## Development

### Releasing a new version

1. Bump `Version` in `mestari-ai-post-thumbnails.php` and `MAPT_VERSION`
2. Update `CHANGELOG.md`
3. Commit and push to `main`
4. Create and push a tag:

```bash
git tag v1.0.1
git push origin v1.0.1
```

GitHub Actions builds `mestari-ai-post-thumbnails.zip` and attaches it to the release.

## License

GPL v2 or later. See [LICENSE](LICENSE).

## Author

[Tapio Kansleri](https://github.com/Tapiokansleri)
