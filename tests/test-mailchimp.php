<?php
/**
 * Test script for MailChimp integration
 * 
 * This is a temporary test file to verify the MailChimp functionality works.
 * DELETE THIS FILE before production deployment.
 */

// Only allow this to run in admin or CLI
if ( ! defined( 'WP_ADMIN' ) && ! defined( 'WP_CLI' ) ) {
    exit( 'Direct access not allowed' );
}

// Check if current user can manage options
if ( ! current_user_can( 'manage_options' ) ) {
    exit( 'Permission denied' );
}

echo "<h2>DCMM MailChimp Integration Test</h2>";

try {
    // Include required files
    require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/class-mailchimp-api.php';
    require_once DCMM_PATH . '/includes/premium/interface-premium-feature.php';
    require_once DCMM_PATH . '/includes/premium/class-premium-manager.php';
    require_once DCMM_PATH . '/includes/premium/integrations/class-mailchimp-integration.php';
    
    echo "<h3>1. Testing MailChimp API Connection</h3>";
    
    $api_key = get_option( 'dcmm_mailchimp_api_key' );
    if ( empty( $api_key ) ) {
        echo "<p style='color: red;'>❌ No API key configured. Please set it in Premium Features settings.</p>";
    } else {
        echo "<p>✓ API key found: " . substr( $api_key, 0, 10 ) . "...</p>";
        
        $mailchimp_api = new \DCMM\MailChimp\MailChimp_API( $api_key );
        $connection_test = $mailchimp_api->test_connection();
        
        if ( $connection_test['success'] ) {
            echo "<p style='color: green;'>✓ " . $connection_test['message'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ " . $connection_test['message'] . "</p>";
        }
    }
    
    echo "<h3>2. Testing List Retrieval</h3>";
    
    if ( ! empty( $api_key ) ) {
        $lists = $mailchimp_api->get_lists();
        if ( ! empty( $lists ) ) {
            echo "<p>✓ Found " . count( $lists ) . " lists:</p><ul>";
            foreach ( $lists as $list ) {
                echo "<li>" . esc_html( $list['name'] ) . " (ID: " . esc_html( $list['id'] ) . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ No lists found or error retrieving lists.</p>";
        }
    }
    
    echo "<h3>3. Testing Premium Feature Registration</h3>";
    
    $premium_manager = \DCMM\Premium\Premium_Manager::get_instance();
    $features = $premium_manager->get_all_features();
    
    if ( isset( $features['mailchimp_integration'] ) ) {
        echo "<p style='color: green;'>✓ MailChimp integration feature is registered</p>";
        
        $feature = $features['mailchimp_integration'];
        echo "<p>Feature name: " . $feature->get_feature_name() . "</p>";
        echo "<p>Dependencies met: " . ( $feature->check_dependencies() ? 'Yes' : 'No' ) . "</p>";
        echo "<p>Currently enabled: " . ( $feature->is_enabled() ? 'Yes' : 'No' ) . "</p>";
        
        if ( ! $feature->check_dependencies() ) {
            $errors = $feature->get_dependency_errors();
            echo "<p style='color: red;'>Dependency errors: " . implode( ', ', $errors ) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ MailChimp integration feature not found</p>";
    }
    
    echo "<h3>4. Testing Member Hooks</h3>";
    
    // Check if hooks are properly registered
    global $wp_filter;
    $hooks_to_check = [
        'dcmm_member_status_changed',
        'dcmm_member_payment_received', 
        'dcmm_member_created'
    ];
    
    foreach ( $hooks_to_check as $hook ) {
        if ( isset( $wp_filter[ $hook ] ) ) {
            echo "<p style='color: green;'>✓ Hook '{$hook}' is registered</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Hook '{$hook}' not found (may not be initialized yet)</p>";
        }
    }
    
    echo "<h3>5. Configuration Summary</h3>";
    echo "<ul>";
    echo "<li>MailChimp API Key: " . ( $api_key ? 'Configured' : 'Not configured' ) . "</li>";
    echo "<li>Selected List ID: " . ( get_option( 'dcmm_mailchimp_list_id' ) ?: 'Not selected' ) . "</li>";
    echo "<li>Remove on Lapse: " . ( get_option( 'dcmm_mailchimp_remove_on_lapse' ) ? 'Yes' : 'No' ) . "</li>";
    echo "<li>Add on Payment: " . ( get_option( 'dcmm_mailchimp_add_on_payment' ) ? 'Yes' : 'No' ) . "</li>";
    echo "</ul>";
    
} catch ( Exception $e ) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr><p><em>This is a test file. Remember to delete it before going to production!</em></p>";
?>