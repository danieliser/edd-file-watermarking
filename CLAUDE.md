# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Important

- ALL instructions within this document MUST BE FOLLOWED, these are not optional unless explicitly stated.
- ASK FOR CLARIFICATION If you are uncertain of any of thing within the document.
- DO NOT edit more code than you have to.
- DO NOT WASTE TOKENS, be succinct and concise.

## Project Overview

This is the EDD File Watermarking plugin for WordPress, developed by Daniel Iser. It adds anti-piracy watermarking capabilities to Easy Digital Downloads (EDD) by modifying downloaded files with customer identification.

**Core Functionality**: When customers download files through EDD, the plugin intercepts the download, creates a temporary watermarked copy with customer-specific information (license keys, customer IDs), and serves that instead of the original file.

## Development Commands

### PHP Code Quality
```bash
# Install dependencies first
composer install

# Format code according to standards
composer run format

# Check code standards (linting)
composer run lint

# Run static analysis
composer run phpstan

# Run the real ZIP and download-path regression suite
composer run test
```

### Development Workflow
- Code must follow WordPress coding standards via the Code Atlantic ruleset
- PHPStan analysis at level 8 with WordPress extensions
- All inline comments must end with proper punctuation (., !, or ?)

## Architecture Overview

### Plugin Structure
```
edd-file-watermarking.php          # Main plugin file, hooks registration
inc/
├── functions.php                  # File loader, includes all modules
├── watermark.php                  # Core watermarking logic and zip processing
├── settings.php                   # EDD admin settings integration
├── post.php                       # Download-specific watermark meta boxes
├── globals.php                    # Global helper functions
└── cleanup.php                    # Cleanup scheduled tasks
```

### Core Architecture Patterns

**Hook-Based Architecture**: The plugin uses WordPress action/filter system extensively:
- `edd_requested_file` filter intercepts downloads
- `watermark_edd_download` action processes files
- `watermark_edd_download_{$filename}` for file-specific processing

**Watermarking Process Flow**:
1. **Detection**: `watermark_edd_download()` in `watermark.php` intercepts EDD file requests
2. **Validation**: Extracts customer/license data from request parameters
3. **File Processing**: Creates a temporary copy in a random, plugin-namespaced per-request directory
4. **Zip Manipulation**: Uses ZipArchive to modify files within zip archives
5. **Dynamic Base Path Detection**: Automatically detects zip structure (single root directory vs multiple files)
6. **Watermark Application**: Applies configured watermarks (add files, string replacement, content appending)
7. **Verification**: Rejects a customer archive that is unchanged or cannot be committed safely
8. **Cleanup**: Scheduled daily cleanup recursively removes only stale plugin-owned copies

**Watermark Types Supported**:
- `add_file`: Insert new files into zip archives
- `string_replacement`: Find/replace text within existing files
- `append_to_file`: Add content to end of existing files

**Settings Architecture**:
- **Global Settings**: Stored in EDD options as `edd_watermarks` array
- **Per-Download Settings**: Post meta `edd_watermark_settings` for download-specific watermarks
- **Dynamic Content Parsing**: Supports placeholders like `{license_key}`, `{customer_id}`, advanced syntax like `{customer_id times=3}`

### Key Integration Points

**EDD Integration**:
- Hooks into EDD's file delivery system via `edd_requested_file`
- Supports both standard EDD downloads and Software Licensing extension
- Handles different request methods: `eddfile` parameter and `license` parameter formats

**WordPress Integration**:
- Uses `wp_mkdir_p()` and `wp_delete_file()` around plugin-owned temporary paths
- Integrates with EDD settings pages via settings API
- Uses post meta boxes for download-specific configuration

### Security Considerations

**File Security**:
- Creates temporary files under `temp/edd-file-watermarking/<customer>/<random-request>/`
- Preserves the original ZIP and fails closed with a generic retryable error when personalization fails
- Never recursively cleans the shared legacy `temp/<customer>/` namespace
- Validates all input parameters and sanitizes data

**License Validation**:
- Verifies license keys through EDD Software Licensing when available
- Validates payment IDs and customer relationships before processing

## Important Notes

### Development Guidelines
- Never modify XXXX placeholders in any premium plugin files - these are anti-piracy watermarks
- Follow WordPress coding standards strictly (enforced via composer lint)
- All functions must be properly namespaced under `EDDFileWatermarking`
- Inline comments require proper punctuation endings

### File Processing Behavior
- The plugin automatically detects zip archive structure (single root directory vs flat structure)
- Static caching prevents multiple reads of the same zip file content during processing
- Base path detection ensures watermarks work correctly regardless of zip structure
- Temporary files are cleaned up daily via WordPress cron after a one-hour race-safety grace period

### Compatibility
- Requires ZipArchive PHP extension for zip file processing
- Compatible with EDD Software Licensing extension
- Missing dependencies or failed personalization stop eligible downloads by default; the documented `edd_file_watermarking_fail_open` filter is an emergency-only compatibility override

### Testing Approach
- Test with various zip structures (single root directory, multiple root items)
- Verify watermarks work with different EDD request methods
- Test cleanup functionality doesn't remove files prematurely
- Validate license key parsing and customer identification accuracy
