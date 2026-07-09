<?php
/**
 * Email Handler for DC Membership Plugin
 *
 * This class handles sending emails to members upon subscription and renewal.
 *
 * @package DC_Membership
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DCMM_Email_Handler Class
 * 
 * @since 1.1.0
 */
class DCMM_Email_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('dcmm_member_subscribed', array($this, 'handle_member_subscribed'), 10, 2);
    }
    
    public function handle_member_subscribed($member_id, $context) {
        $member = new DCMM_Member($member_id);

        // check if member is active
        if ( 'active' !== $member->get( 'status' ) ) {
            return false;
      }
        
        $settings = get_option('dcmm_email_settings', array());
        
        // Check the subscription context to send appropriate email
        if ($context === 'signup') {
            $this->send_welcome_email($member, $settings);
        } elseif (strpos($context, 'renew:') === 0 || strpos($context, 'manual:') === 0 || strpos($context, 'paypal') === 0) {
            $this->send_renewal_email($member, $context, $settings);
        }
    }
    
    public function send_welcome_email($member, $settings = null) {
        if ($settings === null) {
            $settings = get_option('dcmm_email_settings', array());
        }
        
        if (!$this->is_email_enabled('welcome', $settings)) {
            return false;
        }
        
        $to = $member->get('email');
        $subject = $this->get_email_subject('welcome', $settings);
        $message = $this->get_email_template('welcome', $member, $settings);
        $headers = $this->get_email_headers($settings);
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    public function send_renewal_email($member, $context, $settings = null) {
        if ($settings === null) {
            $settings = get_option('dcmm_email_settings', array());
        }
        
        if (!$this->is_email_enabled('renewal', $settings)) {
            return false;
        }
        
        $to = $member->get('email');
        $subject = $this->get_email_subject('renewal', $settings);
        $message = $this->get_email_template('renewal', $member, $settings, $context);
        $headers = $this->get_email_headers($settings);
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    public function send_manual_renewal_email($member, $send_email = true) {
        if (!$send_email) {
            return false;
        }
        
        $settings = get_option('dcmm_email_settings', array());
        
        if (!$this->is_email_enabled('renewal', $settings)) {
            return false;
        }
        
        $to = $member->get('email');
        $subject = $this->get_email_subject('renewal', $settings);
        $message = $this->get_email_template('renewal', $member, $settings, 'manual');
        $headers = $this->get_email_headers($settings);
        
        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send expiration notification email to member
     * 
     * @param DCMM_Member $member The member object
     * @param string $notification_type The type of notification (e.g., '30_days', '7_days', '1_day', 'expired')
     * @return bool True if email sent successfully, false otherwise
     * @since 1.1.0
     */
    public function send_expiration_notification($member, $notification_type) {
        $settings = get_option('dcmm_expiration_notification_settings', array());
        
        if (!$this->is_expiration_notification_enabled($notification_type, $settings)) {
            return false;
        }
        
        $to = $member->get('email');
        $subject = $this->get_expiration_notification_subject($notification_type, $settings);
        $message = $this->get_expiration_notification_template($notification_type, $member, $settings);
        $headers = $this->get_email_headers($settings);
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    private function is_email_enabled($type, $settings) {
        $enabled = isset($settings['enable_' . $type . '_emails']) ? $settings['enable_' . $type . '_emails'] : true;
        return $enabled;
    }
    
    /**
     * Check if specific expiration notification is enabled
     * 
     * @param string $notification_type The type of notification (e.g., '30_days', '7_days', '1_day', 'expired')
     * @param array $settings The email settings array
     * @return bool True if the notification is enabled, false otherwise
     * @since 1.1.0
     */
    private function is_expiration_notification_enabled($notification_type, $settings) {
        return isset($settings['notifications'][$notification_type]['enabled']) && $settings['notifications'][$notification_type]['enabled'];
    }
    
    /**
     * Get email subject with fallback to defaults
     * 
     * TODO: combine this with get_expiration_notification_subject()
     * 
     * @param string $type The type of email ('welcome' or 'renewal')
     * @param array $settings The email settings array
     * @return string The email subject
     * @since 1.1.0
     */
    private function get_email_subject($type, $settings) {
        $defaults = array(
            'welcome' => 'Welcome to Your Membership!',
            'renewal' => 'Membership Renewal Confirmation'
        );
        
        $key = $type . '_subject';
        return isset($settings[$key]) ? $settings[$key] : $defaults[$type];
    }
    
    /**
     * Get expiration notification subject with fallback to defaults
     * 
     * TODO: combine this with get_email_subject()
     * 
     * @param string $notification_type The type of notification (e.g., '30_days', '7_days', '1_day', 'expired')
     * @param array $settings The email settings array
     * @return string The email subject
     * @since 1.1.0
     */
    private function get_expiration_notification_subject($notification_type, $settings) {
        $defaults = array(
            '30_days' => 'Your membership expires in 30 days',
            '7_days' => 'Your membership expires in 7 days',
            '1_day' => 'Your membership expires tomorrow',
            'expired' => 'Your membership has expired'
        );
        
        if (isset($settings['notifications'][$notification_type]['subject'])) {
            return $settings['notifications'][$notification_type]['subject'];
        }
        
        return isset($defaults[$notification_type]) ? $defaults[$notification_type] : 'Membership Expiration Notice';
    }
    
    private function get_email_headers($settings) {
        $from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
        $from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        return $headers;
    }
    
    /**
     * Get email template with fallback to defaults
     * 
     * TODO: combine this with get_expiration_notification_template()
     * 
     * @param string $type The type of email ('welcome' or 'renewal')
     * @param DCMM_Member $member The member object
     * @param array $settings The email settings array
     * @param string $context The context of the email (e.g., 'signup', 'renew:paypal', 'manual')
     * @return string The email message
     * @since 1.1.0
     */
    private function get_email_template($type, $member, $settings, $context = '') {
        $template_key = $type . '_template';
        
        if (isset($settings[$template_key]) && !empty($settings[$template_key])) {
            $template = $settings[$template_key];
        } else {
            $template = $this->get_default_template($type);
        }
        
        return $this->replace_merge_tags($template, $member, $context);
    }
    
    /**
     * Get expiration notification template with fallback to defaults
     * 
     * TODO: combine this with get_email_template()
     * 
     * @param string $notification_type The type of notification (e.g., '30_days', '7_days', '1_day', 'expired')
     * @param DCMM_Member $member The member object
     * @param array $settings The email settings array
     * @return string The email message
     * @since 1.1.0
     */
    private function get_expiration_notification_template($notification_type, $member, $settings) {
        if (isset($settings['notifications'][$notification_type]['template']) && !empty($settings['notifications'][$notification_type]['template'])) {
            $template = $settings['notifications'][$notification_type]['template'];
        } else {
            $template = $this->get_default_expiration_template($notification_type);
        }
        
        return $this->replace_expiration_merge_tags($template, $member, $notification_type);
    }
    
    private function get_default_template($type) {
        switch ($type) {
            case 'welcome':
                return $this->get_welcome_template();
            case 'renewal':
                return $this->get_renewal_template();
            default:
                return '';
        }
    }
    
    private function get_default_expiration_template($notification_type) {
        switch ($notification_type) {
            case '30_days':
                return $this->get_30_day_expiration_template();
            case '7_days':
                return $this->get_7_day_expiration_template();
            case '1_day':
                return $this->get_1_day_expiration_template();
            case 'expired':
                return $this->get_expired_template();
            default:
                return $this->get_generic_expiration_template();
        }
    }
    
    /**
     * Default welcome email template
     * 
     * @return string The default welcome email HTML template
     * @since 1.1.0
     */
    private function get_welcome_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Welcome to Your Membership!</h2>
            
            <p>Dear {first_name},</p>
            
            <p>Welcome to our membership program! We\'re excited to have you join us.</p>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #555;">Membership Details</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Membership Start Date:</strong> {membership_start_date}</p>
                <p><strong>Status:</strong> {membership_status}</p>
            </div>
            
            <p>If you have any questions about your membership, please don\'t hesitate to contact us.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Default renewal email template
     * 
     * @return string The default renewal email HTML template
     * @since 1.1.0
     */
    private function get_renewal_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Membership Renewal Confirmation</h2>
            
            <p>Dear {first_name},</p>
            
            <p>Thank you for renewing your membership! Your membership has been successfully renewed.</p>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #555;">Renewal Details</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Renewal Date:</strong> {renewal_date}</p>
                <p><strong>Status:</strong> {membership_status}</p>
                {payment_details}
            </div>
            
            <p>Thank you for your continued membership!</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Default 30-day expiration notification template
     * 
     * @return string The default 30-day expiration email HTML template
     * @since 1.1.0
     */
    private function get_30_day_expiration_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Membership Expiration Notice</h2>
            
            <p>Dear {first_name},</p>
            
            <p>This is a friendly reminder that your membership will expire in <strong>30 days</strong> on {expiration_date}.</p>
            
            <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                <h3 style="margin-top: 0; color: #856404;">Membership Details</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Current Status:</strong> {membership_status}</p>
                <p><strong>Expiration Date:</strong> {expiration_date}</p>
            </div>
            
            <p>To continue enjoying your membership benefits, please renew your membership before it expires.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{renewal_url}" style="background-color: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Membership</a>
            </div>
            
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Default 7-day expiration notification template
     * 
     * @return string The default 7-day expiration email HTML template
     * @since 1.1.0
     */
    private function get_7_day_expiration_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Urgent: Membership Expires Soon</h2>
            
            <p>Dear {first_name},</p>
            
            <p>Your membership will expire in <strong>7 days</strong> on {expiration_date}.</p>
            
            <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                <h3 style="margin-top: 0; color: #721c24;">Action Required</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Current Status:</strong> {membership_status}</p>
                <p><strong>Expiration Date:</strong> {expiration_date}</p>
            </div>
            
            <p>Don\'t lose access to your membership benefits! Please renew your membership as soon as possible.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{renewal_url}" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Now</a>
            </div>
            
            <p>If you have any questions, please contact us immediately.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Default 1 day expiration notification template
     * 
     * @return string The default 1-day expiration email HTML template
     * @since 1.1.0
     */
    private function get_1_day_expiration_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Final Notice: Membership Expires Tomorrow</h2>
            
            <p>Dear {first_name},</p>
            
            <p><strong>Your membership expires tomorrow</strong> on {expiration_date}.</p>
            
            <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                <h3 style="margin-top: 0; color: #721c24;">Final Day to Renew</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Current Status:</strong> {membership_status}</p>
                <p><strong>Expiration Date:</strong> {expiration_date}</p>
            </div>
            
            <p>This is your final reminder to renew your membership before it expires. Act now to avoid any interruption in your membership benefits.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{renewal_url}" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Before It\'s Too Late</a>
            </div>
            
            <p>If you have any questions, please contact us immediately.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Default expired membership notification template
     * 
     * @return string The default expired membership email HTML template
     * @since 1.1.0
     */
    private function get_expired_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Membership Expired</h2>
            
            <p>Dear {first_name},</p>
            
            <p>Your membership expired on {expiration_date}.</p>
            
            <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                <h3 style="margin-top: 0; color: #721c24;">Membership Status</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Current Status:</strong> Expired</p>
                <p><strong>Expiration Date:</strong> {expiration_date}</p>
            </div>
            
            <p>Your membership benefits are no longer active. To restore your membership, please renew as soon as possible.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{renewal_url}" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Membership</a>
            </div>
            
            <p>If you have any questions about renewing your membership, please contact us.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Generic expiration notification template as a fallback
     * 
     * @return string The generic expiration email HTML template
     * @since 1.1.0
     */
    private function get_generic_expiration_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333; text-align: center;">Membership Expiration Notice</h2>
            
            <p>Dear {first_name},</p>
            
            <p>This is a reminder about your membership expiration on {expiration_date}.</p>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #555;">Membership Details</h3>
                <p><strong>Name:</strong> {full_name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Current Status:</strong> {membership_status}</p>
                <p><strong>Expiration Date:</strong> {expiration_date}</p>
                <p><strong>Days Until Expiration:</strong> {days_until_expiration}</p>
            </div>
            
            <p>To continue your membership, please renew before the expiration date.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{renewal_url}" style="background-color: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Membership</a>
            </div>
            
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            
            <p>Best regards,<br>
            {site_name}</p>
        </div>';
    }
    
    /**
     * Replace merge tags in the email template
     * 
     * TODO: combine this with replace_expiration_merge_tags()
     * 
     * @param string $template The email template with merge tags
     * @param DCMM_Member $member The member object
     * @param string $context The context of the email (e.g., 'signup', 'renew:paypal', 'manual')
     * @return string The email template with merge tags replaced
     * @since 1.1.0
     */
    private function replace_merge_tags($template, $member, $context = '') {
        $replacements = array(
            '{first_name}' => $member->get('first_name'),
            '{last_name}' => $member->get('last_name'),
            '{full_name}' => trim($member->get('first_name') . ' ' . $member->get('last_name')),
            '{email}' => $member->get('email'),
            '{membership_start_date}' => $this->format_date($member->get('start_date')),
            '{membership_status}' => ucfirst($member->get('status')),
            '{renewal_date}' => $this->format_date(current_time('Y-m-d H:i:s')),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_site_url(),
            '{payment_details}' => $this->get_payment_details($member, $context)
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Replace merge tags in the expiration notification template
     * 
     * TODO: combine this with replace_merge_tags()
     * 
     * @param string $template The email template with merge tags
     * @param DCMM_Member $member The member object
     * @param string $notification_type The type of notification (e.g., '30_days', '7_days', '1_day', 'expired')
     * @return string The email template with merge tags replaced
     * @since 1.1.0
     */
    private function replace_expiration_merge_tags($template, $member, $notification_type) {
        $expiration_date = $member->get_expiration_date();
        $days_until_expiration = $this->calculate_days_until_expiration($expiration_date);
        
        $replacements = array(
            '{first_name}' => $member->get('first_name'),
            '{last_name}' => $member->get('last_name'),
            '{full_name}' => trim($member->get('first_name') . ' ' . $member->get('last_name')),
            '{email}' => $member->get('email'),
            '{membership_status}' => ucfirst($member->get('status')),
            '{expiration_date}' => $this->format_date($expiration_date),
            '{days_until_expiration}' => $days_until_expiration,
            '{renewal_url}' => $this->get_renewal_url($member),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_site_url()
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Get payment details for renewal emails
     * 
     * @param DCMM_Member $member The member object
     * @param string $context The context of the email (e.g., 'renew:paypal', 'manual')
     * @return string The payment details HTML snippet
     * @since 1.1.0
     */
    private function get_payment_details($member, $context) {
        $payment_log = $member->get_meta('dcmm_payment_log', true);
        
        if (empty($payment_log) || !is_array($payment_log)) {
            return '';
        }
        
        $latest_payment = end($payment_log);
        
        if (!$latest_payment || !isset($latest_payment['amount'])) {
            return '';
        }
        
        $amount = '$' . number_format($latest_payment['amount'], 2);
        $gateway = isset($latest_payment['gateway']) ? ucfirst($latest_payment['gateway']) : 'Unknown';
        $transaction_id = isset($latest_payment['transaction_id']) ? $latest_payment['transaction_id'] : '';
        
        $details = '<p><strong>Amount Paid:</strong> ' . $amount . '</p>';
        $details .= '<p><strong>Payment Method:</strong> ' . $gateway . '</p>';
        
        if (!empty($transaction_id)) {
            $details .= '<p><strong>Transaction ID:</strong> ' . $transaction_id . '</p>';
        }
        
        return $details;
    }
    
    private function format_date($date) {
        if (empty($date)) {
            return 'N/A';
        }
        
        return date('F j, Y', strtotime($date));
    }
    
    /**
     * Calculate days until expiration
     * 
     * TODO: Should this be moved to the DCMM_Member class?
     * 
     * @param string $expiration_date The expiration date in Y-m-d format
     * @return string|int Number of days until expiration, 'Expired' if past, or 'N/A' if no date
     * @since 1.1.0
     */
    private function calculate_days_until_expiration($expiration_date) {
        if (empty($expiration_date)) {
            return 'N/A';
        }
        
        $now = new DateTime();
        $expiration = new DateTime($expiration_date);
        $interval = $now->diff($expiration);
        
        if ($expiration < $now) {
            return 'Expired';
        }
        
        return $interval->days;
    }
    
    /**
     * Get renewal URL for the member
     *
     * @param DCMM_Member $member The member object
     * @return string The renewal URL
     * @since 1.1.0
     */
    private function get_renewal_url($member) {

        // Get the dashboard URL from plugin settings and append the renewal action
        $renewal_url = add_query_arg('dcmm_action', 'renew', \DCMM_Settings\get_dashboard_url());
        
        return apply_filters('dcmm_renewal_url', $renewal_url, $member);
    }
    
    /**
     * Get available merge tags for emails
     * 
     * TODO: Should this be combined with get_merge_tags()?
     * 
     * @return array Associative array of merge tags and their descriptions
     * @since 1.1.0
     */
    public static function get_merge_tags() {
        return array(
            '{first_name}' => 'Member\'s first name',
            '{last_name}' => 'Member\'s last name',
            '{full_name}' => 'Member\'s full name',
            '{email}' => 'Member\'s email address',
            '{membership_start_date}' => 'Membership start date',
            '{membership_status}' => 'Current membership status',
            '{renewal_date}' => 'Date of renewal',
            '{site_name}' => 'Site name',
            '{site_url}' => 'Site URL',
            '{payment_details}' => 'Payment details (for renewals only)'
        );
    }
    
    /**
     * Get available merge tags for expiration notification emails
     * 
     * TODO: Should this be combined with get_merge_tags()?
     * 
     * @return array Associative array of merge tags and their descriptions
     * @since 1.1.0
     */
    public static function get_expiration_merge_tags() {
        return array(
            '{first_name}' => 'Member\'s first name',
            '{last_name}' => 'Member\'s last name',
            '{full_name}' => 'Member\'s full name',
            '{email}' => 'Member\'s email address',
            '{membership_status}' => 'Current membership status',
            '{expiration_date}' => 'Membership expiration date',
            '{days_until_expiration}' => 'Number of days until expiration',
            '{renewal_url}' => 'URL to renew membership',
            '{site_name}' => 'Site name',
            '{site_url}' => 'Site URL'
        );
    }
}

// Initialize the email handler
DCMM_Email_Handler::get_instance();