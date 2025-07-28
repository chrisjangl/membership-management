<?php
/**
 * Settings for the Membership Management plugin.
 * 
 * @package Membership Management
 * @since 1.1.0
 */

namespace DCMM_Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Add page in a custom admin menu group (Membership Management).
 * 
 * @since 1.1.0
 * @param array $pages Array of pages to register.
 * @return array Modified array of pages with the settings page added.
 */
function add_settings_page( ) {

    add_submenu_page(
        'edit.php?post_type=dcmm-member', // Parent slug
        __( 'Settings', 'dcmm-membership' ), // Page title
        __( 'Settings', 'dcmm-membership' ), // Menu title
        'manage_options', // Capability
        'dcmm_settings', // Menu slug
        __NAMESPACE__ . '\settings_page_callback' // Callback function
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_page' );

/**
 * Callback function for the settings page.
 * 
 * @since 1.1.0
 */
function settings_page_callback() {
    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    
    // Define tabs
    $tabs = array(
        'general' => __('General', 'dcmm-membership'),
        'payments' => __('Payment Gateways', 'dcmm-membership'),
        'emails' => __('Email & Notifications', 'dcmm-membership'),
        'premium' => __('Premium Features', 'dcmm-membership')
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Membership Management Settings', 'dcmm-membership' ); ?></h1>
        
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_key => $tab_label): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, admin_url('edit.php?post_type=dcmm-member&page=dcmm_settings'))); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <?php
            switch ($current_tab) {
                case 'general':
                    render_general_tab();
                    break;
                case 'payments':
                    render_payments_tab();
                    break;
                case 'emails':
                    render_emails_tab();
                    break;
                case 'premium':
                    render_premium_tab();
                    break;
                default:
                    render_general_tab();
                    break;
            }
            ?>
        </div>
    </div>
    
    <style>
    .tab-content {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-top: none;
        padding: 20px;
        margin-top: 0;
    }
    .nav-tab-wrapper {
        margin-bottom: 0;
    }
    </style>
    <?php
}

/**
 * Render General tab content
 */
function render_general_tab() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'dcmm_settings_group' );
        
        // Preserve payment gateway settings as hidden fields
        $current_settings = get_option( 'dcmm_settings', array() );
        $payment_fields = array( 'dcmm_enable_offline_payments', 'dcmm_offline_payment_methods', 'dcmm_offline_require_reference', 'dcmm_paypal_client_id', 'dcmm_paypal_client_secret', 'dcmm_paypal_environment', 'dcmm_paypal_webhook_id' );
        foreach ( $payment_fields as $field ) {
            if ( isset( $current_settings[$field] ) ) {
                if ( is_array( $current_settings[$field] ) ) {
                    foreach ( $current_settings[$field] as $key => $value ) {
                        echo '<input type="hidden" name="dcmm_settings[' . esc_attr($field) . '][' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="dcmm_settings[' . esc_attr($field) . ']" value="' . esc_attr($current_settings[$field]) . '">';
                }
            }
        }
        
        // Render only membership settings section
        echo '<h2>' . __('Membership Settings', 'dcmm-membership') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        do_settings_fields( 'dcmm_settings_group', 'dcmm_membership_settings' );
        echo '</table>';
        
        submit_button();
        ?>
    </form>
    <?php
}

/**
 * Render Payment Gateways tab content
 */
function render_payments_tab() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'dcmm_settings_group' );
        
        // Preserve general membership settings as hidden fields
        $current_settings = get_option( 'dcmm_settings', array() );
        $general_fields = array( 'dcmm_enable_dues', 'dcmm_dues_amount', 'dcmm_membership_term_length', 'dcmm_join_policy', 'dcmm_anchor_date', 'dcmm_my_account_page', 'renewal_window_days', 'grace_period_days', 'renewal_notice_days', 'dcmm_paypal_client_id', 'dcmm_paypal_client_secret', 'dcmm_paypal_environment' );
        foreach ( $general_fields as $field ) {
            if ( isset( $current_settings[$field] ) ) {
                echo '<input type="hidden" name="dcmm_settings[' . esc_attr($field) . ']" value="' . esc_attr($current_settings[$field]) . '">';
            }
        }
        
        // Render Offline Payment settings section
        echo '<h2>' . __('Offline Payment Settings', 'dcmm-membership') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        do_settings_fields( 'dcmm_settings_group', 'dcmm_offline_payment_settings' );
        echo '</table>';
        
        // Render PayPal settings section
        echo '<h2>' . __('PayPal Settings', 'dcmm-membership') . '</h2>';
        
        // Show the section description
        $paypal_section_callback = function() {
            ?>
            <section class="dcmm-paypal-settings-section">
                <p><?php esc_html_e( 'Configure PayPal payment processing for membership dues.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-paypal-setup-instructions" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'PayPal Setup Instructions:', 'dcmm-membership' ); ?></h4>
                    <ol>
                        <li>
                            <strong><?php esc_html_e( 'Create a PayPal Developer Account:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Visit', 'dcmm-membership' ); ?> <a href="https://developer.paypal.com/" target="_blank">https://developer.paypal.com/</a> <?php esc_html_e( 'and sign in with your PayPal account.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Create an Application:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Go to', 'dcmm-membership' ); ?> <a href="https://developer.paypal.com/developer/applications/" target="_blank"><?php esc_html_e( 'My Apps & Credentials', 'dcmm-membership' ); ?></a> <?php esc_html_e( 'and click "Create App".', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Configure Your App:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Choose "Default Application" and select your business account. Make sure to enable "Accept payments" feature.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Copy Credentials:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Copy the Client ID and Client Secret from your app details below.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Set Up Webhooks (Optional):', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'For real-time payment notifications, configure webhooks in your PayPal app using the webhook URL shown below.', 'dcmm-membership' ); ?>
                        </li>
                    </ol>
                    <p><em><?php esc_html_e( 'Start with Sandbox environment for testing, then switch to Live when ready for production.', 'dcmm-membership' ); ?></em></p>
                </div>
            </section>
            <?php
        };
        $paypal_section_callback();
        
        echo '<table class="form-table" role="presentation">';
        do_settings_fields( 'dcmm_settings_group', 'dcmm_paypal_settings' );
        echo '</table>';
        
        submit_button();
        ?>
    </form>
    <?php
}

/**
 * Render Premium Features tab content
 */
function render_premium_tab() {
    // Get premium manager instance
    $premium_manager = \DCMM\Premium\Premium_Manager::get_instance();
    
    // Force load features if not already loaded
    $premium_manager->load_features();
    
    $features_data = $premium_manager->get_features_settings_data();
    
    ?>
    <div class="dcmm-premium-features">
        <h2><?php esc_html_e('Premium Features', 'dcmm-membership'); ?></h2>
        <p><?php esc_html_e('Enable and configure premium features for your membership plugin.', 'dcmm-membership'); ?></p>
        
        <!-- MailChimp API Configuration -->
        <div class="dcmm-mailchimp-config" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 20px; margin-bottom: 20px;">
            <h3><?php esc_html_e('MailChimp Configuration', 'dcmm-membership'); ?></h3>
            <p><?php esc_html_e('Configure your MailChimp API connection to enable email list integrations.', 'dcmm-membership'); ?></p>
            
            <form id="dcmm-mailchimp-api-form" style="margin-top: 15px;">
                <?php wp_nonce_field('dcmm_mailchimp_api', 'dcmm_mailchimp_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dcmm_mailchimp_api_key"><?php esc_html_e('MailChimp API Key', 'dcmm-membership'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="dcmm_mailchimp_api_key" name="dcmm_mailchimp_api_key" 
                                   value="<?php echo esc_attr(get_option('dcmm_mailchimp_api_key', '')); ?>" 
                                   class="regular-text" placeholder="your-api-key-here-us1" />
                            <button type="button" id="dcmm-test-connection" class="button" style="margin-left: 10px;">
                                <?php esc_html_e('Test Connection', 'dcmm-membership'); ?>
                            </button>
                            <div id="dcmm-connection-status" style="margin-top: 5px;"></div>
                            <p class="description">
                                <?php esc_html_e('Your MailChimp API key from your MailChimp account.', 'dcmm-membership'); ?><br>
                                <strong><?php esc_html_e('How to find your API key:', 'dcmm-membership'); ?></strong>
                                <?php esc_html_e('Log in to MailChimp → Account → Extras → API keys → Create A Key', 'dcmm-membership'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save MailChimp Settings', 'dcmm-membership'); ?>" />
                </p>
            </form>
        </div>
        
        <?php if (empty($features_data)): ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('No premium features are currently available.', 'dcmm-membership'); ?></p>
            </div>
        <?php else: ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dcmm_premium_settings', 'dcmm_premium_nonce'); ?>
                <input type="hidden" name="action" value="dcmm_save_premium_settings" />
                
                <?php foreach ($features_data as $feature_id => $feature): ?>
                    <div class="dcmm-premium-feature" style="border: 1px solid #ddd; margin-bottom: 20px; padding: 20px; background: #fff;">
                        <div class="dcmm-feature-header" style="display: flex; align-items: center; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0; font-size: 18px;"><?php echo esc_html($feature['name']); ?></h3>
                                <p style="margin: 5px 0 0 0; color: #666;"><?php echo esc_html($feature['description']); ?></p>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <label class="dcmm-toggle-switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                                    <input type="checkbox" name="features[<?php echo esc_attr($feature_id); ?>][enabled]" 
                                           value="1" <?php checked($feature['enabled']); ?>
                                           <?php echo !$feature['dependencies_met'] ? 'disabled' : ''; ?>
                                           style="opacity: 0; width: 0; height: 0;">
                                    <span class="dcmm-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;"></span>
                                </label>
                            </div>
                        </div>
                        
                        <?php if (!$feature['dependencies_met'] && !empty($feature['dependency_errors'])): ?>
                            <div class="notice notice-error" style="margin: 10px 0;">
                                <p><strong><?php esc_html_e('Dependencies not met:', 'dcmm-membership'); ?></strong></p>
                                <ul style="margin: 5px 0;">
                                    <?php foreach ($feature['dependency_errors'] as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($feature['settings_fields'])): ?>
                            <div class="dcmm-feature-settings" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                                <h4><?php esc_html_e('Settings', 'dcmm-membership'); ?></h4>
                                <table class="form-table">
                                    <?php foreach ($feature['settings_fields'] as $field_id => $field): ?>
                                        <tr>
                                            <th scope="row">
                                                <label for="<?php echo esc_attr($field_id); ?>">
                                                    <?php echo esc_html($field['title']); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <?php 
                                                $field_name = "features[{$feature_id}][settings][{$field_id}]";
                                                $field_value = get_option($field_id, $field['default'] ?? '');
                                                
                                                switch ($field['type']) {
                                                    case 'select':
                                                        echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                                                        foreach ($field['options'] as $value => $label) {
                                                            echo '<option value="' . esc_attr($value) . '" ' . selected($field_value, $value, false) . '>';
                                                            echo esc_html($label);
                                                            echo '</option>';
                                                        }
                                                        echo '</select>';
                                                        break;
                                                        
                                                    case 'checkbox':
                                                        echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="1" ' . checked($field_value, true, false) . ' />';
                                                        break;
                                                        
                                                    default:
                                                        echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="regular-text" />';
                                                        break;
                                                }
                                                ?>
                                                <?php if (!empty($field['description'])): ?>
                                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Save Premium Settings', 'dcmm-membership'); ?>" />
                </p>
            </form>
            
        <?php endif; ?>
    </div>
    
    <style>
    .dcmm-toggle-switch input:checked + .dcmm-slider {
        background-color: #2196F3;
    }
    
    .dcmm-toggle-switch input:focus + .dcmm-slider {
        box-shadow: 0 0 1px #2196F3;
    }
    
    .dcmm-toggle-switch input:checked + .dcmm-slider:before {
        transform: translateX(26px);
    }
    
    .dcmm-slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    .dcmm-premium-feature {
        transition: box-shadow 0.3s ease;
    }
    
    .dcmm-premium-feature:hover {
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    #dcmm-connection-status.success {
        color: #46b450;
        font-weight: bold;
    }
    
    #dcmm-connection-status.error {
        color: #dc3232;
        font-weight: bold;
    }
    
    #dcmm-connection-status.testing {
        color: #0073aa;
    }
    </style>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle MailChimp API form submission
        $('#dcmm-mailchimp-api-form').on('submit', function(e) {
            e.preventDefault();
            
            var apiKey = $('#dcmm_mailchimp_api_key').val();
            var nonce = $('#dcmm_mailchimp_nonce').val();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dcmm_save_mailchimp_api_key',
                    api_key: apiKey,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#dcmm-connection-status').html('<span class="success">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#dcmm-connection-status').html('<span class="error">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $('#dcmm-connection-status').html('<span class="error">✗ Failed to save API key</span>');
                }
            });
        });
        
        // Handle connection test
        $('#dcmm-test-connection').on('click', function() {
            var apiKey = $('#dcmm_mailchimp_api_key').val();
            var nonce = $('#dcmm_mailchimp_nonce').val();
            
            if (!apiKey) {
                $('#dcmm-connection-status').html('<span class="error">✗ Please enter an API key first</span>');
                return;
            }
            
            $('#dcmm-connection-status').html('<span class="testing">Testing connection...</span>');
            $(this).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dcmm_test_mailchimp_connection',
                    api_key: apiKey,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#dcmm-connection-status').html('<span class="success">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#dcmm-connection-status').html('<span class="error">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $('#dcmm-connection-status').html('<span class="error">✗ Connection test failed</span>');
                },
                complete: function() {
                    $('#dcmm-test-connection').prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render Email & Notifications tab content
 */
function render_emails_tab() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'dcmm_settings_group' );
        
        // Email Settings Section
        echo '<h2>' . __('Email Settings', 'dcmm-membership') . '</h2>';
        
        // Show the section description with merge tags
        $email_section_callback = function() {
            ?>
            <section class="dcmm-email-settings-section">
                <p><?php esc_html_e( 'Configure email notifications for member signups and renewals.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-email-merge-tags" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags:', 'dcmm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your email templates:', 'dcmm-membership' ); ?></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{first_name}">{first_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{last_name}">{last_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{full_name}">{full_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{email}">{email}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{membership_start_date}">{membership_start_date}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{membership_status}">{membership_status}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{renewal_date}">{renewal_date}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{site_name}">{site_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{site_url}">{site_url}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{payment_details}">{payment_details}</button>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">
                        <strong>Note:</strong> <code>{payment_details}</code> will only show content in renewal emails when payment was made.
                    </p>
                </div>
            </section>
            <?php
        };
        $email_section_callback();
        
        echo '<table class="form-table" role="presentation">';
        do_settings_fields( 'dcmm_settings_group', 'dcmm_email_settings' );
        echo '</table>';
        
        // Expiration Notification Settings Section
        echo '<h2>' . __('Expiration Notification Settings', 'dcmm-membership') . '</h2>';
        
        // Show the expiration section description
        $expiration_section_callback = function() {
            ?>
            <section class="dcmm-expiration-notification-section">
                <p><?php esc_html_e( 'Configure automated email notifications for membership expiration reminders.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-expiration-merge-tags" style="background: #f9f9f9; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags for Expiration Emails:', 'dcmm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your expiration email templates:', 'dcmm-membership' ); ?></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{first_name}">{first_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{last_name}">{last_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{full_name}">{full_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{email}">{email}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{membership_status}">{membership_status}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{expiration_date}">{expiration_date}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{days_until_expiration}">{days_until_expiration}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{renewal_url}">{renewal_url}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{site_name}">{site_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{site_url}">{site_url}</button>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">
                        <strong>Note:</strong> <code>{renewal_url}</code> will link to your member dashboard renewal page.
                    </p>
                </div>
            </section>
            <?php
        };
        $expiration_section_callback();
        
        echo '<table class="form-table" role="presentation">';
        do_settings_fields( 'dcmm_settings_group', 'dcmm_expiration_notification_settings' );
        echo '</table>';
        
        submit_button();
        ?>
    </form>
    <?php
}

/**
 * Handle premium settings form submission
 */
function handle_premium_settings_save() {
    // Check nonce and permissions
    if (!wp_verify_nonce($_POST['dcmm_premium_nonce'], 'dcmm_premium_settings') || !current_user_can('manage_options')) {
        wp_die(__('Security check failed.', 'dcmm-membership'));
    }
    
    $premium_manager = \DCMM\Premium\Premium_Manager::get_instance();
    
    // Make sure features are loaded
    $premium_manager->load_features();
    
    $features_data = $_POST['features'] ?? array();
    
    // Debug: Log what we're receiving
    error_log('DCMM: Premium settings save - Features data: ' . print_r($features_data, true));
    
    // Process each feature
    foreach ($features_data as $feature_id => $feature_data) {
        $feature = $premium_manager->get_feature($feature_id);
        if (!$feature) {
            continue;
        }
        
        // Handle enable/disable
        $enabled = isset($feature_data['enabled']) && $feature_data['enabled'];
        
        if ($enabled && !$premium_manager->is_feature_enabled($feature_id)) {
            $premium_manager->enable_feature($feature_id);
        } elseif (!$enabled && $premium_manager->is_feature_enabled($feature_id)) {
            $premium_manager->disable_feature($feature_id);
        }
        
        // Handle feature settings
        if (isset($feature_data['settings'])) {
            $validated_settings = $feature->validate_settings($feature_data['settings']);
            foreach ($validated_settings as $setting_key => $setting_value) {
                update_option($setting_key, $setting_value);
            }
        }
    }
    
    // Redirect back to premium tab with success message
    $redirect_url = add_query_arg(array(
        'page' => 'dcmm_settings',
        'tab' => 'premium',
        'settings-updated' => 'true'
    ), admin_url('edit.php?post_type=dcmm-member'));
    
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_post_dcmm_save_premium_settings', __NAMESPACE__ . '\handle_premium_settings_save');

/**
 * Handle MailChimp API key saving via AJAX
 */
function handle_mailchimp_api_save() {
    // Check nonce and permissions
    if (!wp_verify_nonce($_POST['nonce'], 'dcmm_mailchimp_api') || !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'dcmm-membership')));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('API key cannot be empty.', 'dcmm-membership')));
        return;
    }

    require_once( DCMM_PATH . 'includes/premium/integrations/mailchimp/class-mailchimp-api.php' );
    
    // Save the API key
    update_option('dcmm_mailchimp_api_key', $api_key);
    
    // Clear any cached lists
    \DCMM\MailChimp\MailChimp_API::clear_lists_cache();
    
    wp_send_json_success(array('message' => __('MailChimp API key saved successfully!', 'dcmm-membership')));
}
add_action('wp_ajax_dcmm_save_mailchimp_api_key', __NAMESPACE__ . '\handle_mailchimp_api_save');

/**
 * Handle MailChimp connection test via AJAX
 */
function handle_mailchimp_connection_test() {
    // Check nonce and permissions
    if (!wp_verify_nonce($_POST['nonce'], 'dcmm_mailchimp_api') || !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'dcmm-membership')));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('API key cannot be empty.', 'dcmm-membership')));
        return;
    }
    
    try {
        // Include the MailChimp API class
        require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/class-mailchimp-api.php';
        
        $mailchimp_api = new \DCMM\MailChimp\MailChimp_API($api_key);
        $result = $mailchimp_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => __('Connection test failed: ', 'dcmm-membership') . $e->getMessage()));
    }
}
add_action('wp_ajax_dcmm_test_mailchimp_connection', __NAMESPACE__ . '\handle_mailchimp_connection_test');

/**
 * Register settings for the Membership Management plugin.
 * 
 * @since 1.1.0
 */
function register_settings() {
    register_setting( 'dcmm_settings_group', 'dcmm_settings' );

    add_settings_section(
        'dcmm_membership_settings',
        __( 'Membership Settings', 'dcmm-membership' ),
        null,
        'dcmm_settings_group'
    );

    // Enable dues
    add_settings_field(
        'dcmm_enable_dues',
        __( 'Charge Dues', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $checked = isset( $options['dcmm_enable_dues'] ) ? (bool) $options['dcmm_enable_dues'] : false;
            ?>
            <input type="checkbox" id="dcmm_enable_dues" name="dcmm_settings[dcmm_enable_dues]" value="1" <?php checked( $checked ); ?> />
            <label for="dcmm_enable_dues"><?php esc_html_e( 'Is there a charge/fee/cost to being a member?', 'dcmm-membership' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );


    // Dues amount setting
    add_settings_field(
        'dcmm_dues_amount',
        __( '', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $dues_amount = isset( $options['dcmm_dues_amount'] ) ? esc_attr( $options['dcmm_dues_amount'] ) : '';
            ?>
            <input type="text" id="dcmm_dues_amount" name="dcmm_settings[dcmm_dues_amount]" value="<?php echo esc_attr( $dues_amount ); ?>" />
            <label for="dcmm_dues_amount"><?php esc_html_e( 'Set the dues amount for members', 'dcmm' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_dues_amount',
        [
            'type' => 'string',
            'default' => '',
        ]
    );

    // Membership term length
    add_settings_field(
        'dcmm_membership_term_length',
        __( 'Membership Duration', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $membership_period = isset( $options['dcmm_membership_term_length'] ) ? esc_attr( $options['dcmm_membership_term_length'] ) : '';
            ?>
            <select id="dcmm_membership_term_length" name="dcmm_settings[dcmm_membership_term_length]">
                <option value="yearly" <?php selected( $membership_period, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'dcmm-membership' ); ?></option>
                <option value="seasonal" <?php selected( $membership_period, 'seasonal' ); ?>><?php esc_html_e( 'Seasonal', 'dcmm-membership' ); ?></option>
                <option value="monthly" <?php selected( $membership_period, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'dcmm-membership' ); ?></option>
            </select>
            <label for="dcmm_membership_term_length"><?php esc_html_e( 'Select the membership period', 'dcmm-membership' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    // Renewal Window Settings
    add_settings_field(
        'dcmm_renewal_window_settings',
        __( 'Renewal Window Settings', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $renewal_window_days = isset( $options['renewal_window_days'] ) ? intval( $options['renewal_window_days'] ) : 30;
            $grace_period_days = isset( $options['grace_period_days'] ) ? intval( $options['grace_period_days'] ) : 30;
            $renewal_notice_days = isset( $options['renewal_notice_days'] ) ? intval( $options['renewal_notice_days'] ) : 7;
            ?>
            <div class="dcmm-renewal-window-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="renewal_window_days"><?php esc_html_e( 'Renewal Window (days)', 'dcmm-membership' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="renewal_window_days" name="dcmm_settings[renewal_window_days]" value="<?php echo esc_attr( $renewal_window_days ); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e( 'Number of days before expiration that renewal becomes available. Default: 30 days.', 'dcmm-membership' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grace_period_days"><?php esc_html_e( 'Grace Period (days)', 'dcmm-membership' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="grace_period_days" name="dcmm_settings[grace_period_days]" value="<?php echo esc_attr( $grace_period_days ); ?>" min="0" max="365" />
                            <p class="description"><?php esc_html_e( 'Number of days after expiration that renewal is still allowed. Default: 30 days.', 'dcmm-membership' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="renewal_notice_days"><?php esc_html_e( 'Renewal Notice (days)', 'dcmm-membership' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="renewal_notice_days" name="dcmm_settings[renewal_notice_days]" value="<?php echo esc_attr( $renewal_notice_days ); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e( 'Show renewal notice X days before renewal window opens. Default: 7 days.', 'dcmm-membership' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_membership_term_length',
        [
            'type' => 'string',
            'default' => 'monthly',
        ]
    );

    // Does the membership period start/end with the calendar or does it start on a specific date?
    add_settings_field(
        'dcmm_join_policy',
        __( 'Membership Start Model / Join Policy', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $membership_start_end = isset( $options['dcmm_join_policy'] ) ? esc_attr( $options['dcmm_join_policy'] ) : '';
            ?>
            <select id="dcmm_join_policy" name="dcmm_settings[dcmm_join_policy]">
                <option value="fixed_term" <?php selected( $membership_start_end, 'fixed_term' ); ?>><?php esc_html_e( 'Fixed Calendar Term', 'dcmm-membership' ); ?></option>
                <option value="rolling" <?php selected( $membership_start_end, 'rolling' ); ?>><?php esc_html_e( 'Rolling (Anniversary-based)', 'dcmm-membership' ); ?></option>
                <option value="anchored_full_term" <?php selected( $membership_start_end, 'anchored_full_term' ); ?>><?php esc_html_e( 'Anchored Full-Term', 'dcmm-membership' ); ?></option>
            </select>
            <label for="dcmm_join_policy"><?php esc_html_e( 'This determines when the membership begins and how renewal is calculated.', 'dcmm-membership' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_join_policy',
        [
            'type' => 'string',
            'default' => 'fixed_term',
        ]
    );

    // Register renewal window settings
    register_setting(
        'dcmm_settings_group',
        'dcmm_renewal_window_days',
        [
            'type' => 'integer',
            'default' => 30,
        ]
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_grace_period_days',
        [
            'type' => 'integer',
            'default' => 30,
        ]
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_renewal_notice_days',
        [
            'type' => 'integer',
            'default' => 7,
        ]
    );

    // Offline Payment Settings Section
    add_settings_section(
        'dcmm_offline_payment_settings',
        __( 'Offline Payment Settings', 'dcmm-membership' ),
        function() {
            ?>
            <section class="dcmm-offline-payment-settings-section">
                <p><?php esc_html_e( 'Configure offline payment options for manual payment recording by administrators.', 'dcmm-membership' ); ?></p>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // Enable Offline Payments
    add_settings_field(
        'dcmm_enable_offline_payments',
        __( 'Enable Offline Payments', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $enabled = isset( $options['dcmm_enable_offline_payments'] ) ? (bool) $options['dcmm_enable_offline_payments'] : false;
            ?>
            <input type="checkbox" id="dcmm_enable_offline_payments" name="dcmm_settings[dcmm_enable_offline_payments]" value="1" <?php checked( $enabled ); ?> />
            <label for="dcmm_enable_offline_payments"><?php esc_html_e( 'Allow administrators to record offline payments when renewing memberships', 'dcmm-membership' ); ?></label>
            <p class="description"><?php esc_html_e( 'When enabled, the renewal button in the admin will prompt for payment details before processing the renewal.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_offline_payment_settings'
    );


    // Offline Payment Methods
    add_settings_field(
        'dcmm_offline_payment_methods',
        __( 'Payment Methods', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $methods = isset( $options['dcmm_offline_payment_methods'] ) ? $options['dcmm_offline_payment_methods'] : array(
                'cash' => 'Cash',
                'check' => 'Check',
                'transfer' => 'Bank Transfer',
                'other' => 'Other'
            );
            ?>
            <div class="dcmm-payment-methods">
                <p><?php esc_html_e( 'Configure available payment methods for offline payments:', 'dcmm-membership' ); ?></p>
                <table class="form-table">
                    <?php foreach ( $methods as $key => $label ): ?>
                    <tr>
                        <td>
                            <input type="text" name="dcmm_settings[dcmm_offline_payment_methods][<?php echo esc_attr( $key ); ?>]" 
                                   value="<?php echo esc_attr( $label ); ?>" class="regular-text" 
                                   placeholder="Payment method name" />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <p class="description"><?php esc_html_e( 'These options will appear in the payment method dropdown when recording offline payments.', 'dcmm-membership' ); ?></p>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_offline_payment_settings'
    );


    // Require Reference Numbers
    add_settings_field(
        'dcmm_offline_require_reference',
        __( 'Reference Numbers', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $required = isset( $options['dcmm_offline_require_reference'] ) ? (bool) $options['dcmm_offline_require_reference'] : false;
            ?>
            <input type="checkbox" id="dcmm_offline_require_reference" name="dcmm_settings[dcmm_offline_require_reference]" value="1" <?php checked( $required ); ?> />
            <label for="dcmm_offline_require_reference"><?php esc_html_e( 'Require reference numbers for offline payments', 'dcmm-membership' ); ?></label>
            <p class="description"><?php esc_html_e( 'When enabled, administrators must enter a reference number (check number, transaction ID, etc.) for each offline payment.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_offline_payment_settings'
    );


    // PayPal Settings Section
    add_settings_section(
        'dcmm_paypal_settings',
        __( 'PayPal Settings', 'dcmm-membership' ),
        function() {
            ?>
            <section class="dcmm-paypal-settings-section">
                <p><?php esc_html_e( 'Configure PayPal payment processing for membership dues.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-paypal-setup-instructions" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'PayPal Setup Instructions:', 'dcmm-membership' ); ?></h4>
                    <ol>
                        <li>
                            <strong><?php esc_html_e( 'Create a PayPal Developer Account:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Visit', 'dcmm-membership' ); ?> <a href="https://developer.paypal.com/" target="_blank">https://developer.paypal.com/</a> <?php esc_html_e( 'and sign in with your PayPal account.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Create an Application:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Go to', 'dcmm-membership' ); ?> <a href="https://developer.paypal.com/developer/applications/" target="_blank"><?php esc_html_e( 'My Apps & Credentials', 'dcmm-membership' ); ?></a> <?php esc_html_e( 'and click "Create App".', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Configure Your App:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Choose "Default Application" and select your business account. Make sure to enable "Accept payments" feature.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Copy Credentials:', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'Copy the Client ID and Client Secret from your app details below.', 'dcmm-membership' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Set Up Webhooks (Optional):', 'dcmm-membership' ); ?></strong><br>
                            <?php esc_html_e( 'For real-time payment notifications, configure webhooks in your PayPal app using the webhook URL shown below.', 'dcmm-membership' ); ?>
                        </li>
                    </ol>
                    <p><em><?php esc_html_e( 'Start with Sandbox environment for testing, then switch to Live when ready for production.', 'dcmm-membership' ); ?></em></p>
                </div>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // PayPal Environment (Sandbox vs Live)
    add_settings_field(
        'dcmm_paypal_environment',
        __( 'PayPal Environment', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $environment = isset( $options['dcmm_paypal_environment'] ) ? esc_attr( $options['dcmm_paypal_environment'] ) : 'sandbox';
            ?>
            <select id="dcmm_paypal_environment" name="dcmm_settings[dcmm_paypal_environment]">
                <option value="sandbox" <?php selected( $environment, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Testing)', 'dcmm-membership' ); ?></option>
                <option value="live" <?php selected( $environment, 'live' ); ?>><?php esc_html_e( 'Live (Production)', 'dcmm-membership' ); ?></option>
            </select>
            <p class="description">
                <?php esc_html_e( 'Use Sandbox for testing, Live for production payments.', 'dcmm-membership' ); ?><br>
                <strong><?php esc_html_e( 'Important:', 'dcmm-membership' ); ?></strong> 
                <?php esc_html_e( 'Sandbox and Live environments use different credentials. Make sure your Client ID and Secret match the selected environment.', 'dcmm-membership' ); ?>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_paypal_environment',
        [
            'type' => 'string',
            'default' => 'sandbox',
        ]
    );

    // PayPal Client ID
    add_settings_field(
        'dcmm_paypal_client_id',
        __( 'PayPal Client ID', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $client_id = isset( $options['dcmm_paypal_client_id'] ) ? esc_attr( $options['dcmm_paypal_client_id'] ) : '';
            ?>
            <input type="text" id="dcmm_paypal_client_id" name="dcmm_settings[dcmm_paypal_client_id]" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" />
            <p class="description">
                <?php esc_html_e( 'Your PayPal application Client ID from the PayPal Developer Dashboard.', 'dcmm-membership' ); ?><br>
                <strong><?php esc_html_e( 'Where to find:', 'dcmm-membership' ); ?></strong> 
                <?php esc_html_e( 'Log in to', 'dcmm-membership' ); ?> <a href="https://developer.paypal.com/developer/applications/" target="_blank"><?php esc_html_e( 'PayPal Developer Dashboard', 'dcmm-membership' ); ?></a>, 
                <?php esc_html_e( 'click on your app, and copy the "Client ID" from the app details page.', 'dcmm-membership' ); ?>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_paypal_client_id',
        [
            'type' => 'string',
            'default' => '',
        ]
    );

    // PayPal Client Secret
    add_settings_field(
        'dcmm_paypal_client_secret',
        __( 'PayPal Client Secret', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $client_secret = isset( $options['dcmm_paypal_client_secret'] ) ? esc_attr( $options['dcmm_paypal_client_secret'] ) : '';
            ?>
            <input type="password" id="dcmm_paypal_client_secret" name="dcmm_settings[dcmm_paypal_client_secret]" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" />
            <p class="description">
                <?php esc_html_e( 'Your PayPal application Client Secret. Keep this secure!', 'dcmm-membership' ); ?><br>
                <strong><?php esc_html_e( 'Where to find:', 'dcmm-membership' ); ?></strong> 
                <?php esc_html_e( 'In your PayPal app details page, click "Show" next to "Client Secret" and copy the revealed secret.', 'dcmm-membership' ); ?><br>
                <em><?php esc_html_e( 'Note: Never share this secret publicly or commit it to version control.', 'dcmm-membership' ); ?></em>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_paypal_client_secret',
        [
            'type' => 'string',
            'default' => '',
        ]
    );

    // PayPal Webhook ID
    add_settings_field(
        'dcmm_paypal_webhook_id',
        __( 'PayPal Webhook ID', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $webhook_id = isset( $options['dcmm_paypal_webhook_id'] ) ? esc_attr( $options['dcmm_paypal_webhook_id'] ) : '';
            $webhook_url = site_url( '/wp-json/dcmm/v1/paypal-webhook' );
            ?>
            <input type="text" id="dcmm_paypal_webhook_id" name="dcmm_settings[dcmm_paypal_webhook_id]" value="<?php echo esc_attr( $webhook_id ); ?>" class="regular-text" />
            <p class="description">
                <?php esc_html_e( 'Optional: Webhook ID from PayPal for real-time payment notifications.', 'dcmm-membership' ); ?><br>
                <strong><?php esc_html_e( 'Your Webhook URL:', 'dcmm-membership' ); ?></strong> <code><?php echo esc_html( $webhook_url ); ?></code><br><br>
                <strong><?php esc_html_e( 'How to set up webhooks:', 'dcmm-membership' ); ?></strong><br>
                1. <?php esc_html_e( 'In your PayPal app, scroll to "Features" section and click "Add Webhook"', 'dcmm-membership' ); ?><br>
                2. <?php esc_html_e( 'Enter the webhook URL above', 'dcmm-membership' ); ?><br>
                3. <?php esc_html_e( 'Select these event types: "Checkout order approved", "Payment capture completed", "Payment capture denied"', 'dcmm-membership' ); ?><br>
                4. <?php esc_html_e( 'Save the webhook and copy the "Webhook ID" back here', 'dcmm-membership' ); ?><br>
                <em><?php esc_html_e( 'Webhooks provide real-time payment updates but are not required for basic functionality.', 'dcmm-membership' ); ?></em>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_paypal_webhook_id',
        [
            'type' => 'string',
            'default' => '',
        ]
    );

    // Email Settings Section
    add_settings_section(
        'dcmm_email_settings',
        __( 'Email Settings', 'dcmm-membership' ),
        function() {
            ?>
            <section class="dcmm-email-settings-section">
                <p><?php esc_html_e( 'Configure email notifications for member signups and renewals.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-email-merge-tags" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags:', 'dcmm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your email templates:', 'dcmm-membership' ); ?></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{first_name}">{first_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{last_name}">{last_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{full_name}">{full_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{email}">{email}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{membership_start_date}">{membership_start_date}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{membership_status}">{membership_status}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{renewal_date}">{renewal_date}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{site_name}">{site_name}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{site_url}">{site_url}</button>
                        <button type="button" class="button button-small dcmm-merge-tag" data-tag="{payment_details}">{payment_details}</button>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">
                        <strong>Note:</strong> <code>{payment_details}</code> will only show content in renewal emails when payment was made.
                    </p>
                </div>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // Email From Name
    add_settings_field(
        'dcmm_email_from_name',
        __( 'From Name', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $from_name = isset( $options['from_name'] ) ? esc_attr( $options['from_name'] ) : get_bloginfo('name');
            ?>
            <input type="text" id="dcmm_email_from_name" name="dcmm_email_settings[from_name]" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Name that appears in the "From" field of emails.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Email From Email
    add_settings_field(
        'dcmm_email_from_email',
        __( 'From Email', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $from_email = isset( $options['from_email'] ) ? esc_attr( $options['from_email'] ) : get_option('admin_email');
            ?>
            <input type="email" id="dcmm_email_from_email" name="dcmm_email_settings[from_email]" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Email address that appears in the "From" field of emails.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Enable Welcome Emails
    add_settings_field(
        'dcmm_enable_welcome_emails',
        __( 'Welcome Emails', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $checked = isset( $options['enable_welcome_emails'] ) ? (bool) $options['enable_welcome_emails'] : true;
            ?>
            <input type="checkbox" id="dcmm_enable_welcome_emails" name="dcmm_email_settings[enable_welcome_emails]" value="1" <?php checked( $checked ); ?> />
            <label for="dcmm_enable_welcome_emails"><?php esc_html_e( 'Send welcome emails to new members', 'dcmm-membership' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Welcome Email Subject
    add_settings_field(
        'dcmm_welcome_email_subject',
        __( 'Welcome Email Subject', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $subject = isset( $options['welcome_subject'] ) ? esc_attr( $options['welcome_subject'] ) : 'Welcome to Your Membership!';
            ?>
            <input type="text" id="dcmm_welcome_email_subject" name="dcmm_email_settings[welcome_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Subject line for welcome emails.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Welcome Email Template
    add_settings_field(
        'dcmm_welcome_email_template',
        __( 'Welcome Email Template', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $template = isset( $options['welcome_template'] ) ? $options['welcome_template'] : '';
            
            // Settings for the TinyMCE editor
            $editor_settings = array(
                'textarea_name' => 'dcmm_email_settings[welcome_template]',
                'media_buttons' => true,
                'textarea_rows' => 12,
                'teeny' => false,
                'tinymce' => array(
                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,image,|,alignleft,aligncenter,alignright,|,undo,redo',
                    'toolbar2' => 'forecolor,backcolor,|,hr,|,charmap,|,removeformat,|,outdent,indent,|,wp_adv',
                    'toolbar3' => '',
                ),
                'quicktags' => array(
                    'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
                )
            );
            
            ?>
            <div class="dcmm-email-template-editor">
                <?php wp_editor( $template, 'dcmm_welcome_email_template', $editor_settings ); ?>
                <p class="description">
                    <?php esc_html_e( 'HTML template for welcome emails. Leave blank to use default template.', 'dcmm-membership' ); ?><br>
                    <strong><?php esc_html_e( 'Tip:', 'dcmm-membership' ); ?></strong> <?php esc_html_e( 'Use the merge tags listed above to personalize your emails.', 'dcmm-membership' ); ?>
                </p>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Enable Renewal Emails
    add_settings_field(
        'dcmm_enable_renewal_emails',
        __( 'Renewal Emails', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $checked = isset( $options['enable_renewal_emails'] ) ? (bool) $options['enable_renewal_emails'] : true;
            ?>
            <input type="checkbox" id="dcmm_enable_renewal_emails" name="dcmm_email_settings[enable_renewal_emails]" value="1" <?php checked( $checked ); ?> />
            <label for="dcmm_enable_renewal_emails"><?php esc_html_e( 'Send renewal confirmation emails', 'dcmm-membership' ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Renewal Email Subject
    add_settings_field(
        'dcmm_renewal_email_subject',
        __( 'Renewal Email Subject', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $subject = isset( $options['renewal_subject'] ) ? esc_attr( $options['renewal_subject'] ) : 'Membership Renewal Confirmation';
            ?>
            <input type="text" id="dcmm_renewal_email_subject" name="dcmm_email_settings[renewal_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Subject line for renewal emails.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Renewal Email Template
    add_settings_field(
        'dcmm_renewal_email_template',
        __( 'Renewal Email Template', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $template = isset( $options['renewal_template'] ) ? $options['renewal_template'] : '';
            
            // Settings for the TinyMCE editor
            $editor_settings = array(
                'textarea_name' => 'dcmm_email_settings[renewal_template]',
                'media_buttons' => true,
                'textarea_rows' => 12,
                'teeny' => false,
                'tinymce' => array(
                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,image,|,alignleft,aligncenter,alignright,|,undo,redo',
                    'toolbar2' => 'forecolor,backcolor,|,hr,|,charmap,|,removeformat,|,outdent,indent,|,wp_adv',
                    'toolbar3' => '',
                ),
                'quicktags' => array(
                    'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
                )
            );
            
            ?>
            <div class="dcmm-email-template-editor">
                <?php wp_editor( $template, 'dcmm_renewal_email_template', $editor_settings ); ?>
                <p class="description">
                    <?php esc_html_e( 'HTML template for renewal emails. Leave blank to use default template.', 'dcmm-membership' ); ?><br>
                    <strong><?php esc_html_e( 'Tip:', 'dcmm-membership' ); ?></strong> <?php esc_html_e( 'Use the merge tags listed above to personalize your emails.', 'dcmm-membership' ); ?>
                </p>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Register email settings
    register_setting( 'dcmm_settings_group', 'dcmm_email_settings' );

    // Expiration Notification Settings Section
    add_settings_section(
        'dcmm_expiration_notification_settings',
        __( 'Expiration Notification Settings', 'dcmm-membership' ),
        function() {
            ?>
            <section class="dcmm-expiration-notification-section">
                <p><?php esc_html_e( 'Configure automated email notifications for membership expiration reminders.', 'dcmm-membership' ); ?></p>
                <div class="dcmm-expiration-merge-tags" style="background: #f9f9f9; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags for Expiration Emails:', 'dcmm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your expiration email templates:', 'dcmm-membership' ); ?></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{first_name}">{first_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{last_name}">{last_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{full_name}">{full_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{email}">{email}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{membership_status}">{membership_status}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{expiration_date}">{expiration_date}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{days_until_expiration}">{days_until_expiration}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{renewal_url}">{renewal_url}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{site_name}">{site_name}</button>
                        <button type="button" class="button button-small dcmm-expiration-merge-tag" data-tag="{site_url}">{site_url}</button>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">
                        <strong>Note:</strong> <code>{renewal_url}</code> will link to your member dashboard renewal page.
                    </p>
                </div>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // Enable Expiration Notifications
    add_settings_field(
        'dcmm_enable_expiration_notifications',
        __( 'Enable Expiration Notifications', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['enabled'] ) ? (bool) $options['enabled'] : false;
            ?>
            <input type="checkbox" id="dcmm_enable_expiration_notifications" name="dcmm_expiration_notification_settings[enabled]" value="1" <?php checked( $enabled ); ?> />
            <label for="dcmm_enable_expiration_notifications"><?php esc_html_e( 'Send automated expiration reminder emails to members', 'dcmm-membership' ); ?></label>
            <p class="description"><?php esc_html_e( 'When enabled, members will receive automated emails before their membership expires.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );

    // 30 Day Notification
    add_settings_field(
        'dcmm_30_day_notification',
        __( '30 Day Notification', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['30_days']['enabled'] ) ? (bool) $options['notifications']['30_days']['enabled'] : true;
            $subject = isset( $options['notifications']['30_days']['subject'] ) ? esc_attr( $options['notifications']['30_days']['subject'] ) : 'Your membership expires in 30 days';
            $template = isset( $options['notifications']['30_days']['template'] ) ? wp_kses_post( $options['notifications']['30_days']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][30_days][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 30 days before expiration', 'dcmm-membership' ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', 'dcmm-membership' ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][30_days][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', 'dcmm-membership' ); ?></strong></p>
                    <div class="dcmm-email-template-editor">
                        <?php
                        wp_editor( $template, 'dcmm_30_day_template', array(
                            'textarea_name' => 'dcmm_expiration_notification_settings[notifications][30_days][template]',
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                                'toolbar2' => ''
                            )
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );

    // 7 Day Notification
    add_settings_field(
        'dcmm_7_day_notification',
        __( '7 Day Notification', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['7_days']['enabled'] ) ? (bool) $options['notifications']['7_days']['enabled'] : true;
            $subject = isset( $options['notifications']['7_days']['subject'] ) ? esc_attr( $options['notifications']['7_days']['subject'] ) : 'Your membership expires in 7 days';
            $template = isset( $options['notifications']['7_days']['template'] ) ? wp_kses_post( $options['notifications']['7_days']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][7_days][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 7 days before expiration', 'dcmm-membership' ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', 'dcmm-membership' ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][7_days][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', 'dcmm-membership' ); ?></strong></p>
                    <div class="dcmm-email-template-editor">
                        <?php
                        wp_editor( $template, 'dcmm_7_day_template', array(
                            'textarea_name' => 'dcmm_expiration_notification_settings[notifications][7_days][template]',
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                                'toolbar2' => ''
                            )
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );

    // 1 Day Notification
    add_settings_field(
        'dcmm_1_day_notification',
        __( '1 Day Notification', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['1_day']['enabled'] ) ? (bool) $options['notifications']['1_day']['enabled'] : true;
            $subject = isset( $options['notifications']['1_day']['subject'] ) ? esc_attr( $options['notifications']['1_day']['subject'] ) : 'Your membership expires tomorrow';
            $template = isset( $options['notifications']['1_day']['template'] ) ? wp_kses_post( $options['notifications']['1_day']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][1_day][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 1 day before expiration', 'dcmm-membership' ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', 'dcmm-membership' ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][1_day][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', 'dcmm-membership' ); ?></strong></p>
                    <div class="dcmm-email-template-editor">
                        <?php
                        wp_editor( $template, 'dcmm_1_day_template', array(
                            'textarea_name' => 'dcmm_expiration_notification_settings[notifications][1_day][template]',
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                                'toolbar2' => ''
                            )
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );

    // Expired Notification
    add_settings_field(
        'dcmm_expired_notification',
        __( 'Expired Notification', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['expired']['enabled'] ) ? (bool) $options['notifications']['expired']['enabled'] : true;
            $subject = isset( $options['notifications']['expired']['subject'] ) ? esc_attr( $options['notifications']['expired']['subject'] ) : 'Your membership has expired';
            $template = isset( $options['notifications']['expired']['template'] ) ? wp_kses_post( $options['notifications']['expired']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][expired][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send on expiration day', 'dcmm-membership' ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', 'dcmm-membership' ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][expired][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', 'dcmm-membership' ); ?></strong></p>
                    <div class="dcmm-email-template-editor">
                        <?php
                        wp_editor( $template, 'dcmm_expired_template', array(
                            'textarea_name' => 'dcmm_expiration_notification_settings[notifications][expired][template]',
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                                'toolbar2' => ''
                            )
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );


    // Register expiration notification settings
    register_setting( 'dcmm_settings_group', 'dcmm_expiration_notification_settings' );

    // My Account Page setting
    add_settings_field(
        'dcmm_my_account_page',
        __( 'My Account Page', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $selected_page = isset( $options['dcmm_my_account_page'] ) ? $options['dcmm_my_account_page'] : '';
            wp_dropdown_pages( array(
                'name' => 'dcmm_settings[dcmm_my_account_page]',
                'id' => 'dcmm_my_account_page',
                'selected' => $selected_page,
                'show_option_none' => 'Select a page...',
                'option_none_value' => ''
            ) );
            ?>
            <p class="description"><?php esc_html_e( 'Select the page that contains your member dashboard shortcode. This will be used for renewal links in expiration emails.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    // Add JavaScript to show/hide the specific date field based on the join policy selection
    add_action( 'admin_footer', function() {
        // Only run this JavaScript on the settings page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'dcmm_settings' ) {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to toggle the dues amount field based on the dues enabled checkbox
                function toggleDuesAmountField() {
                    var amountRow = $('#dcmm_dues_amount').closest('tr');
                    var duesEnabledElement = document.getElementById('dcmm_enable_dues');
                    if (duesEnabledElement) {
                        var duesEnabled = duesEnabledElement.checked;
                        if (duesEnabled) {
                            amountRow.show();
                            console.debug('on');
                        } else {
                            amountRow.hide();
                            console.debug('off');
                        }
                    }
                }

                // Function to toggle the specific date field based on the join policy selection
                function toggleSpecificDateField() {
                    var dateRow = $('#dcmm_anchor_date').closest('tr');
                    var joinPolicy = $('#dcmm_join_policy').val();
                    if (joinPolicy === 'anchored_full_term') {
                        dateRow.show();
                    } else {
                        dateRow.hide();
                    }
                }

                // Function to update anchor date field based on membership term length
                function updateAnchorDateField() {
                    var termLength = $('#dcmm_membership_term_length').val();
                    var anchorInput = $('#dcmm_anchor_date');
                    var description = $('#dcmm-anchor-date-description');
                    
                    if (termLength === 'monthly') {
                        anchorInput.attr('placeholder', '15');
                        description.html('<?php esc_html_e( "Enter the day of the month (1-31) when memberships expire. Example: \"15\" for the 15th of each month.", "dcmm-membership" ); ?>');
                    } else {
                        anchorInput.attr('placeholder', '08-15');
                        description.html('<?php esc_html_e( "Enter the month and day (MM-DD format) when memberships expire. Example: \"08-15\" for August 15th each year.", "dcmm-membership" ); ?>');
                    }
                }

                // Initial check
                toggleDuesAmountField();
                toggleSpecificDateField();
                updateAnchorDateField();

                // Bind change event
                $('#dcmm_enable_dues').change(function() {
                    toggleDuesAmountField();
                });

                $('#dcmm_join_policy').change(function() {
                    toggleSpecificDateField();
                });

                $('#dcmm_membership_term_length').change(function() {
                    updateAnchorDateField();
                });

                // Handle merge tag button clicks
                $('.dcmm-merge-tag, .dcmm-expiration-merge-tag').on('click', function(e) {
                    e.preventDefault();
                    var tag = $(this).data('tag');
                    
                    // Try to insert into the active TinyMCE editor
                    if (typeof tinymce !== 'undefined') {
                        var activeEditor = tinymce.activeEditor;
                        if (activeEditor && !activeEditor.isHidden()) {
                            activeEditor.execCommand('mceInsertContent', false, tag);
                            return;
                        }
                    }
                    
                    // Fallback: find the last focused textarea
                    var $activeTextarea = $('.dcmm-email-template-editor textarea:focus, .dcmm-email-template-editor textarea').last();
                    if ($activeTextarea.length) {
                        var textarea = $activeTextarea[0];
                        var startPos = textarea.selectionStart;
                        var endPos = textarea.selectionEnd;
                        var textValue = textarea.value;
                        
                        textarea.value = textValue.substring(0, startPos) + tag + textValue.substring(endPos);
                        textarea.selectionStart = textarea.selectionEnd = startPos + tag.length;
                        textarea.focus();
                    }
                });
            });
        </script>
        
        <style>
        .dcmm-email-template-editor {
            margin-bottom: 20px;
        }
        
        .dcmm-merge-tag, .dcmm-expiration-merge-tag {
            font-family: monospace;
            font-size: 11px;
            margin: 2px;
            white-space: nowrap;
        }
        
        .dcmm-merge-tag:hover, .dcmm-expiration-merge-tag:hover {
            background-color: #0073aa;
            color: white;
        }
        
        .dcmm-expiration-merge-tag {
            background-color: #f0f8f0;
            border-color: #28a745;
        }
        
        .dcmm-expiration-merge-tag:hover {
            background-color: #28a745;
            color: white;
        }
        
        .dcmm-email-merge-tags {
            border-radius: 4px;
        }
        
        .dcmm-notification-field {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .dcmm-notification-field label {
            font-weight: bold;
            font-size: 14px;
        }
        
        .dcmm-notification-details {
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .dcmm-expiration-notification-section {
            margin-bottom: 20px;
        }
        
        .dcmm-expiration-merge-tags {
            border-radius: 4px;
        }
        </style>
        <?php
    });

    // if membership period is specific date, add a date field; but only have it show if the membership period is set to specific date
    // the date field will be hidden by default and shown only when the specific date option is selected, dynamically using JavaScript
    add_settings_field(
        'dcmm_anchor_date',
        __( 'Anchor Date', 'dcmm-membership' ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $anchor_date = isset( $options['dcmm_anchor_date'] ) ? esc_attr( $options['dcmm_anchor_date'] ) : '';
            $term_length = isset( $options['dcmm_membership_term_length'] ) ? esc_attr( $options['dcmm_membership_term_length'] ) : 'monthly';
            ?>
            <div class="dcmm-anchor-date-field">
                <input type="text" id="dcmm_anchor_date" name="dcmm_settings[dcmm_anchor_date]" value="<?php echo esc_attr( $anchor_date ); ?>" placeholder="<?php echo $term_length === 'monthly' ? '15' : '08-15'; ?>" />
                <div class="description">
                    <p id="dcmm-anchor-date-description">
                        <?php if ( $term_length === 'monthly' ): ?>
                            <?php esc_html_e( 'Enter the day of the month (1-31) when memberships expire. Example: "15" for the 15th of each month.', 'dcmm-membership' ); ?>
                        <?php else: ?>
                            <?php esc_html_e( 'Enter the month and day (MM-DD format) when memberships expire. Example: "08-15" for August 15th each year.', 'dcmm-membership' ); ?>
                        <?php endif; ?>
                    </p>
                    <p><strong><?php esc_html_e( 'Note:', 'dcmm-membership' ); ?></strong> <?php esc_html_e( 'When members renew, their expiration will be extended by the full membership duration from their current expiration date.', 'dcmm-membership' ); ?></p>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_anchor_date',
        [
            'type' => 'string',
            'default' => '',
        ]
    );    
}
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );

/**
 * Get the settings option.
 * 
 * If a specific setting is requested, return that setting; otherwise, return the entire settings array.
 * 
 * @since 1.1.0
 * @return array Settings array | specific setting value | null
 * 
 */
function get_settings( $setting = null ) {

    $settings = get_option( 'dcmm_settings', [] );

    if ( is_null( $setting ) ) {
        return $settings;
    }

    return isset( $settings[ $setting ] ) ? $settings[ $setting ] : null;
}

/**
 * Find out if dues are enabled.
 * 
 * @since 1.1.0
 * @return bool True if dues are enabled, false otherwise.
 * 
 */
function are_dues_enabled() {
    
    $settings = get_settings();
    return ! empty( $settings['dcmm_enable_dues'] );
}

/**
 * Get the dues amount.
 * 
 * @since 1.1.0
 * @return string Dues amount or false if not set.
 * 
 */
function get_dues_amount() {
 
    $settings = get_settings();
    return isset( $settings['dcmm_dues_amount'] ) ? $settings['dcmm_dues_amount'] : false;

}

/**
 * Get the membership period.
 * 
 * @since 1.1.0
 * @return string Membership period (yearly or monthly).
 * 
 */
function get_membership_period() {
    $settings = get_settings();
    return isset( $settings['dcmm_membership_term_length'] ) ? $settings['dcmm_membership_term_length'] : 'monthly';
}

/**
 * Get PayPal environment setting.
 * 
 * @since 1.1.0
 * @return string 'sandbox' or 'live'
 */
function get_paypal_environment() {
    $settings = get_settings();
    return isset( $settings['dcmm_paypal_environment'] ) ? $settings['dcmm_paypal_environment'] : 'sandbox';
}

/**
 * Get PayPal Client ID.
 * 
 * @since 1.1.0
 * @return string PayPal Client ID or empty string
 */
function get_paypal_client_id() {
    $settings = get_settings();
    return isset( $settings['dcmm_paypal_client_id'] ) ? $settings['dcmm_paypal_client_id'] : '';
}

/**
 * Get PayPal Client Secret.
 * 
 * @since 1.1.0
 * @return string PayPal Client Secret or empty string
 */
function get_paypal_client_secret() {
    $settings = get_settings();
    return isset( $settings['dcmm_paypal_client_secret'] ) ? $settings['dcmm_paypal_client_secret'] : '';
}

/**
 * Get PayPal Webhook ID.
 * 
 * @since 1.1.0
 * @return string PayPal Webhook ID or empty string
 */
function get_paypal_webhook_id() {
    $settings = get_settings();
    return isset( $settings['dcmm_paypal_webhook_id'] ) ? $settings['dcmm_paypal_webhook_id'] : '';
}

/**
 * Check if PayPal is properly configured.
 * 
 * @since 1.1.0
 * @return bool True if PayPal has required settings configured
 */
function is_paypal_configured() {
    $client_id = get_paypal_client_id();
    $client_secret = get_paypal_client_secret();
    
    return ! empty( $client_id ) && ! empty( $client_secret );
}

/**
 * Get PayPal API base URL based on environment.
 * 
 * @since 1.1.0
 * @return string PayPal API base URL
 */
function get_paypal_api_base_url() {
    $environment = get_paypal_environment();
    
    if ( $environment === 'live' ) {
        return 'https://api-m.paypal.com';
    }
    
    return 'https://api-m.sandbox.paypal.com';
}

/**
 * Get renewal window days setting.
 * 
 * @since 1.1.0
 * @return int Number of days before expiration that renewal becomes available
 */
function get_renewal_window_days() {
    $settings = get_settings();
    return isset( $settings['renewal_window_days'] ) ? intval( $settings['renewal_window_days'] ) : 30;
}

/**
 * Get grace period days setting.
 * 
 * @since 1.1.0
 * @return int Number of days after expiration that renewal is still allowed
 */
function get_grace_period_days() {
    $settings = get_settings();
    return isset( $settings['grace_period_days'] ) ? intval( $settings['grace_period_days'] ) : 30;
}

/**
 * Get renewal notice days setting.
 * 
 * @since 1.1.0
 * @return int Number of days before renewal window opens to show notice
 */
function get_renewal_notice_days() {
    $settings = get_settings();
    return isset( $settings['renewal_notice_days'] ) ? intval( $settings['renewal_notice_days'] ) : 7;
}

/**
 * Check if offline payments are enabled.
 * 
 * @since 1.1.0
 * @return bool True if offline payments are enabled, false otherwise.
 */
function are_offline_payments_enabled() {
    $settings = get_settings();
    return ! empty( $settings['dcmm_enable_offline_payments'] );
}

/**
 * Get offline payment methods.
 * 
 * @since 1.1.0
 * @return array Available offline payment methods.
 */
function get_offline_payment_methods() {
    $settings = get_settings();
    $default_methods = array(
        'cash' => 'Cash',
        'check' => 'Check',
        'transfer' => 'Bank Transfer',
        'other' => 'Other'
    );
    
    return isset( $settings['dcmm_offline_payment_methods'] ) ? $settings['dcmm_offline_payment_methods'] : $default_methods;
}

/**
 * Check if reference numbers are required for offline payments.
 * 
 * @since 1.1.0
 * @return bool True if reference numbers are required, false otherwise.
 */
function is_offline_reference_required() {
    $settings = get_settings();
    return ! empty( $settings['dcmm_offline_require_reference'] );
}