=== Easy Digital Downloads - File Watermarking ===
Contributors: codeatlantic, danieliser
Plugin URI: https://github.com/danieliser/edd-file-watermarking
Author URI: https://danieliser.com/?utm_campaign=plugin-info&utm_source=readme-header&utm_medium=plugin-ui&utm_content=author-uri
Donate link: https://code-atlantic.com/donate/?utm_campaign=donations&utm_source=readme-header&utm_medium=plugin-ui&utm_content=donate-link
Tags: easy digital downloads, watermark, software licensing, digital products, piracy prevention
Requires at least: 6.5
Tested up to: 6.8
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html


== Description ==

Easy Digital Downloads - File Watermarking allows you to apply a watermark to your downloadable files. This can help prevent unauthorized distribution of your files.

== Changelog ==

= Unreleased =

- Fix: Fail closed with a retryable error instead of serving the store's unwatermarked source ZIP after personalization failures.
- Fix: Isolate concurrent downloads in random per-request directories and recursively clean nested customer copies.
- Fix: Detect rooted plugin ZIPs that omit an explicit top-level directory entry.
- Fix: Correct advanced placeholder parsing and isolate modified-content caches per archive.
- Developer: Add real ZIP download-path tests and PHP 7.4 through 8.5 CI coverage.

= 1.2.0 - 2025-05-05 =

- Improvement: Catch more line break formats.
- Improvement: Add proper composer `wordpress-plugin` type for composer based installation.
- Improvement: Checks to ensure many EDD functions exist before usage.
- Improvement: More robust upload directory determination with a fallback to WordPress upload directory.
- Improvement: Better logic for watermark application by using full paths in zip archives.
- Improvement: Added better detection for both plugins with nested `plugin-slug` folder and without.
- Developer: Code quality improvements with PHPCS.


= 1.1.0 - 2024-08-05 =

- Fix: Error hen saving settings.
- Fix: Issues when multiple changes to the same file.

= v1.0.0 =

- Initial Release
