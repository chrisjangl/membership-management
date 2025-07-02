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
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Membership Management Settings', 'dcmm-membership' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'dcmm_settings_group' );
            do_settings_sections( 'dcmm_settings_group' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

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

    register_setting(
        'dcmm_settings_group',
        'dcmm_enable_feature',
        [
            'type' => 'boolean',
            'default' => false,
        ]
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

    // Add JavaScript to show/hide the specific date field based on the join policy selection
    add_action( 'admin_footer', function() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to toggle the dues amount field based on the dues enabled checkbox
                function toggleDuesAmountField() {
                    var amountRow = $('#dcmm_dues_amount').closest('tr');
                    var duesEnabled = document.getElementById('dcmm_enable_dues').checked;
                    if (duesEnabled) {
                        amountRow.show();
                        console.debug('on');
                    } else {
                        amountRow.hide();
                        console.debug('off');
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

                // Initial check
                toggleDuesAmountField();
                toggleSpecificDateField();

                // Bind change event
                $('#dcmm_enable_dues').change(function() {
                    toggleDuesAmountField();
                });

                $('#dcmm_join_policy').change(function() {
                    toggleSpecificDateField();
                });
            });
        </script>
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
            ?>
            <input type="date" id="dcmm_anchor_date" name="dcmm_settings[dcmm_anchor_date]" value="<?php echo esc_attr( $anchor_date ); ?>" />
            <label for="dcmm_anchor_date"><?php esc_html_e( 'Set a specific date for the membership period', 'dcmm-membership' ); ?></label>
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