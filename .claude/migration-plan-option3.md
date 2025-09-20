# Migration Plan: Option 3 - WordPress Settings API (Proper Implementation)

## Overview
This plan migrates the DC Membership plugin settings from the current fragmented approach to a proper WordPress Settings API implementation. This will solve data loss issues, improve maintainability, and align with WordPress best practices.

## Current State Analysis

### Current Problems
1. **Data Loss**: Saving one settings tab overwrites settings from other tabs
2. **Fragile Architecture**: Manual hidden field management required for each tab
3. **Maintenance Burden**: Every new setting requires updates across multiple functions
4. **Non-Standard**: Not using WordPress Settings API correctly

### Current Structure
```
dcmm_settings = [
  // General settings
  'dcmm_enable_dues' => true,
  'auto_sync_wp_users' => true,

  // Payment settings
  'dcmm_paypal_client_id' => 'xxx',

  // Email settings
  'dcmm_enable_welcome_emails' => true
]
```

## Target Architecture

### WordPress Settings API (Proper)
```php
// Single option group with multiple sections
register_setting('dcmm_settings', 'dcmm_settings', [
    'sanitize_callback' => 'dcmm_sanitize_all_settings'
]);

// Sections for logical grouping
add_settings_section('general', 'General Settings', null, 'dcmm_settings');
add_settings_section('payment', 'Payment Settings', null, 'dcmm_settings');
add_settings_section('email', 'Email Settings', null, 'dcmm_settings');

// Fields assigned to sections
add_settings_field('enable_dues', 'Enable Dues', 'render_enable_dues', 'dcmm_settings', 'general');
add_settings_field('paypal_client_id', 'PayPal Client ID', 'render_paypal_client_id', 'dcmm_settings', 'payment');
```

## Migration Steps

### Phase 1: Analysis and Preparation

#### 1.1 Audit Current Settings
Run this analysis script to catalog all current settings:

```php
function audit_current_settings() {
    $settings = get_option('dcmm_settings', []);
    $payment_settings = get_option('dcmm_payment_settings', []);
    $email_settings = get_option('dcmm_email_settings', []);

    error_log('Current dcmm_settings: ' . print_r($settings, true));
    error_log('Payment settings: ' . print_r($payment_settings, true));
    error_log('Email settings: ' . print_r($email_settings, true));

    // Find all add_settings_field calls
    $file = file_get_contents(__DIR__ . '/settings.php');
    preg_match_all("/add_settings_field\(\s*'([^']+)'/", $file, $matches);
    error_log('Found settings fields: ' . print_r($matches[1], true));
}
```

#### 1.2 Create Settings Mapping
Document which settings belong to which logical groups:

```php
$settings_mapping = [
    'general' => [
        'dcmm_enable_dues',
        'dcmm_dues_amount',
        'dcmm_membership_term_length',
        'renewal_window_days',
        'grace_period_days',
        'renewal_notice_days',
        'dcmm_join_policy',
        'dcmm_anchor_date',
        'dcmm_my_account_page',
        'auto_sync_wp_users'
    ],
    'payment' => [
        'dcmm_enable_offline_payments',
        'dcmm_offline_payment_methods',
        'dcmm_offline_require_reference',
        'dcmm_paypal_environment',
        'dcmm_paypal_client_id',
        'dcmm_paypal_client_secret',
        'dcmm_paypal_webhook_id'
    ],
    'email' => [
        'dcmm_email_from_name',
        'dcmm_email_from_email',
        'dcmm_enable_welcome_emails',
        'dcmm_welcome_email_subject',
        'dcmm_welcome_email_template',
        'dcmm_enable_renewal_emails',
        'dcmm_renewal_email_subject',
        'dcmm_renewal_email_template',
        'dcmm_enable_expiration_notifications',
        'dcmm_30_day_notification',
        'dcmm_7_day_notification',
        'dcmm_1_day_notification',
        'dcmm_expired_notification'
    ]
];
```

### Phase 2: Infrastructure Setup

#### 2.1 Create New Settings Architecture
Replace the current `register_settings()` function:

```php
function register_settings() {
    // Single option registration with comprehensive sanitization
    register_setting('dcmm_settings', 'dcmm_settings', [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\sanitize_all_settings',
        'default' => []
    ]);

    // Register sections
    add_settings_section(
        'dcmm_general_section',
        __('Membership Settings', 'dcmm-membership'),
        __NAMESPACE__ . '\render_general_section_description',
        'dcmm_settings'
    );

    add_settings_section(
        'dcmm_payment_section',
        __('Payment Settings', 'dcmm-membership'),
        __NAMESPACE__ . '\render_payment_section_description',
        'dcmm_settings'
    );

    add_settings_section(
        'dcmm_email_section',
        __('Email Settings', 'dcmm-membership'),
        __NAMESPACE__ . '\render_email_section_description',
        'dcmm_settings'
    );

    // Register all fields
    register_general_fields();
    register_payment_fields();
    register_email_fields();
}
```

#### 2.2 Create Sanitization Function
Central sanitization for all settings:

```php
function sanitize_all_settings($input) {
    $sanitized = [];

    // Define field types for proper sanitization
    $field_types = [
        'dcmm_enable_dues' => 'boolean',
        'dcmm_dues_amount' => 'float',
        'dcmm_membership_term_length' => 'select',
        'renewal_window_days' => 'integer',
        'grace_period_days' => 'integer',
        'renewal_notice_days' => 'integer',
        'dcmm_join_policy' => 'select',
        'dcmm_anchor_date' => 'date',
        'dcmm_my_account_page' => 'integer',
        'auto_sync_wp_users' => 'boolean',
        'dcmm_paypal_client_id' => 'text',
        'dcmm_paypal_client_secret' => 'text',
        'dcmm_paypal_environment' => 'select',
        'dcmm_email_from_name' => 'text',
        'dcmm_email_from_email' => 'email',
        // ... add all fields
    ];

    foreach ($input as $key => $value) {
        $type = $field_types[$key] ?? 'text';

        switch ($type) {
            case 'boolean':
                $sanitized[$key] = (bool) $value;
                break;
            case 'integer':
                $sanitized[$key] = (int) $value;
                break;
            case 'float':
                $sanitized[$key] = (float) $value;
                break;
            case 'email':
                $sanitized[$key] = sanitize_email($value);
                break;
            case 'select':
                $sanitized[$key] = sanitize_text_field($value);
                // Add validation for allowed values
                break;
            case 'date':
                $sanitized[$key] = sanitize_text_field($value);
                // Add date format validation
                break;
            default:
                $sanitized[$key] = sanitize_text_field($value);
        }
    }

    return $sanitized;
}
```

#### 2.3 Create Field Registration Functions
Break down field registration into logical groups:

```php
function register_general_fields() {
    $fields = [
        'dcmm_enable_dues' => [
            'title' => __('Enable Dues', 'dcmm-membership'),
            'callback' => 'render_enable_dues_field'
        ],
        'dcmm_dues_amount' => [
            'title' => __('Dues Amount', 'dcmm-membership'),
            'callback' => 'render_dues_amount_field'
        ],
        // ... add all general fields
    ];

    foreach ($fields as $id => $field) {
        add_settings_field(
            $id,
            $field['title'],
            __NAMESPACE__ . '\\' . $field['callback'],
            'dcmm_settings',
            'dcmm_general_section'
        );
    }
}

function register_payment_fields() {
    // Similar structure for payment fields
}

function register_email_fields() {
    // Similar structure for email fields
}
```

### Phase 3: Update Rendering Functions

#### 3.1 Update Tab Rendering
Replace current tab functions:

```php
function render_general_tab() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('dcmm_settings');
        do_settings_sections('dcmm_settings'); // This shows ALL sections
        submit_button();
        ?>
    </form>
    <?php
}

// For individual tabs, use custom section rendering
function render_general_tab_only() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('dcmm_settings');

        // Preserve all non-general settings as hidden fields
        $all_settings = get_option('dcmm_settings', []);
        $general_fields = get_general_field_names();

        foreach ($all_settings as $key => $value) {
            if (!in_array($key, $general_fields)) {
                printf(
                    '<input type="hidden" name="dcmm_settings[%s]" value="%s">',
                    esc_attr($key),
                    esc_attr($value)
                );
            }
        }

        // Render only general section
        do_settings_fields('dcmm_settings', 'dcmm_general_section');
        submit_button();
        ?>
    </form>
    <?php
}
```

#### 3.2 Create Field Render Functions
Standardized field rendering:

```php
function render_enable_dues_field() {
    $options = get_option('dcmm_settings', []);
    $value = isset($options['dcmm_enable_dues']) ? (bool) $options['dcmm_enable_dues'] : false;

    printf(
        '<input type="checkbox" id="dcmm_enable_dues" name="dcmm_settings[dcmm_enable_dues]" value="1" %s>',
        checked($value, true, false)
    );

    echo '<label for="dcmm_enable_dues">';
    esc_html_e('Enable membership dues collection', 'dcmm-membership');
    echo '</label>';
}

function render_dues_amount_field() {
    $options = get_option('dcmm_settings', []);
    $value = isset($options['dcmm_dues_amount']) ? esc_attr($options['dcmm_dues_amount']) : '';

    printf(
        '<input type="number" id="dcmm_dues_amount" name="dcmm_settings[dcmm_dues_amount]" value="%s" min="0" step="0.01">',
        $value
    );
}

// Repeat for all fields...
```

### Phase 4: Update Settings Access

#### 4.1 Update Settings Helper Functions
Replace current get_settings() function:

```php
function get_settings($key = null, $default = null) {
    $settings = get_option('dcmm_settings', []);

    if ($key === null) {
        return $settings;
    }

    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Backwards compatibility
function get_setting_value($key, $default = null) {
    return get_settings($key, $default);
}
```

#### 4.2 Update All Settings Usage
Find and replace throughout codebase:

```bash
# Find all get_option('dcmm_settings') calls
grep -r "get_option.*dcmm_settings" includes/

# Find all get_option('dcmm_general_settings') calls
grep -r "get_option.*dcmm_general_settings" includes/

# Replace with standardized function calls
```

### Phase 5: Data Migration

#### 5.1 Create Migration Function
Consolidate all separate options back into single option:

```php
function migrate_back_to_single_option() {
    // Skip if already migrated
    if (get_option('dcmm_wp_settings_api_migrated', false)) {
        return;
    }

    // Get all current settings
    $general_settings = get_option('dcmm_general_settings', []);
    $payment_settings = get_option('dcmm_payment_settings', []);
    $email_settings = get_option('dcmm_email_settings', []);
    $old_settings = get_option('dcmm_settings', []);

    // Merge all settings
    $consolidated = array_merge($old_settings, $general_settings, $payment_settings, $email_settings);

    // Save to single option
    update_option('dcmm_settings', $consolidated);

    // Clean up old options
    delete_option('dcmm_general_settings');
    delete_option('dcmm_payment_settings');
    delete_option('dcmm_email_settings');

    // Mark migration complete
    update_option('dcmm_wp_settings_api_migrated', true);

    error_log('DCMM: Migrated to WordPress Settings API. Total settings: ' . count($consolidated));
}

add_action('admin_init', __NAMESPACE__ . '\migrate_back_to_single_option', 1);
```

### Phase 6: Testing and Validation

#### 6.1 Create Testing Script
Comprehensive testing of new settings system:

```php
function test_settings_api_migration() {
    echo "=== WordPress Settings API Migration Test ===\n";

    // Test 1: Verify single option exists
    $settings = get_option('dcmm_settings', []);
    echo "Total settings found: " . count($settings) . "\n";

    // Test 2: Test setting access
    $test_keys = ['dcmm_enable_dues', 'auto_sync_wp_users', 'dcmm_paypal_client_id'];
    foreach ($test_keys as $key) {
        $value = get_settings($key);
        echo "Setting '$key': " . var_export($value, true) . "\n";
    }

    // Test 3: Test form rendering
    ob_start();
    render_general_tab();
    $form_html = ob_get_clean();
    echo "General form renders: " . (strlen($form_html) > 100 ? "YES" : "NO") . "\n";

    // Test 4: Test sanitization
    $test_input = [
        'dcmm_enable_dues' => '1',
        'dcmm_dues_amount' => '25.50',
        'invalid_field' => '<script>alert("test")</script>'
    ];

    $sanitized = sanitize_all_settings($test_input);
    echo "Sanitization test: " . var_export($sanitized, true) . "\n";

    echo "=== Test Complete ===\n";
}
```

#### 6.2 Validation Checklist
- [ ] All settings tabs save without data loss
- [ ] Settings values display correctly in forms
- [ ] Auto-sync functionality works with new settings structure
- [ ] PayPal settings save and load correctly
- [ ] Email settings preserve across tab switches
- [ ] No PHP errors in admin settings pages
- [ ] All existing settings preserved after migration

### Phase 7: Cleanup and Documentation

#### 7.1 Remove Legacy Code
After successful migration:

```php
// Remove these functions:
// - get_general_settings()
// - get_payment_settings()
// - get_email_settings()
// - migrate_to_separate_options()

// Remove these options:
// - dcmm_settings_migrated
// - dcmm_general_settings
// - dcmm_payment_settings
// - dcmm_email_settings
```

#### 7.2 Update Documentation
Update DEVELOPER_DOCS.md with new settings architecture:

```markdown
## Settings Architecture

The plugin uses WordPress Settings API properly with:
- Single option: `dcmm_settings`
- Multiple sections: general, payment, email
- Centralized sanitization
- No data loss between tabs

### Settings Access:
```php
// Get specific setting
$value = DCMM_Settings\get_settings('auto_sync_wp_users', true);

// Get all settings
$all_settings = DCMM_Settings\get_settings();
```
```

## Implementation Timeline

### Week 1: Foundation
- [ ] Phase 1: Analysis and audit
- [ ] Phase 2: Infrastructure setup
- [ ] Create sanitization function

### Week 2: Core Implementation
- [ ] Phase 3: Update rendering functions
- [ ] Phase 4: Update settings access
- [ ] Create field render functions

### Week 3: Migration and Testing
- [ ] Phase 5: Data migration
- [ ] Phase 6: Testing and validation
- [ ] Bug fixes and refinements

### Week 4: Finalization
- [ ] Phase 7: Cleanup and documentation
- [ ] Final testing
- [ ] Deployment

## Risk Mitigation

### Backup Strategy
```php
// Before migration, backup current settings
function backup_current_settings() {
    $backup = [
        'dcmm_settings' => get_option('dcmm_settings', []),
        'dcmm_general_settings' => get_option('dcmm_general_settings', []),
        'dcmm_payment_settings' => get_option('dcmm_payment_settings', []),
        'dcmm_email_settings' => get_option('dcmm_email_settings', []),
        'timestamp' => current_time('mysql')
    ];

    update_option('dcmm_settings_backup_' . time(), $backup);
}
```

### Rollback Plan
If migration fails:
1. Restore from backup
2. Revert code to previous version
3. Re-enable Option 1 (separate options)
4. Debug issues before retry

## Success Criteria

✅ **No data loss** when switching between settings tabs
✅ **All existing functionality** continues to work
✅ **Performance improvement** (fewer database queries)
✅ **Code maintainability** improved significantly
✅ **WordPress standards compliance** achieved
✅ **Easy to add new settings** in the future

## Final Notes

This migration represents a significant architectural improvement that will:

1. **Eliminate data loss issues** permanently
2. **Reduce maintenance burden** substantially
3. **Improve code quality** and WordPress compliance
4. **Provide foundation** for future feature development
5. **Enhance user experience** with reliable settings

The migration should be done carefully with thorough testing, but the long-term benefits make it worthwhile for the plugin's future sustainability.