<?php

namespace DCMM\Premium\Integrations;

use DCMM\Premium\Premium_Feature_Interface;

/**
 * MailChimp Integration Premium Feature
 * 
 * Automatically manages MailChimp list subscriptions based on membership status.
 * Requires the DC MailChimp Signup plugin to be active.
 * 
 * @since 1.1.0
 */
class MailChimp_Integration implements Premium_Feature_Interface {
    
    /**
     * @var string Feature ID
     */
    const FEATURE_ID = 'mailchimp_integration';
    
    /**
     * @var bool Whether the feature is initialized
     */
    private $initialized = false;
    
    /**
     * Get the unique feature ID
     * 
     * @return string
     */
    public function get_feature_id() {
        return self::FEATURE_ID;
    }
    
    /**
     * Get the human-readable feature name
     * 
     * @return string
     */
    public function get_feature_name() {
        return __( 'MailChimp Integration', 'dcmm' );
    }
    
    /**
     * Get the feature description
     * 
     * @return string
     */
    public function get_feature_description() {
        return __( 'Automatically add paid members to MailChimp lists and remove them when membership lapses or is cancelled.', 'dcmm' );
    }
    
    /**
     * Check if this feature is currently enabled
     * 
     * @return bool
     */
    public function is_enabled() {
        $enabled_features = get_option( 'dcmm_premium_features_enabled', array() );
        return isset( $enabled_features[ self::FEATURE_ID ] ) && $enabled_features[ self::FEATURE_ID ];
    }
    
    /**
     * Check if feature dependencies are met
     * 
     * @return bool
     */
    public function check_dependencies() {
        // Check if MailChimp API key is configured
        $api_key = get_option( 'dcmm_mailchimp_api_key' );
        
        return ! empty( $api_key );
    }
    
    /**
     * Get dependency error messages
     * 
     * @return array
     */
    public function get_dependency_errors() {
        $errors = array();
        
        if ( ! $this->check_dependencies() ) {
            $errors[] = __( 'MailChimp API key must be configured in Premium Features settings.', 'dcmm' );
        }
        
        return $errors;
    }
    
    /**
     * Initialize the feature
     * 
     * @return bool
     */
    public function initialize() {
        if ( ! $this->check_dependencies() ) {
            return false;
        }
        
        // Hook into member status changes
        add_action( 'dcmm_member_status_changed', array( $this, 'handle_member_status_change' ), 10, 3 );
        add_action( 'dcmm_member_payment_received', array( $this, 'handle_member_payment' ), 10, 2 );
        add_action( 'dcmm_member_created', array( $this, 'handle_new_member' ), 10, 1 );
        
        $this->initialized = true;
        
        return true;
    }
    
    /**
     * Deactivate the feature
     * 
     * @return bool
     */
    public function deactivate() {
        // Remove hooks
        remove_action( 'dcmm_member_status_changed', array( $this, 'handle_member_status_change' ) );
        remove_action( 'dcmm_member_payment_received', array( $this, 'handle_member_payment' ) );
        remove_action( 'dcmm_member_created', array( $this, 'handle_new_member' ) );
        
        $this->initialized = false;
        
        return true;
    }
    
    /**
     * Get feature settings fields
     * 
     * @return array
     */
    public function get_settings_fields() {
        $fields = array();
        
        // Get available MailChimp lists if possible
        $list_options = $this->get_mailchimp_lists();
        
        $fields['dcmm_mailchimp_list_id'] = array(
            'title' => __( 'MailChimp List', 'dcmm' ),
            'type' => 'select',
            'description' => __( 'Select the MailChimp list to add paid members to.', 'dcmm' ),
            'options' => $list_options,
            'default' => '',
        );
        
        $fields['dcmm_mailchimp_remove_on_lapse'] = array(
            'title' => __( 'Remove on Membership Lapse', 'dcmm' ),
            'type' => 'checkbox',
            'description' => __( 'Remove members from MailChimp list when membership lapses or is cancelled.', 'dcmm' ),
            'default' => true,
        );
        
        $fields['dcmm_mailchimp_add_on_payment'] = array(
            'title' => __( 'Add on Payment', 'dcmm' ),
            'type' => 'checkbox',
            'description' => __( 'Add members to MailChimp list when payment is received (not just initial signup).', 'dcmm' ),
            'default' => true,
        );
        
        return $fields;
    }
    
    /**
     * Validate feature settings
     * 
     * @param array $settings
     * @return array
     */
    public function validate_settings( $settings ) {
        $validated = array();
        
        // Validate list ID
        if ( isset( $settings['dcmm_mailchimp_list_id'] ) ) {
            $validated['dcmm_mailchimp_list_id'] = sanitize_text_field( $settings['dcmm_mailchimp_list_id'] );
        }
        
        // Validate checkboxes
        $validated['dcmm_mailchimp_remove_on_lapse'] = isset( $settings['dcmm_mailchimp_remove_on_lapse'] );
        $validated['dcmm_mailchimp_add_on_payment'] = isset( $settings['dcmm_mailchimp_add_on_payment'] );
        
        return $validated;
    }
    
    /**
     * Handle member status changes
     * 
     * @param int $member_id
     * @param string $old_status
     * @param string $new_status
     */
    public function handle_member_status_change( $member_id, $old_status, $new_status ) {
        if ( ! $this->initialized ) {
            return;
        }
        
        $member = new \DCMM_Member( $member_id );
        if ( ! $member->exists() ) {
            return;
        }
        
        $list_id = get_option( 'dcmm_mailchimp_list_id' );
        if ( empty( $list_id ) ) {
            return;
        }
        
        // Add to list when becoming active/paid
        if ( in_array( $new_status, array( 'active', 'paid' ) ) && ! in_array( $old_status, array( 'active', 'paid' ) ) ) {
            $this->add_member_to_list( $member, $list_id );
        }
        
        // Remove from list when lapsing/cancelling (if setting enabled)
        if ( get_option( 'dcmm_mailchimp_remove_on_lapse', true ) ) {
            if ( in_array( $new_status, array( 'lapsed', 'cancelled', 'inactive' ) ) && in_array( $old_status, array( 'active', 'paid' ) ) ) {
                $this->remove_member_from_list( $member, $list_id );
            }
        }
    }
    
    /**
     * Handle member payment received
     * 
     * @param int $member_id
     * @param array $payment_data
     */
    public function handle_member_payment( $member_id, $payment_data ) {
        if ( ! $this->initialized || ! get_option( 'dcmm_mailchimp_add_on_payment', true ) ) {
            return;
        }
        
        $member = new \DCMM_Member( $member_id );
        if ( ! $member->exists() ) {
            return;
        }
        
        $list_id = get_option( 'dcmm_mailchimp_list_id' );
        if ( empty( $list_id ) ) {
            return;
        }
        
        $this->add_member_to_list( $member, $list_id );
    }
    
    /**
     * Handle new member creation
     * 
     * @param int $member_id
     */
    public function handle_new_member( $member_id ) {
        if ( ! $this->initialized ) {
            return;
        }
        
        $member = new \DCMM_Member( $member_id );
        if ( ! $member->exists() ) {
            return;
        }
        
        // Only add if member is immediately active/paid
        $status = $member->get_status();
        if ( ! in_array( $status, array( 'active', 'paid' ) ) ) {
            return;
        }
        
        $list_id = get_option( 'dcmm_mailchimp_list_id' );
        if ( empty( $list_id ) ) {
            return;
        }
        
        $this->add_member_to_list( $member, $list_id );
    }
    
    /**
     * Add member to MailChimp list
     * 
     * @param \DCMM_Member $member
     * @param string $list_id
     */
    private function add_member_to_list( $member, $list_id ) {
        try {
            // Include the MailChimp API class
            require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/class-mailchimp-api.php';
            
            $mailchimp_api = new \DCMM\MailChimp\MailChimp_API();
            
            $email = $member->get_email();
            $first_name = $member->get_first_name();
            $last_name = $member->get_last_name();
            
            if ( empty( $email ) ) {
                return;
            }
            
            // Use our internal API to subscribe the user with activity note
            $source = 'Membership Status: ' . ucfirst( $member->get_status() );
            $result = $mailchimp_api->subscribe_user( $list_id, $email, $first_name, $last_name, 'subscribed', $source );
            
            // Add activity note to member timeline
            if ( $result['success'] ) {
                $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
                $note = sprintf( 
                    '%s: Member added to list - Status changed to %s',
                    $plugin_name,
                    ucfirst( $member->get_status() )
                );
                $mailchimp_api->add_member_note( $list_id, $email, $note );
            }
            
        } catch ( \Exception $e ) {
            // Note: Could log exceptions here if needed for debugging
        }
    }
    
    /**
     * Remove member from MailChimp list
     * 
     * @param \DCMM_Member $member
     * @param string $list_id
     */
    private function remove_member_from_list( $member, $list_id ) {
        try {
            // Include the MailChimp API class
            require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/class-mailchimp-api.php';
            
            $mailchimp_api = new \DCMM\MailChimp\MailChimp_API();
            $email = $member->get_email();
            
            if ( empty( $email ) ) {
                return;
            }
            
            // Use our internal API to unsubscribe the user with activity note
            $reason = 'Membership ' . ucfirst( $member->get_status() );
            $result = $mailchimp_api->unsubscribe_user( $list_id, $email, $reason );
            
            // Add activity note to member timeline
            if ( $result['success'] ) {
                $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
                $note = sprintf( 
                    '%s: Member removed from list - Status changed to %s',
                    $plugin_name,
                    ucfirst( $member->get_status() )
                );
                $mailchimp_api->add_member_note( $list_id, $email, $note );
            }
            
        } catch ( \Exception $e ) {
            // Note: Could log exceptions here if needed for debugging
        }
    }
    
    /**
     * Get available MailChimp lists
     * 
     * @return array
     */
    private function get_mailchimp_lists() {
        $options = array( '' => __( 'Select a list...', 'dcmm' ) );
        
        if ( ! $this->check_dependencies() ) {
            return $options;
        }
        
        try {
            // Include the MailChimp API class
            require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/class-mailchimp-api.php';
            
            $mailchimp_api = new \DCMM\MailChimp\MailChimp_API();
            $lists = $mailchimp_api->get_lists();
            
            if ( is_array( $lists ) ) {
                foreach ( $lists as $list ) {
                    $options[ $list['id'] ] = $list['name'];
                }
            }
            
        } catch ( \Exception $e ) {
            // Note: Could log exceptions here if needed for debugging
        }
        
        return $options;
    }
}

// Register the feature with the premium manager
add_action( 'dcmm_register_premium_features', function( $premium_manager ) {
    $premium_manager->register_feature( new MailChimp_Integration() );
} );