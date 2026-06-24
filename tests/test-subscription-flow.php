<?php
/**
 * Complete Subscription Flow Test Guide
 * 
 * This file provides step-by-step testing instructions for the recurring billing feature.
 * Access via: yoursite.com/wp-content/plugins/dc-membership/test-subscription-flow.php
 */

if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>DCMM Subscription Flow Test Guide</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .step { background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0; }
        .success { background: #d4edda; border-color: #28a745; }
        .warning { background: #fff3cd; border-color: #ffc107; }
        .error { background: #f8d7da; border-color: #dc3545; }
        code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; }
        .webhook-urls { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔄 DCMM Recurring Billing Test Guide</h1>

    <div class="step success">
        <h3>✅ Setup Complete!</h3>
        <p>Your recurring billing feature has been successfully implemented. Follow the steps below to test it end-to-end.</p>
    </div>

    <h2>📋 Testing Checklist</h2>

    <div class="step">
        <h3>Step 1: Configure PayPal Webhooks</h3>
        <p>In your PayPal Developer Dashboard, add these webhook URLs:</p>
        <div class="webhook-urls">
            <strong>Webhook URL:</strong><br>
            <code><?php echo esc_url(site_url('/wp-json/dcmm/v1/paypal-webhook')); ?></code>
            
            <br><br><strong>Events to Subscribe to:</strong>
            <ul>
                <li><code>BILLING.SUBSCRIPTION.ACTIVATED</code></li>
                <li><code>BILLING.SUBSCRIPTION.CANCELLED</code></li>
                <li><code>BILLING.SUBSCRIPTION.SUSPENDED</code></li>
                <li><code>PAYMENT.SALE.COMPLETED</code></li>
                <li><code>PAYMENT.SALE.DENIED</code></li>
            </ul>
        </div>
    </div>

    <div class="step">
        <h3>Step 2: Test Subscription Creation</h3>
        <ol>
            <li>Log in as a member on your frontend</li>
            <li>Navigate to: <code><?php echo esc_url(home_url('/member-dashboard/')); ?></code></li>
            <li>Look for renewal options with radio buttons</li>
            <li>Select "Recurring subscription" option</li>
            <li>Click "Renew Membership"</li>
            <li>Verify you're redirected to PayPal with "Agree & Subscribe" button</li>
            <li>Complete the subscription setup</li>
        </ol>
    </div>

    <div class="step">
        <h3>Step 3: Test Subscription Return Flow</h3>
        <p>After completing PayPal subscription setup, you should be redirected to:</p>
        <code><?php echo esc_url(site_url('/wp-json/dcmm/v1/paypal-subscription-return')); ?></code>
        
        <p>This should then redirect you back to your member dashboard with a success message.</p>
    </div>

    <div class="step">
        <h3>Step 4: Verify Subscription Display</h3>
        <p>On the member dashboard, you should now see:</p>
        <ul>
            <li>🔄 <strong>Automatic Renewal Subscription</strong> section</li>
            <li><span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px;">ACTIVE</span> status badge</li>
            <li>Billing frequency, payment method details</li>
            <li>Next billing date (if available)</li>
            <li>Green "You're all set!" message</li>
            <li>Red "Cancel Automatic Renewal" button</li>
        </ul>
    </div>

    <div class="step">
        <h3>Step 5: Test Subscription Cancellation</h3>
        <ol>
            <li>Click "Cancel Automatic Renewal" button</li>
            <li>Confirm the cancellation</li>
            <li>Verify the subscription status updates to "cancelled"</li>
            <li>Check that renewal options reappear for manual renewal</li>
        </ol>
    </div>

    <h2>🔍 Debugging & Logs</h2>

    <div class="step warning">
        <h3>Check WordPress Debug Logs</h3>
        <p>Monitor these log entries for debugging:</p>
        <ul>
            <li><code>DCMM: Starting subscription renewal for member...</code></li>
            <li><code>DCMM PayPal: Creating subscription...</code></li>
            <li><code>DCMM PayPal: Subscription approval URL...</code></li>
            <li><code>PayPal subscription return - Subscription ID...</code></li>
        </ul>
        
        <p><strong>Debug Log Location:</strong> <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code></p>
    </div>

    <div class="step">
        <h3>Test Direct Subscription Creation</h3>
        <p>For technical testing, you can test subscription creation directly:</p>
        <a href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'debug-subscription.php?test=1'); ?>" 
           style="background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
            🧪 Test Subscription Creation
        </a>
    </div>

    <h2>🎯 Expected User Experience</h2>

    <div class="step success">
        <h3>For Members with Active Subscriptions:</h3>
        <ul>
            <li>See detailed subscription information at top of dashboard</li>
            <li>No renewal prompts or buttons (since they're auto-renewing)</li>
            <li>Clear next billing date and amount</li>
            <li>Easy cancellation option</li>
        </ul>
    </div>

    <div class="step">
        <h3>For Members without Subscriptions:</h3>
        <ul>
            <li>See renewal options with subscription choice</li>
            <li>Can choose between one-time or recurring billing</li>
            <li>After setting up subscription, UI updates automatically</li>
        </ul>
    </div>

    <h2>📞 Support Information</h2>

    <div class="step">
        <h3>PayPal Subscription URLs:</h3>
        <ul>
            <li><strong>Return URL:</strong> <code><?php echo esc_url(site_url('/wp-json/dcmm/v1/paypal-subscription-return')); ?></code></li>
            <li><strong>Cancel URL:</strong> <code><?php echo esc_url(site_url('/wp-json/dcmm/v1/paypal-subscription-cancel')); ?></code></li>
            <li><strong>Webhook URL:</strong> <code><?php echo esc_url(site_url('/wp-json/dcmm/v1/paypal-webhook')); ?></code></li>
        </ul>
    </div>

    <p style="text-align: center; margin-top: 40px; color: #666;">
        <em>🚀 Recurring billing implementation complete! Your members can now enjoy hassle-free automatic renewals.</em>
    </p>

</body>
</html>