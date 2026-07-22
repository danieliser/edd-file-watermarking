---
title: PHP Callback Watermark Method Specification
status: archived-rejected
archived_at: 2026-07-22
reason: Existing filename-specific action hooks provide product-specific extensibility without exposing an administrator-configurable arbitrary PHP callback surface.
---

# PHP Callback Watermark Method Specification

> Archived design evidence only. Do not implement this settings-driven callback type. Its unique requirement—custom per-artifact transformations—is already available through the existing `watermark_edd_download_{filename}` action with code-reviewed callbacks.

## Overview

This specification outlines a new watermark method `php_callback` that allows users to execute custom PHP functions during the watermarking process. This method provides maximum flexibility by enabling developers to implement custom logic using all available context data.

## Watermark Type: `php_callback`

### Purpose
Enable execution of user-defined PHP functions with full access to download context (customer, license, file, payment data) for custom watermarking logic.

### Security Model
- **Function Validation**: Only callable functions/methods allowed
- **Namespace Restrictions**: User-defined functions must be in allowed namespaces
- **Execution Context**: Functions execute with same permissions as watermarking process
- **Error Handling**: Graceful fallback on callback failures

## Configuration Schema

### Settings Structure
```php
[
    'type' => 'php_callback',        // Watermark type
    'file' => '',                    // Unused for callbacks (kept for consistency)
    'search' => 'my_function',       // Repurpose: Function name to execute
    'content' => 'key1=value1,key2=value2', // Repurpose: Arguments as comma-separated pairs
]
```

### Field Definitions

#### `type` (string, required)
- Set to `'php_callback'` to use callback functionality

#### `file` (string, optional)
- Not used for callbacks but kept for UI consistency
- Can be left empty or used for documentation purposes

#### `search` (string, required) - **Repurposed as Callback Function**
- PHP callable function/method name
- Examples:
  - `'my_watermark_function'` - Global function
  - `'MyClass::static_method'` - Static class method
  - `'MyNamespace\\my_function'` - Namespaced function

#### `content` (string, optional) - **Repurposed as Arguments**
- Additional custom arguments as comma-separated key=value pairs
- Examples: `template=premium,format=json` or `multiplier=3,prefix=VIP`
- Parsed into associative array and passed to callback
- Supports basic string values (no nested structures)

## Callback Function Signature

### Standard Signature
```php
function callback_function( $context, $zip, $custom_args = [] ) {
    // Implementation
    return $result; // Based on return_mode
}
```

### Context Data Structure
```php
$context = [
    // Customer Information
    'customer_id'    => int,           // EDD customer ID
    'customer_email' => string,        // Customer email address
    'customer_name'  => string,        // Customer display name

    // License Information
    'license_key'    => string,        // Software license key
    'license_id'     => int,           // License database ID
    'license_status' => string,        // active, inactive, expired, etc.
    'license_expires' => string|null,  // License expiration date

    // Purchase Information
    'payment_id'     => int,           // EDD payment/order ID
    'purchase_date'  => string,        // Payment completion date
    'purchase_total' => float,         // Payment total amount

    // Download Information
    'download_id'    => int,           // EDD download post ID
    'download_name'  => string,        // Download title
    'file_key'       => string,        // Specific file identifier

    // File Context
    'requested_file' => string,        // Original file path
    'target_file'    => string,        // Target file in watermark config
    'base_path'      => string,        // Detected ZIP base path
];
```

### Args Parsing Implementation
```php
/**
 * Parse comma-separated key=value pairs from watermark content field
 *
 * @param string $content_string User input like "template=premium,format=json"
 * @return array Associative array of parsed arguments
 */
function parse_callback_args( $content_string ) {
    $args = [];

    if ( empty( $content_string ) ) {
        return $args;
    }

    // Split by comma, then by equals sign
    $pairs = explode( ',', $content_string );

    foreach ( $pairs as $pair ) {
        $pair = trim( $pair );
        if ( strpos( $pair, '=' ) !== false ) {
            list( $key, $value ) = explode( '=', $pair, 2 );
            $args[ trim( $key ) ] = trim( $value );
        }
    }

    return $args;
}
```

## Implementation Examples

### Content Return Mode
```php
/**
 * Generate custom license file content
 */
function generate_license_file( $context, $zip, $args = [] ) {
    $template = $args['template'] ?? 'default';

    $content = "<?php\n";
    $content .= "// Generated on " . date('Y-m-d H:i:s') . "\n";
    $content .= "return [\n";
    $content .= "    'license_key' => '" . esc_attr($context['license_key']) . "',\n";
    $content .= "    'customer_id' => " . intval($context['customer_id']) . ",\n";
    $content .= "    'expires' => '" . esc_attr($context['license_expires']) . "',\n";
    $content .= "    'domain' => '" . esc_attr(parse_url($context['site_url'], PHP_URL_HOST)) . "',\n";
    $content .= "];\n";

    return $content;
}

// Configuration
[
    'type' => 'php_callback',
    'file' => '',                    // Not used for callbacks
    'search' => 'generate_license_file',
    'content' => 'template=premium,format=php'
]
```

### File Operations Mode
```php
/**
 * Custom ZIP manipulation
 */
function custom_zip_operations( $context, $zip, $args = [] ) {
    // Add multiple generated files
    $config_content = json_encode([
        'customer' => $context['customer_id'],
        'license' => hash('sha256', $context['license_key']),
        'domain' => parse_url($context['site_url'], PHP_URL_HOST)
    ]);

    $zip->addFromString($context['base_path'] . 'config.json', $config_content);

    // Modify existing files
    $readme_content = $zip->getFromName($context['base_path'] . 'README.txt');
    if ($readme_content !== false) {
        $footer = "\n\n--- Licensed to Customer #{$context['customer_id']} ---";
        $zip->deleteName($context['base_path'] . 'README.txt');
        $zip->addFromString($context['base_path'] . 'README.txt', $readme_content . $footer);
    }

    // Function returns void
}

// Configuration
[
    'type' => 'php_callback',
    'file' => '',
    'search' => 'custom_zip_operations',
    'content' => ''                  // No custom args needed
]
```

### Advanced Analytics Callback
```php
/**
 * Log download analytics and apply dynamic watermark
 */
function analytics_watermark( $context, $zip, $args = [] ) {
    // Log download event
    wp_insert_post([
        'post_type' => 'download_log',
        'post_title' => 'Download: ' . $context['download_name'],
        'post_content' => json_encode([
            'customer_id' => $context['customer_id'],
            'license_key' => $context['license_key'],
            'timestamp' => $context['timestamp'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]),
        'meta_input' => [
            'download_id' => $context['download_id'],
            'customer_id' => $context['customer_id']
        ]
    ]);

    // Return dynamic content based on customer tier
    $customer = new EDD_Customer($context['customer_id']);
    $total_spent = $customer->purchase_value;

    if ($total_spent > 1000) {
        return "VIP Customer License\nTotal Spent: $" . number_format($total_spent, 2);
    } elseif ($total_spent > 100) {
        return "Premium Customer License\nMember Since: " . date('Y', strtotime($customer->date_created));
    } else {
        return "Standard License\nCustomer ID: " . $context['customer_id'];
    }
}
```

## Security Considerations

### Function Validation
```php
// Implement in watermark_zip() function
function is_callback_allowed( $callback ) {
    // Check if callable exists
    if ( ! is_callable( $callback ) ) {
        return false;
    }

    // Whitelist allowed function prefixes/namespaces
    $allowed_prefixes = apply_filters( 'edd_watermark_callback_prefixes', [
        'edd_watermark_',
        'my_watermark_',
        // User-defined prefixes
    ]);

    foreach ( $allowed_prefixes as $prefix ) {
        if ( strpos( $callback, $prefix ) === 0 ) {
            return true;
        }
    }

    // Check for explicitly allowed functions
    $allowed_functions = apply_filters( 'edd_watermark_allowed_callbacks', [] );

    return in_array( $callback, $allowed_functions, true );
}
```

### Error Handling
```php
function execute_callback_safely( $callback, $context, $zip, $custom_args, $error_handling ) {
    try {
        return call_user_func( $callback, $context, $zip, $custom_args );
    } catch ( Exception $e ) {
        switch ( $error_handling ) {
            case 'log':
                error_log( 'EDD Watermark Callback Error: ' . $e->getMessage() );
                break;
            case 'fallback':
                return apply_filters( 'edd_watermark_callback_fallback', '', $callback, $context );
            case 'skip':
            default:
                break;
        }
        return null;
    }
}
```

## Integration Points

### Settings UI Enhancement
Add new option to watermark type dropdown:
```html
<option value="php_callback">PHP Callback</option>
```

**Field Usage for Callbacks:**
- **Watermark Type**: Select `PHP Callback`
- **File to Modify**: Not used (can be left empty or used for notes)
- **Search String**: Enter the PHP function name (e.g., `my_watermark_function`)
- **Watermark Content**: Enter arguments as comma-separated pairs (e.g., `template=premium,multiplier=3`)

### Hooks and Filters

#### New Action Hooks
```php
// Before callback execution
do_action( 'edd_watermark_before_callback', $callback, $context, $zip );

// After callback execution
do_action( 'edd_watermark_after_callback', $callback, $result, $context, $zip );
```

#### New Filter Hooks
```php
// Modify context data before passing to callback
$context = apply_filters( 'edd_watermark_callback_context', $context, $callback );

// Modify callback result before processing
$result = apply_filters( 'edd_watermark_callback_result', $result, $callback, $context );

// Define allowed callback prefixes
$prefixes = apply_filters( 'edd_watermark_callback_prefixes', $default_prefixes );

// Define explicitly allowed callback functions
$functions = apply_filters( 'edd_watermark_allowed_callbacks', [] );
```

## Migration and Compatibility

### Backward Compatibility
- Existing watermark methods unchanged
- No breaking changes to current functionality
- New callback method adds alongside existing options

### Version Requirements
- PHP 7.4+ (for enhanced callable validation)
- WordPress 5.0+ (for modern hook system)
- EDD 3.0+ (for customer object methods)

## Testing Strategy

### Unit Tests
- Callback function validation
- Context data structure
- Error handling scenarios
- Return mode processing

### Integration Tests
- ZIP file manipulation
- EDD download integration
- Multi-watermark sequences
- Performance with large files

### Security Tests
- Malicious callback rejection
- Namespace restriction enforcement
- Error information disclosure
- Resource exhaustion protection

## Performance Considerations

### Optimization Strategies
- Cache callback validation results
- Limit execution time per callback
- Memory usage monitoring
- Async processing for heavy operations

### Resource Limits
```php
// Implement execution limits
ini_set('max_execution_time', 30);
ini_set('memory_limit', '256M');
```

## Documentation Requirements

### User Documentation
- Callback function development guide
- Security best practices
- Example implementations
- Troubleshooting guide

### Developer Documentation
- Context data reference
- Hook system documentation
- Extension development guide
- API reference

## Implementation Priority

1. **Phase 1**: Core callback execution engine
2. **Phase 2**: Security validation system
3. **Phase 3**: Settings UI integration
4. **Phase 4**: Enhanced context data
5. **Phase 5**: Advanced features and optimization

This specification provides a comprehensive foundation for implementing a secure, flexible PHP callback watermark system that extends the existing EDD File Watermarking functionality.
