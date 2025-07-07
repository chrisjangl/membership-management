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
    
    private function is_email_enabled($type, $settings) {
        $enabled = isset($settings['enable_' . $type . '_emails']) ? $settings['enable_' . $type . '_emails'] : true;
        return $enabled;
    }
    
    private function get_email_subject($type, $settings) {
        $defaults = array(
            'welcome' => 'Welcome to Your Membership!',
            'renewal' => 'Membership Renewal Confirmation'
        );
        
        $key = $type . '_subject';
        return isset($settings[$key]) ? $settings[$key] : $defaults[$type];
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
    
    private function get_email_template($type, $member, $settings, $context = '') {
        $template_key = $type . '_template';
        
        if (isset($settings[$template_key]) && !empty($settings[$template_key])) {
            $template = $settings[$template_key];
        } else {
            $template = $this->get_default_template($type);
        }
        
        return $this->replace_merge_tags($template, $member, $context);
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
}

// Initialize the email handler
DCMM_Email_Handler::get_instance();