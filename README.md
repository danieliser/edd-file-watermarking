# Easy Digital Downloads - File Watermarking

This plugin applies text-based modifications to ZIP files served by Easy Digital Downloads. It can insert or replace content with a license key, customer ID, payment ID, download ID, or custom text while leaving the store's source ZIP untouched.

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer with the ZIP extension
- Easy Digital Downloads

Each eligible request is processed under `temp/edd-file-watermarking/<customer>/<request>/` in a random per-request directory. Concurrent requests cannot overwrite one another, while the original ZIP basename is preserved for delivery. Scheduled cleanup only owns this plugin-specific namespace and recursively removes stale copies after a one-hour grace period so it cannot race a newly prepared download; the interval is filterable with `edd_file_watermarking_cleanup_minimum_age`. Legacy `temp/<customer>/` leftovers are intentionally not swept automatically because other extensions may own that shared location.

Watermarking is fail-closed: missing ZIP support, unreadable sources, invalid customer/license context, callback failures, failed individual watermark rules, ZIP failures, and unchanged archives remove the temporary copy and stop the download with a generic HTTP 503 response. Operators can temporarily restore the legacy behavior during an outage with the `edd_file_watermarking_fail_open` filter, but doing so serves the unwatermarked source ZIP. Intentional no-op integrations can opt out of the unchanged-archive check with `edd_file_watermarking_require_changed_archive`.

## Features

- Watermarking for EDD files
- Search & Replace for EDD files
- License Key insertion for EDD files
- Customer ID insertion for EDD files
- Custom text insertion for EDD files
- Encoded license-key insertion for identifiers that should not expose the raw key

## Development

Run `composer test`, `composer lint`, and `composer phpstan`. CI exercises the real ZIP tests on PHP 7.4 through 8.5.
