<?php
/**
 * Expiration Scheduler Class
 * 
 * Handles scheduling and sending of membership expiration notifications
 * using WordPress cron jobs.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class DCMM_Expiration_Scheduler {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'dcmm_' . 'check_expiration_notifications';

    /**
     * Default notification windows (days before expiration)
     */
    const DEFAULT_WINDOWS = array( 30, 7, 1, 0 );

    /**
     * Initialize the scheduler
     */
    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'process_expiration_notifications' ) );
        add_action( 'init', array( __CLASS__, 'schedule_cron_job' ) );
        
        // Clear notifications when member status changes
        add_action( 'dcmm_member_cancelled', array( __CLASS__, 'clear_member_notifications' ) );
        add_action( 'dcmm_member_renewed', array( __CLASS__, 'clear_member_notifications' ) );
    }

    /**
     * Schedule the daily cron job if not already scheduled
     */
    public static function schedule_cron_job() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the cron job (for plugin deactivation)
     */
    public static function unschedule_cron_job() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Main method to process expiration notifications
     * Called by WordPress cron
     */
    public static function process_expiration_notifications() {
        // Check if notifications are enabled
        if ( ! self::notifications_enabled() ) {
            return;
        }

        $notification_windows = self::get_notification_windows();
        
        foreach ( $notification_windows as $days ) {
            $notification_type = self::get_notification_type( $days );
            
            // Skip if this notification type is disabled
            if ( ! self::is_notification_type_enabled( $notification_type ) ) {
                continue;
            }

            $members = self::get_members_expiring_in_days( $days );
            
            foreach ( $members as $member_post ) {
                // Check if notification already sent
                if ( DCMM_Notification_Logger::notification_sent( $member_post->ID, $notification_type ) ) {
                    continue;
                }

                // Send notification
                if ( self::send_expiration_notification( $member_post->ID, $notification_type ) ) {
                    // Log successful notification
                    DCMM_Notification_Logger::log_notification( $member_post->ID, $notification_type );
                }
            }
        }
    }

    /**
     * Get members expiring in a specific number of days (with range tolerance for missed cron runs)
     * 
     * @param int $days Number of days from today
     * @return array Array of member post objects
     */
    public static function get_members_expiring_in_days( $days ) {
        // For cron reliability, check a range instead of exact date
        // This handles cases where cron doesn't run for several days
        
        if ( $days == 0 ) {
            // For "expired" notifications, check today and past few days
            $start_date = date( 'Y-m-d', strtotime( '-3 days' ) );
            $end_date = date( 'Y-m-d' );
        } else {
            // For future notifications, check the target day and a few days before
            // This ensures we don't miss notifications if cron is delayed
            $end_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );
            $start_date = date( 'Y-m-d', strtotime( "+{$days} days -3 days" ) );
        }
        
        $args = array(
            'post_type' => 'dcmm-member',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'dcmm_expiration_date',
                    'value' => array( $start_date, $end_date ),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'dcmm_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );
        
        $members = get_posts( $args );
        
        // Filter out members who already received this notification type recently
        $filtered_members = array();
        foreach ( $members as $member ) {
            $notification_type = self::get_notification_type( $days );
            
            // Check if notification was sent in the last 7 days to prevent spam
            if ( ! self::notification_sent_recently( $member->ID, $notification_type, 7 ) ) {
                $filtered_members[] = $member;
            }
        }
        
        return $filtered_members;
    }

    /**
     * Send expiration notification to a member
     * 
     * @param int    $member_id        Member CPT ID
     * @param string $notification_type Type of notification
     * @return bool Success/failure
     */
    public static function send_expiration_notification( $member_id, $notification_type ) {
        $member = new DCMM_Member( $member_id );
        
        if ( ! $member->exists() ) {
            return false;
        }

        $email_handler = DCMM_Email_Handler::get_instance();
        
        // Create context for expiration notification
        $context = "expiration_{$notification_type}";
        
        return $email_handler->send_expiration_notification( $member, $notification_type );
    }

    /**
     * Get notification windows from settings
     * 
     * @return array Array of days
     */
    public static function get_notification_windows() {
        
        $settings = get_option( 'dcmm_expiration_notification_settings', array() );
        $windows = array();
        
        if ( ! empty( $settings['notifications'] ) ) {
            foreach ( $settings['notifications'] as $type => $config ) {
                if ( ! empty( $config['enabled'] ) ) {
                    $days = self::get_days_from_notification_type( $type );
                    if ( $days !== false ) {
                        $windows[] = $days;
                    }
                }
            }
        }
        
        // Return default windows if none configured
        return ! empty( $windows ) ? $windows : self::DEFAULT_WINDOWS;
    }

    /**
     * Convert notification type to days
     * 
     * @param string $type Notification type (30_days, 7_days, etc.)
     * @return int|false Days or false if invalid
     */
    public static function get_days_from_notification_type( $type ) {
        switch ( $type ) {
            case '30_days':
                return 30;
            case '7_days':
                return 7;
            case '1_day':
                return 1;
            case 'expired':
                return 0;
            default:
                return false;
        }
    }

    /**
     * Convert days to notification type
     * 
     * @param int $days Number of days
     * @return string Notification type
     */
    public static function get_notification_type( $days ) {
        switch ( $days ) {
            case 30:
                return '30_days';
            case 7:
                return '7_days';
            case 1:
                return '1_day';
            case 0:
                return 'expired';
            default:
                return "{$days}_days";
        }
    }

    /**
     * Check if notifications are globally enabled
     * 
     * @return bool
     */
    public static function notifications_enabled() {
        $settings = get_option( 'dcmm_expiration_notification_settings', array() );
        return ! empty( $settings['enabled'] );
    }

    /**
     * Check if specific notification type is enabled
     * 
     * @param string $notification_type Type to check
     * @return bool
     */
    public static function is_notification_type_enabled( $notification_type ) {
        $settings = get_option( 'dcmm_expiration_notification_settings', array() );
        return ! empty( $settings['notifications'][$notification_type]['enabled'] );
    }

    /**
     * Clear all notifications for a member
     * Used when member cancels or renews
     * 
     * @param int $member_id Member CPT ID
     */
    public static function clear_member_notifications( $member_id ) {
        DCMM_Notification_Logger::clear_member_notifications( $member_id );
    }

    /**
     * Check if a notification was sent recently (within specified days)
     * 
     * @param int    $member_id        Member CPT ID
     * @param string $notification_type Type of notification
     * @param int    $days             Number of days to check back
     * @return bool
     */
    public static function notification_sent_recently( $member_id, $notification_type, $days = 7 ) {
        global $wpdb;
        
        $table_name = DCMM_Notification_Logger::get_table_name();
        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE member_id = %d 
             AND notification_type = %s 
             AND sent_date >= %s",
            $member_id,
            $notification_type,
            $cutoff_date
        ) );
        
        return $count > 0;
    }

    /**
     * Get notification statistics for admin dashboard
     * 
     * @return array Statistics
     */
    public static function get_notification_statistics() {
        return DCMM_Notification_Logger::get_notification_stats();
    }

    /**
     * Manual trigger for testing (admin only)
     * 
     * @param bool $force_send Force send even if already sent
     */
    public static function trigger_manual_check( $force_send = false ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( $force_send ) {
            // Temporarily disable duplicate checking for testing
            add_filter( 'dcmm_skip_notification_duplicate_check', '__return_true' );
        }

        self::process_expiration_notifications();

        if ( $force_send ) {
            remove_filter( 'dcmm_skip_notification_duplicate_check', '__return_true' );
        }

        return true;
    }
}

// Initialize the scheduler
DCMM_Expiration_Scheduler::init();