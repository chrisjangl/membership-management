<?php
/**
 * Notification Logger Class
 * 
 * Handles logging of expiration notifications to prevent duplicates
 * and provide audit trail for notification sending.
 * 
 * TODO: Add admin interface to view logs and stats. 
 * TODO: have logs added to member profile in admin.
 * TODO: Add cleanup routine to remove old logs after a certain period.
 * 
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class DCMM_Notification_Logger {

    /**
     * Database table name (without prefix)
     */
    const TABLE_NAME = 'dcmm_' . 'notification_log';

    /**
     * Get the full table name with WordPress prefix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the notification log table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            member_id int(11) NOT NULL,
            notification_type varchar(20) NOT NULL,
            sent_date date NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_notification (member_id, notification_type, sent_date),
            KEY member_id (member_id),
            KEY notification_type (notification_type),
            KEY sent_date (sent_date)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Check if a notification has already been sent
     * 
     * @param int    $member_id        Member ID
     * @param string $notification_type Type of notification (30_days, 7_days, etc.)
     * @param string $sent_date        Date to check (defaults to today)
     * @return bool
     */
    public static function notification_sent( $member_id, $notification_type, $sent_date = null ) {
        global $wpdb;
        
        if ( ! $sent_date ) {
            $sent_date = date( 'Y-m-d' );
        }
        
        $table_name = self::get_table_name();
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE member_id = %d 
             AND notification_type = %s 
             AND sent_date = %s",
            $member_id,
            $notification_type,
            $sent_date
        ) );
        
        return $count > 0;
    }

    /**
     * Log a notification as sent
     * 
     * @param int    $member_id        Member ID
     * @param string $notification_type Type of notification
     * @param string $sent_date        Date notification was sent (defaults to today)
     * @return bool  Success/failure
     */
    public static function log_notification( $member_id, $notification_type, $sent_date = null ) {
        global $wpdb;
        
        if ( ! $sent_date ) {
            $sent_date = date( 'Y-m-d' );
        }
        
        $table_name = self::get_table_name();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'member_id' => $member_id,
                'notification_type' => $notification_type,
                'sent_date' => $sent_date
            ),
            array( '%d', '%s', '%s' )
        );
        
        return $result !== false;
    }

    /**
     * Get notification history for a member
     * 
     * @param int $member_id Member ID
     * @return array Array of notification records
     */
    public static function get_member_notifications( $member_id ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE member_id = %d 
             ORDER BY created_at DESC",
            $member_id
        ) );
    }

    /**
     * Clear all notifications for a member (e.g., when they cancel or renew)
     * 
     * @param int $member_id Member ID
     * @return bool Success/failure
     */
    public static function clear_member_notifications( $member_id ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $result = $wpdb->delete(
            $table_name,
            array( 'member_id' => $member_id ),
            array( '%d' )
        );
        
        return $result !== false;
    }

    /**
     * Get notification statistics
     * 
     * @return array Statistics about notifications sent
     */
    public static function get_notification_stats() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_results(
            "SELECT 
                notification_type,
                COUNT(*) as total_sent,
                COUNT(DISTINCT member_id) as unique_members,
                MAX(sent_date) as last_sent
             FROM $table_name 
             GROUP BY notification_type
             ORDER BY notification_type"
        );
    }
}