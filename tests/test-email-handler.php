<?php
/**
 * Simple test script for email handler functionality
 * 
 * This script tests the email handler directly to diagnose email issues
 * Access: yoursite.com/wp-content/plugins/dc-membership/test-email-handler.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Email Handler Test</h1>";

// Test 1: Check if email handler is loaded
echo "<h2>1. Email Handler Status</h2>";
$email_handler = DCMM_Email_Handler::get_instance();
if ($email_handler) {
    echo "<p style='color: green;'>✅ Email handler is loaded</p>";
} else {
    echo "<p style='color: red;'>❌ Email handler is not loaded</p>";
    exit;
}

// Test 2: Check email settings
echo "<h2>2. Email Settings</h2>";
$email_settings = get_option('dcmm_email_settings', array());
echo "<p><strong>Email settings found:</strong> " . (empty($email_settings) ? "None" : count($email_settings) . " settings") . "</p>";

if (!empty($email_settings)) {
    echo "<ul>";
    foreach ($email_settings as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }
        echo "<li><strong>{$key}:</strong> " . esc_html($value) . "</li>";
    }
    echo "</ul>";
}

// Test 3: Check for test member
echo "<h2>3. Test Member</h2>";
$test_members = get_posts([
    'post_type' => 'dcmm-member',
    'posts_per_page' => 1,
    'meta_query' => [
        [
            'key' => 'dcmm_status',
            'value' => 'active',
            'compare' => '='
        ]
    ]
]);

if (empty($test_members)) {
    echo "<p style='color: orange;'>⚠️ No active members found for testing</p>";
} else {
    $test_member_post = $test_members[0];
    $test_member = new DCMM_Member($test_member_post->ID);
    
    echo "<p style='color: green;'>✅ Found test member: " . get_the_title($test_member_post->ID) . "</p>";
    echo "<p><strong>Email:</strong> " . $test_member->get('email') . "</p>";
    echo "<p><strong>Status:</strong> " . $test_member->get('status') . "</p>";
    
    // Test 4: Manual email trigger
    echo "<h2>4. Manual Email Test</h2>";
    
    if (isset($_GET['test_welcome'])) {
        echo "<h3>Testing Welcome Email</h3>";
        $result = $email_handler->send_welcome_email($test_member);
        echo "<p>Result: " . ($result ? "✅ Success" : "❌ Failed") . "</p>";
        
        if (!$result) {
            echo "<p style='color: red;'>Check error logs for details</p>";
        }
    }
    
    if (isset($_GET['test_renewal'])) {
        echo "<h3>Testing Renewal Email</h3>";
        $result = $email_handler->send_renewal_email($test_member, 'manual_test');
        echo "<p>Result: " . ($result ? "✅ Success" : "❌ Failed") . "</p>";
        
        if (!$result) {
            echo "<p style='color: red;'>Check error logs for details</p>";
        }
    }
    
    if (isset($_GET['test_action'])) {
        echo "<h3>Testing Full Action Hook</h3>";
        echo "<p>Triggering dcmm_member_subscribed action...</p>";
        do_action('dcmm_member_subscribed', $test_member->get_member_id(), 'test_signup');
        echo "<p>Action triggered. Check error logs for results.</p>";
    }
    
    echo "<p>
        <a href='?test_welcome=1' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;'>Test Welcome Email</a>
        <a href='?test_renewal=1' style='background: #00a32a; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;'>Test Renewal Email</a>
        <a href='?test_action=1' style='background: #d63638; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>Test Action Hook</a>
    </p>";
}

// Test 5: WordPress Mail Test
echo "<h2>5. WordPress Mail System Test</h2>";
if (isset($_GET['test_wp_mail'])) {
    $admin_email = get_option('admin_email');
    $test_result = wp_mail($admin_email, 'DCMM Email Test', 'This is a test email from the DC Membership plugin.');
    echo "<p>WordPress wp_mail test to {$admin_email}: " . ($test_result ? "✅ Success" : "❌ Failed") . "</p>";
    
    if (!$test_result) {
        echo "<p style='color: red;'>WordPress mail system appears to be having issues. Check your mail server configuration.</p>";
    }
} else {
    echo "<p><a href='?test_wp_mail=1' style='background: #8c8f94; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>Test WordPress Mail</a></p>";
}

echo "<hr>";
echo "<p><strong>Notes:</strong></p>";
echo "<ul>";
echo "<li>Check your site's error logs for detailed debugging information</li>";
echo "<li>Make sure your WordPress mail system is configured correctly</li>";
echo "<li>Ensure email settings are enabled in Members > Settings</li>";
echo "</ul>";
?>