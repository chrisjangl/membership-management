<?php
/**
 * TEMPORARY TEST FILE - Remove after testing
 * 
 * This file allows manual testing of the expiration notification system
 * Access: yoursite.com/wp-content/plugins/dc-membership/test-notifications.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    // die('Access denied');
}

echo "<h1>Testing Expiration Notifications</h1>";

// Test renewal URL functionality
if (isset($_GET['test_renewal_url'])) {
    echo "<h2>🔗 Renewal URL Testing</h2>";
    
    // Test URL generation
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
    
    if ($test_members) {
        $member = new DCMM_Member($test_members[0]->ID);
        $email_handler = DCMM_Email_Handler::get_instance();
        
        // Use reflection to call the private method
        $reflection = new ReflectionClass($email_handler);
        $method = $reflection->getMethod('get_renewal_url');
        $method->setAccessible(true);
        $renewal_url = $method->invoke($email_handler, $member);
        
        echo "<p><strong>Generated renewal URL:</strong> <a href='" . esc_url($renewal_url) . "' target='_blank'>" . esc_html($renewal_url) . "</a></p>";
        
        // Check settings
        $settings = get_option('dcmm_settings', array());
        $my_account_page_id = isset($settings['dcmm_my_account_page']) ? $settings['dcmm_my_account_page'] : '';
        echo "<p><strong>My Account Page Setting:</strong> " . ($my_account_page_id ? "Page ID: $my_account_page_id (" . get_the_title($my_account_page_id) . ")" : "Not set") . "</p>";
        
        // Check if pages exist
        $member_dashboard_page = get_page_by_path('member-dashboard');
        $member_login_page = get_page_by_path('member-login');
        echo "<p><strong>Member Dashboard Page:</strong> " . ($member_dashboard_page ? "Exists (ID: {$member_dashboard_page->ID})" : "Not found") . "</p>";
        echo "<p><strong>Member Login Page:</strong> " . ($member_login_page ? "Exists (ID: {$member_login_page->ID})" : "Not found") . "</p>";
    }
    
    echo "<hr>";
}

if (!isset($_GET['test_renewal_url'])) {
    echo "<p><a href='?test_renewal_url=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🔗 Test Renewal URL Generation</a></p>";
}

// Test 0: Check get_expiration_date() fix
echo "<h2>0. Test get_expiration_date() Fix</h2>";
$test_members = get_posts([
    'post_type' => 'dcmm-member',
    'posts_per_page' => 3,
    'meta_query' => [
        [
            'key' => 'dcmm_status',
            'value' => 'active',
            'compare' => '='
        ]
    ]
]);

foreach ($test_members as $member_post) {
    $member = new DCMM_Member($member_post->ID);
    $expiration = $member->get_expiration_date();
    echo "<p><strong>" . get_the_title($member_post->ID) . "</strong> - Expiration: " . ($expiration ? $expiration : 'None') . "</p>";
}

// Test 1: Check if settings are configured
echo "<h2>1. Settings Check</h2>";
$settings = get_option('dcmm_expiration_notification_settings', array());
if (empty($settings['enabled'])) {
    echo "<p style='color: red;'>❌ Expiration notifications are disabled. Enable them in Members > Settings.</p>";
} else {
    echo "<p style='color: green;'>✅ Expiration notifications are enabled.</p>";
}

// Test 2: Check for test members
echo "<h2>2. Test Members</h2>";
$windows = [30, 7, 1, 0];
foreach ($windows as $days) {
    $target_date = date('Y-m-d', strtotime("+{$days} days"));
    $members = get_posts([
        'post_type' => 'dcmm-member',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'dcmm_expiration_date',
                'value' => $target_date,
                'compare' => '='
            ],
            [
                'key' => 'dcmm_status',
                'value' => 'active',
                'compare' => '='
            ]
        ]
    ]);
    
    echo "<p><strong>{$days} days ({$target_date}):</strong> " . count($members) . " members</p>";
    foreach ($members as $member) {
        echo "<p>  - " . get_the_title($member->ID) . " (ID: {$member->ID})</p>";
    }
}

// Test 3: Manual trigger with debugging
if (isset($_GET['trigger'])) {
    echo "<h2>3. Manual Trigger Results with Debug Info</h2>";
    echo "<p>Triggering expiration check with detailed debugging...</p>";
    
    // Test individual components before running full trigger
    echo "<h3>Debug Steps:</h3>";
    
    // Check if notifications are enabled
    $enabled = DCMM_Expiration_Scheduler::notifications_enabled();
    echo "<p><strong>Notifications Enabled:</strong> " . ($enabled ? "✅ Yes" : "❌ No") . "</p>";
    
    // Check notification windows
    $windows = DCMM_Expiration_Scheduler::get_notification_windows();
    echo "<p><strong>Notification Windows:</strong> " . implode(', ', $windows) . " days</p>";
    
    // Test each window individually
    foreach ($windows as $days) {
        $notification_type = DCMM_Expiration_Scheduler::get_notification_type($days);
        $type_enabled = DCMM_Expiration_Scheduler::is_notification_type_enabled($notification_type);
        echo "<p><strong>{$notification_type} ({$days} days):</strong> " . ($type_enabled ? "✅ Enabled" : "❌ Disabled") . "</p>";
        
        if ($type_enabled) {
            $members = DCMM_Expiration_Scheduler::get_members_expiring_in_days($days);
            echo "<p>  → Found " . count($members) . " members expiring in {$days} days</p>";
            
            foreach ($members as $member_post) {
                $member = new DCMM_Member($member_post->ID);
                $already_sent = DCMM_Notification_Logger::notification_sent($member_post->ID, $notification_type);
                echo "<p>  → Member: " . get_the_title($member_post->ID) . " - Already sent: " . ($already_sent ? "Yes" : "No") . "</p>";
                
                // Test email sending manually
                if (!$already_sent) {
                    echo "<p>  → Attempting to send email...</p>";
                    
                    // Test member object first
                    $member = new DCMM_Member($member_post->ID);
                    echo "<p>  → Member email: " . ($member->get('email') ?: 'NOT SET') . "</p>";
                    echo "<p>  → Member exists: " . ($member->exists() ? "Yes" : "No") . "</p>";
                    
                    // Test email handler availability
                    $email_handler = DCMM_Email_Handler::get_instance();
                    echo "<p>  → Email handler loaded: " . (is_object($email_handler) ? "Yes" : "No") . "</p>";
                    
                    // Test notification type enabled
                    $notification_enabled = DCMM_Expiration_Scheduler::is_notification_type_enabled($notification_type);
                    echo "<p>  → Notification type ({$notification_type}) enabled: " . ($notification_enabled ? "Yes" : "No") . "</p>";
                    
                    // Test if email handler has the method
                    echo "<p>  → Email handler has send_expiration_notification method: " . (method_exists($email_handler, 'send_expiration_notification') ? "Yes" : "No") . "</p>";
                    
                    // Test manual email sending with more detail
                    if (method_exists($email_handler, 'send_expiration_notification')) {
                        echo "<p>  → Calling email handler directly...</p>";
                        $email_result = $email_handler->send_expiration_notification($member, $notification_type);
                        echo "<p>  → Direct email result: " . ($email_result ? "✅ Success" : "❌ Failed") . "</p>";
                        
                        // Test WordPress mail function
                        if (!$email_result) {
                            echo "<p>  → Testing basic WordPress mail...</p>";
                            $test_email = wp_mail($member->get('email'), 'Test Email', 'This is a test email to verify mail functionality.');
                            echo "<p>  → WordPress wp_mail test: " . ($test_email ? "✅ Success" : "❌ Failed") . "</p>";
                        }
                    } else {
                        echo "<p style='color: red;'>  → Email handler method missing!</p>";
                    }
                    
                    if ($email_result) {
                        $log_result = DCMM_Notification_Logger::log_notification($member_post->ID, $notification_type);
                        echo "<p>  → Log result: " . ($log_result ? "✅ Logged" : "❌ Log failed") . "</p>";
                    }
                }
            }
        }
    }
    
    echo "<hr>";
    echo "<p><strong>Full Trigger Test:</strong></p>";
    
    // Force trigger the scheduler
    DCMM_Expiration_Scheduler::trigger_manual_check(true);
    
    echo "<p style='color: green;'>✅ Manual trigger completed. Check debug info above!</p>";
    
    // Show notification log
    $stats = DCMM_Notification_Logger::get_notification_stats();
    if ($stats) {
        echo "<h3>Notification Statistics:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Type</th><th>Total Sent</th><th>Unique Members</th><th>Last Sent</th></tr>";
        foreach ($stats as $stat) {
            echo "<tr>";
            echo "<td>{$stat->notification_type}</td>";
            echo "<td>{$stat->total_sent}</td>";
            echo "<td>{$stat->unique_members}</td>";
            echo "<td>{$stat->last_sent}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<h2>3. Manual Trigger</h2>";
    echo "<p><a href='?trigger=1' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🚀 Trigger Notifications Now (with Debug)</a></p>";
}

// Test 4: Check notification log
echo "<h2>4. Recent Notifications</h2>";
global $wpdb;
$table_name = DCMM_Notification_Logger::get_table_name();
$recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");

if ($recent) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Member ID</th><th>Type</th><th>Sent Date</th><th>Created</th></tr>";
    foreach ($recent as $notification) {
        echo "<tr>";
        echo "<td>{$notification->member_id}</td>";
        echo "<td>{$notification->notification_type}</td>";
        echo "<td>{$notification->sent_date}</td>";
        echo "<td>{$notification->created_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No notifications sent yet.</p>";
}

// Test 5: WordPress Cron Status (Enhanced)
echo "<h2>5. WordPress Cron Status (Enhanced)</h2>";

// Check if our specific cron is scheduled
$cron_jobs = wp_get_scheduled_event('dcmm_check_expiration_notifications');
if ($cron_jobs) {
    $next_run = date('Y-m-d H:i:s', $cron_jobs->timestamp);
    $time_until = $cron_jobs->timestamp - time();
    echo "<p style='color: green;'>✅ Cron job is scheduled</p>";
    echo "<p><strong>Next run:</strong> {$next_run}</p>";
    echo "<p><strong>Time until next run:</strong> " . ($time_until > 0 ? gmdate('H:i:s', $time_until) : 'OVERDUE by ' . gmdate('H:i:s', abs($time_until))) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Cron job is not scheduled.</p>";
}

// Check WordPress cron system status
echo "<h3>WordPress Cron System Status:</h3>";
$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
echo "<p><strong>WP_CRON disabled:</strong> " . ($cron_disabled ? "❌ Yes (cron won't run automatically)" : "✅ No") . "</p>";

// Check all scheduled events
$all_crons = wp_get_ready_cron_jobs();
echo "<p><strong>Ready cron jobs:</strong> " . count($all_crons) . "</p>";

// Check if any crons are overdue
$overdue_count = 0;
foreach ($all_crons as $timestamp => $crons) {
    if ($timestamp < time()) {
        $overdue_count++;
    }
}
echo "<p><strong>Overdue cron jobs:</strong> " . $overdue_count . "</p>";

// Manual cron trigger test
if (isset($_GET['trigger_cron'])) {
    echo "<h3>Manual WordPress Cron Trigger:</h3>";
    echo "<p>Spawning WordPress cron...</p>";
    
    // Trigger WordPress cron
    spawn_cron();
    
    echo "<p style='color: green;'>✅ WordPress cron triggered. Check logs above for results.</p>";
} else {
    echo "<p><a href='?trigger_cron=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🔄 Trigger WordPress Cron</a></p>";
}

// Show when cron typically runs
echo "<h3>Cron Behavior:</h3>";
echo "<p><strong>How WordPress cron works:</strong></p>";
echo "<ul>";
echo "<li>Cron only runs when someone visits your site</li>";
echo "<li>If no visitors, cron doesn't run (even if scheduled)</li>";
echo "<li>Cron checks if any scheduled events are due</li>";
echo "<li>Multiple events can run on the same page load</li>";
echo "</ul>";

// Show next scheduled runs for our plugin
echo "<h3>Our Plugin's Cron Schedule:</h3>";
$next_scheduled = wp_next_scheduled('dcmm_check_expiration_notifications');
if ($next_scheduled) {
    echo "<p><strong>Next scheduled:</strong> " . date('Y-m-d H:i:s', $next_scheduled) . "</p>";
    echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    if ($next_scheduled <= time()) {
        echo "<p style='color: orange;'>⚠️ Cron is overdue and should run on next page visit</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Cron will run after " . date('Y-m-d H:i:s', $next_scheduled) . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No cron scheduled for our plugin</p>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Set up test members with expiration dates</li>";
echo "<li>Use the manual trigger to test immediately</li>";
echo "<li>Check your email for test notifications</li>";
echo "<li>Monitor the notification log for sent emails</li>";
echo "</ul>";
?>