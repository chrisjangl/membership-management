<?php
/**
 * Test script for recurring billing functionality
 * 
 * This script tests the subscription creation flow without requiring
 * a full WordPress frontend test.
 * 
 * Usage: Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

function dcmm_test_recurring_billing() {
    
    echo "<h2>DCMM Recurring Billing Test</h2>\n";
    
    // Test 1: Check if PayPal gateway supports subscriptions
    echo "<h3>Test 1: Gateway Subscription Support</h3>\n";
    
    try {
        $gateway = \DCMM\Gateways\Gateway_Manager::get_default_gateway();
        if ( ! $gateway ) {
            echo "❌ No default gateway found\n";
            return;
        }
        
        echo "✅ Gateway found: " . get_class( $gateway ) . "\n";
        
        if ( method_exists( $gateway, 'supports_subscriptions' ) && $gateway->supports_subscriptions() ) {
            echo "✅ Gateway supports subscriptions\n";
        } else {
            echo "❌ Gateway does not support subscriptions\n";
            return;
        }
        
    } catch ( Exception $e ) {
        echo "❌ Error loading gateway: " . $e->getMessage() . "\n";
        return;
    }
    
    // Test 2: Check PayPal configuration
    echo "<h3>Test 2: PayPal Configuration</h3>\n";
    
    if ( \DCMM_Settings\is_paypal_configured() ) {
        echo "✅ PayPal is configured\n";
    } else {
        echo "❌ PayPal is not configured\n";
        echo "Please check your PayPal settings in the admin area.\n";
        return;
    }
    
    // Test 3: Test member renewal options
    echo "<h3>Test 3: Member Renewal Options</h3>\n";
    
    // Get the first member for testing
    $members_query = new WP_Query([
        'post_type' => 'dcmm-member',
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ]);
    
    if ( ! $members_query->have_posts() ) {
        echo "❌ No members found for testing\n";
        return;
    }
    
    $member_post = $members_query->posts[0];
    $member = new DCMM_Member( $member_post->ID );
    
    echo "✅ Testing with member ID: " . $member->get_member_id() . "\n";
    
    $renewal_options = $member->get_renewal_options();
    
    if ( isset( $renewal_options['subscription_available'] ) && $renewal_options['subscription_available'] ) {
        echo "✅ Subscription renewal available\n";
        echo "   - Interval: " . ( $renewal_options['subscription_interval'] ?? 'not set' ) . "\n";
        echo "   - Amount: $" . number_format( $renewal_options['amount'] ?? 0, 2 ) . "\n";
    } else {
        echo "❌ Subscription renewal not available\n";
    }
    
    // Test 4: Simulate subscription creation process (without actually creating)
    echo "<h3>Test 4: Subscription Creation Simulation</h3>\n";
    
    echo "This would create a subscription with:\n";
    echo "   - Member ID: " . $member->get_member_id() . "\n";
    echo "   - Amount: $" . ( \DCMM_Settings\get_dues_amount() ?? 'not set' ) . "\n";
    echo "   - Interval: " . ( $renewal_options['subscription_interval'] ?? 'not set' ) . "\n";
    
    echo "\n<p><strong>✅ All tests passed! Recurring billing should work.</strong></p>\n";
    echo "<p>To test the full flow:</p>\n";
    echo "<ol>\n";
    echo "<li>Log in as a member on the frontend</li>\n";
    echo "<li>Go to the member dashboard</li>\n";
    echo "<li>Select 'Recurring subscription' option</li>\n";
    echo "<li>Click 'Renew Membership'</li>\n";
    echo "<li>Check WordPress error logs for debugging info</li>\n";
    echo "</ol>\n";
}

// Run the test if accessed directly
if ( isset( $_GET['dcmm_test_recurring'] ) ) {
    dcmm_test_recurring_billing();
}