# Changelog

## Unreleased

### Added

- Real `ZipArchive` regression tests for rooted packages, advanced customer/license placeholders, multiple mutations of one file, archive-cache isolation, and unsafe-path rejection.
- End-to-end download-filter tests proving source ZIP immutability, customer-copy mutation, and rollback after unavailable, open-failed, or close-failed ZIP operations.
- Fail-closed download tests for missing sources, thrown callbacks, ineffective/no-op and partially applied watermark sets, and an explicit emergency fail-open override.
- Concurrent same-customer download and age-aware recursive nested-cleanup regression tests.
- PHPUnit and working level-8 PHPStan configuration for the current product source.
- GitHub Actions coverage for PHP 7.4 through 8.5 plus Composer validation, coding standards, and static analysis.

### Changed

- Require PHP 7.4 explicitly and avoid PHP 8-only string helpers in the download path.
- Harden repeater sanitization against missing/misaligned fields, non-row values, unsupported watermark types, and pre-remapped payloads that previously bypassed sanitization.
- Use WordPress file deletion during scheduled temporary-file cleanup.
- Treat customer ZIP creation as a fail-closed transaction: failures delete the temporary copy and stop delivery with a retryable HTTP 503 response.
- Create each customer copy in a collision-resistant, plugin-namespaced per-request directory while preserving the original ZIP basename.
- Give fresh customer copies a one-hour cleanup grace period so the scheduled event cannot race an active download.
- Restrict scheduled cleanup to the plugin-owned namespace; legacy shared `temp/<customer>/` data is left for deliberate migration.
- Require eligible archives to differ from their source by default, with an explicit filter for intentional no-op integrations.
- Treat every configured `watermark_zip()` operation as required so one successful rule cannot mask a later failed rule.
- Align developer guidance and WordPress readme metadata with the hardened runtime and GPL-2.0-or-later package license.
- Archive the abandoned settings-driven PHP callback proposal; reviewed filename-specific hooks already provide safer code-owned extensibility.

### Fixed

- Read the correct regular-expression capture for advanced placeholders and support quoted or unquoted `times`/`encoded` values.
- Isolate modified-file caches per ZIP so one archive/customer cannot reuse another archive's contents.
- Reject empty, absolute, traversal, and null-byte target paths and prevent empty-search mutations.
- Correct the PHP 8 deprecation caused by an optional parameter preceding a required parameter.
- Read Popup Maker's packaged version dynamically in the store-owned self-hosted-updater configuration instead of pinning a stale release number.
- Detect single-root ZIP packages even when the archive omits an explicit top-level directory entry.

## [1.2.0] - 2025-05-05

- Improvement: Catch more line break formats.
- Improvement: Add proper composer `wordpress-plugin` type for composer based installation.
- Improvement: Checks to ensure many EDD functions exist before usage.
- Improvement: More robust upload directory determination with a fallback to WordPress upload directory.
- Improvement: Better logic for watermark application by using full paths in zip archives.
- Improvement: Added better detection for both plugins with nested `plugin-slug` folder and without.
- Developer: Code quality improvements with PHPCS.

## [1.1.0] - 2024-08-05

### Fix

- Error hen saving settings.
- Issues when multiple changes to the same file.

## [1.0.0] - 2024-02-04

### Added

- Initial release
