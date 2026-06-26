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
        __( 'Settings', DCMM_PLUGIN_SLUG ), // Page title
        __( 'Settings', DCMM_PLUGIN_SLUG ), // Menu title
        'manage_dcmm_settings', // Capability
        'dcmm_settings', // Menu slug
        __NAMESPACE__ . '\settings_page_callback' // Callback function
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_page' );

/**
 * Enqueue CSS and JS for the settings page.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function enqueue_admin_assets( $hook_suffix ) {
    // Only load on the plugin's own settings page (dcmm-member_page_dcmm_settings).
    if ( 'dcmm-member_page_dcmm_settings' !== $hook_suffix ) {
        return;
    }

    // member-admin.css is already enqueued globally by class-member-metaboxes.php
    // (handle: dcmm_admin_styles). Only the settings-page JS needs to be loaded here.

    wp_enqueue_script(
        'dcmm-admin-settings',
        DCMM_URL . 'js/admin-settings.js',
        array( 'jquery' ),
        DCMM_VERSION,
        true
    );

    wp_localize_script(
        'dcmm-admin-settings',
        'dcmmSettings',
        array(
            'anchorDateMonthly' => __( 'Enter the day of the month (1-31) when memberships expire. Example: "15" for the 15th of each month.', 'dcmm-membership' ),
            'anchorDateYearly'  => __( 'Enter the month and day (MM-DD format) when memberships expire. Example: "08-15" for August 15th each year.', 'dcmm-membership' ),
            'previewNonce'      => wp_create_nonce( 'dcmm_preview_email' ),
            'previewLabel'      => __( 'Preview Email', 'dcmm-membership' ),
            'previewLoading'    => __( 'Loading…', 'dcmm-membership' ),
            'previewError'      => __( 'Preview failed. Please try again.', 'dcmm-membership' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_assets' );

/**
 * Callback function for the settings page.
 * 
 * @since 1.1.0
 */
function settings_page_callback() {
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

    $tabs = array(
        'general'  => __( 'General', 'dcmm-membership' ),
        'payments' => __( 'Payment Gateways', 'dcmm-membership' ),
        'emails'   => __( 'Email & Notifications', 'dcmm-membership' ),
        'premium'  => __( 'Premium Features', 'dcmm-membership' ),
    );

    if ( ! array_key_exists( $current_tab, $tabs ) ) {
        $current_tab = 'general';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Membership Management Settings', 'dcmm-membership' ); ?></h1>
        <?php settings_errors(); ?>

        <nav class="nav-tab-wrapper dcmm-settings-nav" aria-label="<?php esc_attr_e( 'Settings tabs', 'dcmm-membership' ); ?>">
            <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, admin_url( 'edit.php?post_type=dcmm-member&page=dcmm_settings' ) ) ); ?>"
                   class="nav-tab<?php echo $tab_key === $current_tab ? ' nav-tab-active' : ''; ?>"
                   data-tab="<?php echo esc_attr( $tab_key ); ?>"
                   aria-selected="<?php echo $tab_key === $current_tab ? 'true' : 'false'; ?>"
                   aria-controls="dcmm-tab-<?php echo esc_attr( $tab_key ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="dcmm-tab-content">

            <?php // Tabs 1-3 share a single form so all options are submitted together, ?>
            <?php // eliminating the hidden-field workarounds and data-loss between tabs. ?>
            <form method="post" action="options.php" id="dcmm-settings-form">
                <?php settings_fields( 'dcmm_settings_group' ); ?>

                <div id="dcmm-tab-general" class="dcmm-tab-panel" role="tabpanel" data-tab="general"<?php echo $current_tab !== 'general' ? ' style="display:none"' : ''; ?>>
                    <?php render_general_tab(); ?>
                    <?php submit_button( __( 'Save Settings', 'dcmm-membership' ) ); ?>
                </div>

                <div id="dcmm-tab-payments" class="dcmm-tab-panel" role="tabpanel" data-tab="payments"<?php echo $current_tab !== 'payments' ? ' style="display:none"' : ''; ?>>
                    <?php render_payments_tab(); ?>
                    <?php submit_button( __( 'Save Settings', 'dcmm-membership' ) ); ?>
                </div>

                <div id="dcmm-tab-emails" class="dcmm-tab-panel" role="tabpanel" data-tab="emails"<?php echo $current_tab !== 'emails' ? ' style="display:none"' : ''; ?>>
                    <?php render_emails_tab(); ?>
                    <?php submit_button( __( 'Save Settings', 'dcmm-membership' ) ); ?>
                </div>
            </form>

            <?php // Premium tab has its own AJAX and admin-post forms and stays independent. ?>
            <div id="dcmm-tab-premium" class="dcmm-tab-panel" role="tabpanel" data-tab="premium"<?php echo $current_tab !== 'premium' ? ' style="display:none"' : ''; ?>>
                <?php render_premium_tab(); ?>
            </div>

        </div>
    </div>
    <?php
}

/**
 * Render General tab content
 */
function render_general_tab() {
    ?>
    <h2><?php esc_html_e( 'Membership Settings', 'dcmm-membership' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'dcmm_settings_group', 'dcmm_membership_settings' ); ?>
    </table>
    <?php
}

/**
 * Render Payment Gateways tab content
 */
function render_payments_tab() {
    ?>
    <h2><?php esc_html_e( 'Offline Payment Settings', 'dcmm-membership' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'dcmm_settings_group', 'dcmm_offline_payment_settings' ); ?>
    </table>

    <h2><?php esc_html_e( 'PayPal Settings', 'dcmm-membership' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'dcmm_settings_group', 'dcmm_paypal_settings' ); ?>
    </table>
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
    <?php
}

/**
 * Render Email & Notifications tab content
 */
function render_emails_tab() {
    ?>
    <h2><?php esc_html_e( 'Email Settings', 'dcmm-membership' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'dcmm_settings_group', 'dcmm_email_settings' ); ?>
    </table>

    <h2><?php esc_html_e( 'Expiration Notification Settings', 'dcmm-membership' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'dcmm_settings_group', 'dcmm_expiration_notification_settings' ); ?>
    </table>
    <?php
}

/**
 * Handle premium settings form submission
 */
function handle_premium_settings_save() {
    // Check nonce and permissions
    if (!wp_verify_nonce($_POST['dcmm_premium_nonce'], 'dcmm_premium_settings') || !current_user_can('manage_dcmm_settings')) {
        wp_die(__('Security check failed.', DCMM_PLUGIN_SLUG ));
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
    if (!wp_verify_nonce($_POST['nonce'], 'dcmm_mailchimp_api') || !current_user_can('manage_dcmm_settings')) {
        wp_send_json_error(array('message' => __('Security check failed.', DCMM_PLUGIN_SLUG )));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('API key cannot be empty.', DCMM_PLUGIN_SLUG )));
        return;
    }

    require_once( DCMM_PATH . 'includes/premium/integrations/mailchimp/class-mailchimp-api.php' );
    
    // Save the API key
    update_option('dcmm_mailchimp_api_key', $api_key);
    
    // Clear any cached lists
    \DCMM\MailChimp\MailChimp_API::clear_lists_cache();
    
    wp_send_json_success(array('message' => __('MailChimp API key saved successfully!', DCMM_PLUGIN_SLUG )));
}
add_action('wp_ajax_dcmm_save_mailchimp_api_key', __NAMESPACE__ . '\handle_mailchimp_api_save');

/**
 * Handle MailChimp connection test via AJAX
 */
function handle_mailchimp_connection_test() {
    // Check nonce and permissions
    if (!wp_verify_nonce($_POST['nonce'], 'dcmm_mailchimp_api') || !current_user_can('manage_dcmm_settings')) {
        wp_send_json_error(array('message' => __('Security check failed.', DCMM_PLUGIN_SLUG )));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('API key cannot be empty.', DCMM_PLUGIN_SLUG )));
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
    } catch ( \Exception $e ) {
        wp_send_json_error(array('message' => __('Connection test failed: ', DCMM_PLUGIN_SLUG ) . $e->getMessage()));
    }
}
add_action('wp_ajax_dcmm_test_mailchimp_connection', __NAMESPACE__ . '\handle_mailchimp_connection_test');

/**
 * Render a preview of an email template and return the HTML via AJAX.
 *
 * Accepts an optional `template` parameter containing the current (possibly
 * unsaved) editor content so admins can preview without saving first.
 */
function handle_preview_email() {
    check_ajax_referer( 'dcmm_preview_email', 'nonce' );

    if ( ! current_user_can( 'manage_dcmm_settings' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dcmm-membership' ) ) );
    }

    $valid_types   = array( 'welcome', 'renewal', '30_days', '7_days', '1_day', 'expired' );
    $email_type    = isset( $_POST['email_type'] ) ? sanitize_text_field( $_POST['email_type'] ) : '';
    $template_override = isset( $_POST['template'] ) ? wp_kses_post( wp_unslash( $_POST['template'] ) ) : '';

    if ( ! in_array( $email_type, $valid_types, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid email type.', 'dcmm-membership' ) ) );
    }

    $email_handler = \DCMM_Email_Handler::get_instance();
    $html          = $email_handler->get_preview_html( $email_type, $template_override );

    // Resolve subject from saved settings for display in the preview window.
    if ( in_array( $email_type, array( 'welcome', 'renewal' ), true ) ) {
        $settings = get_option( 'dcmm_email_settings', array() );
        $defaults = array(
            'welcome' => __( 'Welcome to Your Membership!', 'dcmm-membership' ),
            'renewal' => __( 'Membership Renewal Confirmation', 'dcmm-membership' ),
        );
        $subject = ! empty( $settings[ $email_type . '_subject' ] )
            ? $settings[ $email_type . '_subject' ]
            : $defaults[ $email_type ];
    } else {
        $settings = get_option( 'dcmm_expiration_notification_settings', array() );
        $defaults = array(
            '30_days' => __( 'Your membership expires in 30 days', 'dcmm-membership' ),
            '7_days'  => __( 'Your membership expires in 7 days', 'dcmm-membership' ),
            '1_day'   => __( 'Your membership expires tomorrow', 'dcmm-membership' ),
            'expired' => __( 'Your membership has expired', 'dcmm-membership' ),
        );
        $subject = ! empty( $settings['notifications'][ $email_type ]['subject'] )
            ? $settings['notifications'][ $email_type ]['subject']
            : ( isset( $defaults[ $email_type ] ) ? $defaults[ $email_type ] : '' );
    }

    wp_send_json_success( array(
        'html'    => $html,
        'subject' => esc_html( $subject ),
    ) );
}
add_action( 'wp_ajax_dcmm_preview_email', __NAMESPACE__ . '\handle_preview_email' );

/**
 * Register settings for the Membership Management plugin.
 * 
 * @since 1.1.0
 */
function register_settings() {
    register_setting( 'dcmm_settings_group', 'dcmm_settings', array(
        'capability' => 'manage_dcmm_settings'
    ) );

    add_settings_section(
        'dcmm_membership_settings',
        __( 'Membership Settings', DCMM_PLUGIN_SLUG ),
        null,
        'dcmm_settings_group'
    );

    // Enable dues
    add_settings_field(
        'dcmm_enable_dues',
        __( 'Charge Dues', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $checked = isset( $options['dcmm_enable_dues'] ) ? (bool) $options['dcmm_enable_dues'] : false;
            ?>
            <input type="checkbox" id="dcmm_enable_dues" name="dcmm_settings[dcmm_enable_dues]" value="1" <?php checked( $checked ); ?> />
            <label for="dcmm_enable_dues"><?php esc_html_e( 'Is there a charge/fee/cost to being a member?', DCMM_PLUGIN_SLUG ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );


    // Dues amount setting
    add_settings_field(
        'dcmm_dues_amount',
        __( '', DCMM_PLUGIN_SLUG ),
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
        array(
            'capability' => 'manage_dcmm_settings',
            'type' => 'string',
            'default' => '',
        )
    );

    // Membership term length
    add_settings_field(
        'dcmm_membership_term_length',
        __( 'Membership Duration', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $membership_period = isset( $options['dcmm_membership_term_length'] ) ? esc_attr( $options['dcmm_membership_term_length'] ) : '';
            ?>
            <select id="dcmm_membership_term_length" name="dcmm_settings[dcmm_membership_term_length]">
                <option value="yearly" <?php selected( $membership_period, 'yearly' ); ?>><?php esc_html_e( 'Yearly', DCMM_PLUGIN_SLUG ); ?></option>
                <option value="seasonal" <?php selected( $membership_period, 'seasonal' ); ?>><?php esc_html_e( 'Seasonal', DCMM_PLUGIN_SLUG ); ?></option>
                <option value="monthly" <?php selected( $membership_period, 'monthly' ); ?>><?php esc_html_e( 'Monthly', DCMM_PLUGIN_SLUG ); ?></option>
            </select>
            <label for="dcmm_membership_term_length"><?php esc_html_e( 'Select the membership period', DCMM_PLUGIN_SLUG ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    // Renewal Window Settings
    add_settings_field(
        'dcmm_renewal_window_settings',
        __( 'Renewal Window Settings', DCMM_PLUGIN_SLUG ),
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
                            <label for="renewal_window_days"><?php esc_html_e( 'Renewal Window (days)', DCMM_PLUGIN_SLUG ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="renewal_window_days" name="dcmm_settings[renewal_window_days]" value="<?php echo esc_attr( $renewal_window_days ); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e( 'Number of days before expiration that renewal becomes available. Default: 30 days.', DCMM_PLUGIN_SLUG ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grace_period_days"><?php esc_html_e( 'Grace Period (days)', DCMM_PLUGIN_SLUG ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="grace_period_days" name="dcmm_settings[grace_period_days]" value="<?php echo esc_attr( $grace_period_days ); ?>" min="0" max="365" />
                            <p class="description"><?php esc_html_e( 'Number of days after expiration that renewal is still allowed. Default: 30 days.', DCMM_PLUGIN_SLUG ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="renewal_notice_days"><?php esc_html_e( 'Renewal Notice (days)', DCMM_PLUGIN_SLUG ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="renewal_notice_days" name="dcmm_settings[renewal_notice_days]" value="<?php echo esc_attr( $renewal_notice_days ); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e( 'Show renewal notice X days before renewal window opens. Default: 7 days.', DCMM_PLUGIN_SLUG ); ?></p>
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
            'capability' => 'manage_dcmm_settings',
            'type' => 'string',
            'default' => 'monthly',
        ]
    );

    // Does the membership period start/end with the calendar or does it start on a specific date?
    add_settings_field(
        'dcmm_join_policy',
        __( 'Membership Start Model / Join Policy', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $membership_start_end = isset( $options['dcmm_join_policy'] ) ? esc_attr( $options['dcmm_join_policy'] ) : '';
            ?>
            <select id="dcmm_join_policy" name="dcmm_settings[dcmm_join_policy]">
                <option value="fixed_term" <?php selected( $membership_start_end, 'fixed_term' ); ?>><?php esc_html_e( 'Fixed Calendar Term', DCMM_PLUGIN_SLUG ); ?></option>
                <option value="rolling" <?php selected( $membership_start_end, 'rolling' ); ?>><?php esc_html_e( 'Rolling (Anniversary-based)', DCMM_PLUGIN_SLUG ); ?></option>
                <option value="anchored_full_term" <?php selected( $membership_start_end, 'anchored_full_term' ); ?>><?php esc_html_e( 'Anchored Full-Term', DCMM_PLUGIN_SLUG ); ?></option>
            </select>
            <label for="dcmm_join_policy"><?php esc_html_e( 'This determines when the membership begins and how renewal is calculated.', DCMM_PLUGIN_SLUG ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_join_policy',
        [
            'capability' => 'manage_dcmm_settings',
            'type' => 'string',
            'default' => 'rolling',
        ]
    );

    // Register renewal window settings
    register_setting(
        'dcmm_settings_group',
        'dcmm_renewal_window_days',
        [
            'capability' => 'manage_dcmm_settings',
            'type' => 'integer',
            'default' => 30,
        ]
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_grace_period_days',
        [
            'capability' => 'manage_dcmm_settings',
            'type' => 'integer',
            'default' => 30,
        ]
    );

    register_setting(
        'dcmm_settings_group',
        'dcmm_renewal_notice_days',
        [
            'capability' => 'manage_dcmm_settings',
            'type' => 'integer',
            'default' => 7,
        ]
    );

    // Register the auto-sync setting
    register_setting(
        'dcmm_settings_group',
        'dcmm_settings',
        [
            'type' => 'array',
            'sanitize_callback' => function( $value ) {
                // Ensure auto_sync_wp_users is properly handled as boolean
                if ( isset( $value['auto_sync_wp_users'] ) ) {
                    $value['auto_sync_wp_users'] = (bool) $value['auto_sync_wp_users'];
                } else {
                    // If checkbox is unchecked, it won't be in $_POST, so set to false
                    $value['auto_sync_wp_users'] = false;
                }
                return $value;
            }
        ]
    );

    // Auto-sync to WordPress Users
    add_settings_field(
        'auto_sync_wp_users',
        __( 'Auto-sync to WordPress Users', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings', array() );
            $auto_sync_enabled = isset( $options['auto_sync_wp_users'] ) ? (bool) $options['auto_sync_wp_users'] : true;
            ?>
            <label for="auto_sync_wp_users">
                <input type="checkbox" id="auto_sync_wp_users" name="dcmm_settings[auto_sync_wp_users]" value="1" <?php checked( $auto_sync_enabled, true ); ?> />
                <?php esc_html_e( 'Automatically sync member name and email changes to WordPress user accounts', DCMM_PLUGIN_SLUG ); ?>
            </label>
            <p class="description">
                <?php esc_html_e( 'When enabled, changes to member first name, last name, and email will automatically update the associated WordPress user account.', DCMM_PLUGIN_SLUG ); ?>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_membership_settings'
    );


    // Offline Payment Settings Section
    add_settings_section(
        'dcmm_offline_payment_settings',
        __( 'Offline Payment Settings', DCMM_PLUGIN_SLUG ),
        function() {
            ?>
            <section class="dcmm-offline-payment-settings-section">
                <p><?php esc_html_e( 'Configure offline payment options for manual payment recording by administrators.', DCMM_PLUGIN_SLUG ); ?></p>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // Enable Offline Payments
    add_settings_field(
        'dcmm_enable_offline_payments',
        __( 'Enable Offline Payments', DCMM_PLUGIN_SLUG ),
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
        __( 'Payment Methods', DCMM_PLUGIN_SLUG ),
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
                <p><?php esc_html_e( 'Configure available payment methods for offline payments:', DCMM_PLUGIN_SLUG ); ?></p>
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
        __( 'Reference Numbers', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $required = isset( $options['dcmm_offline_require_reference'] ) ? (bool) $options['dcmm_offline_require_reference'] : false;
            ?>
            <input type="checkbox" id="dcmm_offline_require_reference" name="dcmm_settings[dcmm_offline_require_reference]" value="1" <?php checked( $required ); ?> />
            <label for="dcmm_offline_require_reference"><?php esc_html_e( 'Require reference numbers for offline payments', DCMM_PLUGIN_SLUG ); ?></label>
            <p class="description"><?php esc_html_e( 'When enabled, administrators must enter a reference number (check number, transaction ID, etc.) for each offline payment.', 'dcmm-membership' ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_offline_payment_settings'
    );


    // PayPal Settings Section
    add_settings_section(
        'dcmm_paypal_settings',
        __( 'PayPal Settings', DCMM_PLUGIN_SLUG ),
        function() {
            ?>
            <section class="dcmm-paypal-settings-section">
                <p><?php esc_html_e( 'Configure PayPal payment processing for membership dues.', DCMM_PLUGIN_SLUG ); ?></p>
                <div class="dcmm-paypal-setup-instructions" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'PayPal Setup Instructions:', DCMM_PLUGIN_SLUG ); ?></h4>
                    <ol>
                        <li>
                            <strong><?php esc_html_e( 'Create a PayPal Developer Account:', DCMM_PLUGIN_SLUG ); ?></strong><br>
                            <?php esc_html_e( 'Visit', DCMM_PLUGIN_SLUG ); ?> <a href="https://developer.paypal.com/" target="_blank">https://developer.paypal.com/</a> <?php esc_html_e( 'and sign in with your PayPal account.', DCMM_PLUGIN_SLUG ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Create an Application:', DCMM_PLUGIN_SLUG ); ?></strong><br>
                            <?php esc_html_e( 'Go to', DCMM_PLUGIN_SLUG ); ?> <a href="https://developer.paypal.com/developer/applications/" target="_blank"><?php esc_html_e( 'My Apps & Credentials', DCMM_PLUGIN_SLUG ); ?></a> <?php esc_html_e( 'and click "Create App".', DCMM_PLUGIN_SLUG ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Configure Your App:', DCMM_PLUGIN_SLUG ); ?></strong><br>
                            <?php esc_html_e( 'Choose "Default Application" and select your business account. Make sure to enable "Accept payments" feature.', DCMM_PLUGIN_SLUG ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Copy Credentials:', DCMM_PLUGIN_SLUG ); ?></strong><br>
                            <?php esc_html_e( 'Copy the Client ID and Client Secret from your app details below.', DCMM_PLUGIN_SLUG ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Set Up Webhooks (Optional):', DCMM_PLUGIN_SLUG ); ?></strong><br>
                            <?php esc_html_e( 'For real-time payment notifications, configure webhooks in your PayPal app using the webhook URL shown below.', DCMM_PLUGIN_SLUG ); ?>
                        </li>
                    </ol>
                    <p><em><?php esc_html_e( 'Start with Sandbox environment for testing, then switch to Live when ready for production.', DCMM_PLUGIN_SLUG ); ?></em></p>
                </div>
            </section>
            <?php
        },
        'dcmm_settings_group'
    );

    // PayPal Environment (Sandbox vs Live)
    add_settings_field(
        'dcmm_paypal_environment',
        __( 'PayPal Environment', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $environment = isset( $options['dcmm_paypal_environment'] ) ? esc_attr( $options['dcmm_paypal_environment'] ) : 'sandbox';
            ?>
            <select id="dcmm_paypal_environment" name="dcmm_settings[dcmm_paypal_environment]">
                <option value="sandbox" <?php selected( $environment, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Testing)', DCMM_PLUGIN_SLUG ); ?></option>
                <option value="live" <?php selected( $environment, 'live' ); ?>><?php esc_html_e( 'Live (Production)', DCMM_PLUGIN_SLUG ); ?></option>
            </select>
            <p class="description">
                <?php esc_html_e( 'Use Sandbox for testing, Live for production payments.', DCMM_PLUGIN_SLUG ); ?><br>
                <strong><?php esc_html_e( 'Important:', DCMM_PLUGIN_SLUG ); ?></strong> 
                <?php esc_html_e( 'Sandbox and Live environments use different credentials. Make sure your Client ID and Secret match the selected environment.', DCMM_PLUGIN_SLUG ); ?>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting('dcmm_settings_group', 'dcmm_paypal_environment', ['capability' => 'manage_dcmm_settings', 'type' => 'string',
            'default' => 'sandbox',
        ]);

    // PayPal Client ID
    add_settings_field(
        'dcmm_paypal_client_id',
        __( 'PayPal Client ID', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $client_id = isset( $options['dcmm_paypal_client_id'] ) ? esc_attr( $options['dcmm_paypal_client_id'] ) : '';
            ?>
            <input type="text" id="dcmm_paypal_client_id" name="dcmm_settings[dcmm_paypal_client_id]" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" />
            <p class="description">
                <?php esc_html_e( 'Your PayPal application Client ID from the PayPal Developer Dashboard.', DCMM_PLUGIN_SLUG ); ?><br>
                <strong><?php esc_html_e( 'Where to find:', DCMM_PLUGIN_SLUG ); ?></strong> 
                <?php esc_html_e( 'Log in to', DCMM_PLUGIN_SLUG ); ?> <a href="https://developer.paypal.com/developer/applications/" target="_blank"><?php esc_html_e( 'PayPal Developer Dashboard', DCMM_PLUGIN_SLUG ); ?></a>, 
                <?php esc_html_e( 'click on your app, and copy the "Client ID" from the app details page.', DCMM_PLUGIN_SLUG ); ?>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting('dcmm_settings_group', 'dcmm_paypal_client_id', ['capability' => 'manage_dcmm_settings', 'type' => 'string',
            'default' => '',
        ]);

    // PayPal Client Secret
    add_settings_field(
        'dcmm_paypal_client_secret',
        __( 'PayPal Client Secret', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_settings' );
            $client_secret = isset( $options['dcmm_paypal_client_secret'] ) ? esc_attr( $options['dcmm_paypal_client_secret'] ) : '';
            ?>
            <input type="password" id="dcmm_paypal_client_secret" name="dcmm_settings[dcmm_paypal_client_secret]" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" />
            <p class="description">
                <?php esc_html_e( 'Your PayPal application Client Secret. Keep this secure!', DCMM_PLUGIN_SLUG ); ?><br>
                <strong><?php esc_html_e( 'Where to find:', DCMM_PLUGIN_SLUG ); ?></strong> 
                <?php esc_html_e( 'In your PayPal app details page, click "Show" next to "Client Secret" and copy the revealed secret.', DCMM_PLUGIN_SLUG ); ?><br>
                <em><?php esc_html_e( 'Note: Never share this secret publicly or commit it to version control.', DCMM_PLUGIN_SLUG ); ?></em>
            </p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_paypal_settings'
    );

    register_setting('dcmm_settings_group', 'dcmm_paypal_client_secret', ['capability' => 'manage_dcmm_settings', 'type' => 'string',
            'default' => '',
        ]);

    // PayPal Webhook ID
    add_settings_field(
        'dcmm_paypal_webhook_id',
        __( 'PayPal Webhook ID', DCMM_PLUGIN_SLUG ),
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

    register_setting('dcmm_settings_group', 'dcmm_paypal_webhook_id', ['capability' => 'manage_dcmm_settings', 'type' => 'string',
            'default' => '',
        ]);

    // Email Settings Section
    add_settings_section(
        'dcmm_email_settings',
        __( 'Email Settings', DCMM_PLUGIN_SLUG ),
        function() {
            ?>
            <section class="dcmm-email-settings-section">
                <p><?php esc_html_e( 'Configure email notifications for member signups and renewals.', DCMM_PLUGIN_SLUG ); ?></p>
                <div class="dcmm-email-merge-tags" style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags:', DCMM_PLUGIN_SLUG ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your email templates:', DCMM_PLUGIN_SLUG ); ?></p>
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
        __( 'From Name', DCMM_PLUGIN_SLUG ),
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
        __( 'From Email', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $from_email = isset( $options['from_email'] ) ? esc_attr( $options['from_email'] ) : get_option('admin_email');
            ?>
            <input type="email" id="dcmm_email_from_email" name="dcmm_email_settings[from_email]" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Email address that appears in the "From" field of emails.', DCMM_PLUGIN_SLUG ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Enable Welcome Emails
    add_settings_field(
        'dcmm_enable_welcome_emails',
        __( 'Welcome Emails', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $checked = isset( $options['enable_welcome_emails'] ) ? (bool) $options['enable_welcome_emails'] : true;
            ?>
            <input type="checkbox" id="dcmm_enable_welcome_emails" name="dcmm_email_settings[enable_welcome_emails]" value="1" <?php checked( $checked ); ?> />
            <label for="dcmm_enable_welcome_emails"><?php esc_html_e( 'Send welcome emails to new members', DCMM_PLUGIN_SLUG ); ?></label>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Welcome Email Subject
    add_settings_field(
        'dcmm_welcome_email_subject',
        __( 'Welcome Email Subject', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $subject = isset( $options['welcome_subject'] ) ? esc_attr( $options['welcome_subject'] ) : 'Welcome to Your Membership!';
            ?>
            <input type="text" id="dcmm_welcome_email_subject" name="dcmm_email_settings[welcome_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Subject line for welcome emails.', DCMM_PLUGIN_SLUG ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Welcome Email Template
    add_settings_field(
        'dcmm_welcome_email_template',
        __( 'Welcome Email Template', DCMM_PLUGIN_SLUG ),
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
                <button type="button" class="button dcmm-preview-email" data-email-type="welcome" data-editor-id="dcmm_welcome_email_template">
                    <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                </button>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Enable Renewal Emails
    add_settings_field(
        'dcmm_enable_renewal_emails',
        __( 'Renewal Emails', DCMM_PLUGIN_SLUG ),
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
        __( 'Renewal Email Subject', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_email_settings', array() );
            $subject = isset( $options['renewal_subject'] ) ? esc_attr( $options['renewal_subject'] ) : 'Membership Renewal Confirmation';
            ?>
            <input type="text" id="dcmm_renewal_email_subject" name="dcmm_email_settings[renewal_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Subject line for renewal emails.', DCMM_PLUGIN_SLUG ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Renewal Email Template
    add_settings_field(
        'dcmm_renewal_email_template',
        __( 'Renewal Email Template', DCMM_PLUGIN_SLUG ),
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
                <button type="button" class="button dcmm-preview-email" data-email-type="renewal" data-editor-id="dcmm_renewal_email_template">
                    <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                </button>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_email_settings'
    );

    // Register email settings
    register_setting( 'dcmm_settings_group', 'dcmm_email_settings', array(
        'capability' => 'manage_dcmm_settings'
    ) );

    // Expiration Notification Settings Section
    add_settings_section(
        'dcmm_expiration_notification_settings',
        __( 'Expiration Notification Settings', DCMM_PLUGIN_SLUG ),
        function() {
            ?>
            <section class="dcmm-expiration-notification-section">
                <p><?php esc_html_e( 'Configure automated email notifications for membership expiration reminders.', DCMM_PLUGIN_SLUG ); ?></p>
                <div class="dcmm-expiration-merge-tags" style="background: #f9f9f9; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
                    <h4><?php esc_html_e( 'Available Merge Tags for Expiration Emails:', DCMM_PLUGIN_SLUG ); ?></h4>
                    <p><?php esc_html_e( 'Click any merge tag below to insert it into your expiration email templates:', DCMM_PLUGIN_SLUG ); ?></p>
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
        __( 'Enable Expiration Notifications', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['enabled'] ) ? (bool) $options['enabled'] : false;
            ?>
            <input type="checkbox" id="dcmm_enable_expiration_notifications" name="dcmm_expiration_notification_settings[enabled]" value="1" <?php checked( $enabled ); ?> />
            <label for="dcmm_enable_expiration_notifications"><?php esc_html_e( 'Send automated expiration reminder emails to members', DCMM_PLUGIN_SLUG ); ?></label>
            <p class="description"><?php esc_html_e( 'When enabled, members will receive automated emails before their membership expires.', DCMM_PLUGIN_SLUG ); ?></p>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );

    // 30 Day Notification
    add_settings_field(
        'dcmm_30_day_notification',
        __( '30 Day Notification', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['30_days']['enabled'] ) ? (bool) $options['notifications']['30_days']['enabled'] : true;
            $subject = isset( $options['notifications']['30_days']['subject'] ) ? esc_attr( $options['notifications']['30_days']['subject'] ) : 'Your membership expires in 30 days';
            $template = isset( $options['notifications']['30_days']['template'] ) ? wp_kses_post( $options['notifications']['30_days']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][30_days][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 30 days before expiration', DCMM_PLUGIN_SLUG ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', DCMM_PLUGIN_SLUG ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][30_days][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', DCMM_PLUGIN_SLUG ); ?></strong></p>
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
                        <button type="button" class="button dcmm-preview-email" data-email-type="30_days" data-editor-id="dcmm_30_day_template">
                            <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                        </button>
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
        __( '7 Day Notification', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['7_days']['enabled'] ) ? (bool) $options['notifications']['7_days']['enabled'] : true;
            $subject = isset( $options['notifications']['7_days']['subject'] ) ? esc_attr( $options['notifications']['7_days']['subject'] ) : 'Your membership expires in 7 days';
            $template = isset( $options['notifications']['7_days']['template'] ) ? wp_kses_post( $options['notifications']['7_days']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][7_days][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 7 days before expiration', DCMM_PLUGIN_SLUG ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', DCMM_PLUGIN_SLUG ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][7_days][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', DCMM_PLUGIN_SLUG ); ?></strong></p>
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
                        <button type="button" class="button dcmm-preview-email" data-email-type="7_days" data-editor-id="dcmm_7_day_template">
                            <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                        </button>
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
        __( '1 Day Notification', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['1_day']['enabled'] ) ? (bool) $options['notifications']['1_day']['enabled'] : true;
            $subject = isset( $options['notifications']['1_day']['subject'] ) ? esc_attr( $options['notifications']['1_day']['subject'] ) : 'Your membership expires tomorrow';
            $template = isset( $options['notifications']['1_day']['template'] ) ? wp_kses_post( $options['notifications']['1_day']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][1_day][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send 1 day before expiration', DCMM_PLUGIN_SLUG ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', DCMM_PLUGIN_SLUG ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][1_day][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', DCMM_PLUGIN_SLUG ); ?></strong></p>
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
                        <button type="button" class="button dcmm-preview-email" data-email-type="1_day" data-editor-id="dcmm_1_day_template">
                            <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                        </button>
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
        __( 'Expired Notification', DCMM_PLUGIN_SLUG ),
        function() {
            $options = get_option( 'dcmm_expiration_notification_settings', array() );
            $enabled = isset( $options['notifications']['expired']['enabled'] ) ? (bool) $options['notifications']['expired']['enabled'] : true;
            $subject = isset( $options['notifications']['expired']['subject'] ) ? esc_attr( $options['notifications']['expired']['subject'] ) : 'Your membership has expired';
            $template = isset( $options['notifications']['expired']['template'] ) ? wp_kses_post( $options['notifications']['expired']['template'] ) : '';
            ?>
            <div class="dcmm-notification-field">
                <label>
                    <input type="checkbox" name="dcmm_expiration_notification_settings[notifications][expired][enabled]" value="1" <?php checked( $enabled ); ?> />
                    <?php esc_html_e( 'Send on expiration day', DCMM_PLUGIN_SLUG ); ?>
                </label>
                <div class="dcmm-notification-details" style="margin-top: 10px;">
                    <p><strong><?php esc_html_e( 'Subject:', DCMM_PLUGIN_SLUG ); ?></strong></p>
                    <input type="text" name="dcmm_expiration_notification_settings[notifications][expired][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text" />
                    <p><strong><?php esc_html_e( 'Email Template:', DCMM_PLUGIN_SLUG ); ?></strong></p>
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
                        <button type="button" class="button dcmm-preview-email" data-email-type="expired" data-editor-id="dcmm_expired_template">
                            <?php esc_html_e( 'Preview Email', 'dcmm-membership' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php
        },
        'dcmm_settings_group',
        'dcmm_expiration_notification_settings'
    );


    // Register expiration notification settings
    register_setting( 'dcmm_settings_group', 'dcmm_expiration_notification_settings', array(
        'capability' => 'manage_dcmm_settings'
    ) );

    // My Account Page setting
    add_settings_field(
        'dcmm_my_account_page',
        __( 'My Account Page', DCMM_PLUGIN_SLUG ),
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

    // if membership period is specific date, add a date field; but only have it show if the membership period is set to specific date
    // the date field will be hidden by default and shown only when the specific date option is selected, dynamically using JavaScript
    add_settings_field(
        'dcmm_anchor_date',
        __( 'Anchor Date', DCMM_PLUGIN_SLUG ),
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

    register_setting('dcmm_settings_group', 'dcmm_anchor_date', ['capability' => 'manage_dcmm_settings', 'type' => 'string',
            'default' => '',
        ]);    
}
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );

/**
 * Allow users with manage_dcmm_settings capability to save plugin settings
 * 
 * This filter is required because WordPress Settings API defaults to 'manage_options'
 * capability when processing form submissions to options.php
 * 
 * @return string The custom capability for this plugin's settings
 * @since 1.1.1
 */
function allow_custom_capability_for_settings() {
    return 'manage_dcmm_settings';
}
add_filter( 'option_page_capability_dcmm_settings_group', __NAMESPACE__ . '\allow_custom_capability_for_settings' );

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
