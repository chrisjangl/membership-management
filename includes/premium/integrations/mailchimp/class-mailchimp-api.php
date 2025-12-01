<?php

namespace DCMM\MailChimp;

/**
 * Internal MailChimp API Wrapper for DC Membership Plugin
 * 
 * Provides a clean interface to MailChimp API functionality
 * using Drew McLellan's MailChimp library as the foundation.
 * 
 * @since 1.1.0
 */
class MailChimp_API {
    
    /**
     * @var DCMM_MailChimp MailChimp API instance
     */
    private $mailchimp;
    
    /**
     * @var string MailChimp API key
     */
    private $api_key;
    
    /**
     * @var array Cached lists
     */
    private static $lists_cache = null;
    
    /**
     * Constructor
     * 
     * @param string $api_key MailChimp API key
     * @throws \Exception If API key is invalid
     */
    public function __construct( $api_key = null ) {
        if ( ! $api_key ) {
            $api_key = get_option( 'dcmm_mailchimp_api_key' );
        }
        
        if ( empty( $api_key ) ) {
            throw new \Exception( 'MailChimp API key is required' );
        }
        
        $this->api_key = $api_key;
        
        // Include the MailChimp library
        if ( ! class_exists( 'DCMM_MailChimp' ) ) {
            require_once DCMM_PATH . '/includes/premium/integrations/mailchimp/MailChimp.php';
        }
        
        try {
            $this->mailchimp = new \DCMM_MailChimp( $api_key );
        } catch ( \Exception $e ) {
            throw new \Exception( 'Invalid MailChimp API key: ' . $e->getMessage() );
        }
    }
    
    /**
     * Test the connection to MailChimp
     * 
     * @return array Result with success status and message
     */
    public function test_connection() {
        try {
            $result = $this->mailchimp->get( 'ping' );
            
            if ( $this->mailchimp->success() ) {
                return array(
                    'success' => true,
                    'message' => __( 'Successfully connected to MailChimp!', DCMM_PLUGIN_SLUG )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __( 'Failed to connect: ', DCMM_PLUGIN_SLUG ) . $this->mailchimp->getLastError()
                );
            }
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => __( 'Connection error: ', DCMM_PLUGIN_SLUG ) . $e->getMessage()
            );
        }
    }
    
    /**
     * Get all MailChimp lists
     * 
     * @param bool $force_refresh Force refresh of cached lists
     * @return array Array of lists or empty array on error
     */
    public function get_lists( $force_refresh = false ) {
        // Return cached lists if available and not forcing refresh
        if ( ! $force_refresh && self::$lists_cache !== null ) {
            return self::$lists_cache;
        }
        
        try {
            $result = $this->mailchimp->get( 'lists', array(
                'fields' => 'lists.id,lists.name,lists.member_count',
                'count' => 1000
            ) );
            
            if ( $this->mailchimp->success() && isset( $result['lists'] ) ) {
                self::$lists_cache = $result['lists'];
                return self::$lists_cache;
            } else {
                error_log( 'DCMM MailChimp: Failed to get lists - ' . $this->mailchimp->getLastError() );
                return array();
            }
        } catch ( \Exception $e ) {
            error_log( 'DCMM MailChimp: Exception getting lists - ' . $e->getMessage() );
            return array();
        }
    }
    
    /**
     * Get a specific list by ID
     * 
     * @param string $list_id MailChimp list ID
     * @return array|false List data or false on error
     */
    public function get_list( $list_id ) {
        try {
            $result = $this->mailchimp->get( "lists/{$list_id}" );
            
            if ( $this->mailchimp->success() ) {
                return $result;
            } else {
                error_log( "DCMM MailChimp: Failed to get list {$list_id} - " . $this->mailchimp->getLastError() );
                return false;
            }
        } catch ( \Exception $e ) {
            error_log( "DCMM MailChimp: Exception getting list {$list_id} - " . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Subscribe a user to a MailChimp list
     * 
     * @param string $list_id MailChimp list ID
     * @param string $email Email address
     * @param string $first_name First name (optional)
     * @param string $last_name Last name (optional)
     * @param string $status Subscription status (default: 'subscribed')
     * @param string $source Source of subscription for activity tracking (optional)
     * @return array Result with success status and data
     */
    public function subscribe_user( $list_id, $email, $first_name = '', $last_name = '', $status = 'subscribed', $source = '' ) {
        if ( ! $this->mailchimp->validateEmail( $email ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid email address', 'dcmm' )
            );
        }
        
        $merge_fields = array();
        if ( ! empty( $first_name ) ) {
            $merge_fields['FNAME'] = $first_name;
        }
        if ( ! empty( $last_name ) ) {
            $merge_fields['LNAME'] = $last_name;
        }
        
        // Add source to merge fields for activity tracking
        if ( ! empty( $source ) ) {
            $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
            $merge_fields['MMSOURCE'] = $plugin_name . ': ' . $source;
        }
        
        $data = array(
            'email_address' => $email,
            'status' => $status
        );
        
        if ( ! empty( $merge_fields ) ) {
            $data['merge_fields'] = $merge_fields;
        }
        
        // Add membership status tag and remove old status tags
        if ( ! empty( $source ) ) {
            $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
            $current_tag = $plugin_name . ': Active';
            
            // First, get current member to remove old tags
            $this->remove_membership_status_tags( $list_id, $email );
            
            $data['tags'] = array( $current_tag );
        }
        
        try {
            // Use PUT for upsert behavior (add or update)
            $hashed_email = $this->mailchimp->subscriberHash( $email );
            $result = $this->mailchimp->put( "lists/{$list_id}/members/{$hashed_email}", $data );
            
            if ( $this->mailchimp->success() ) {
                return array(
                    'success' => true,
                    'message' => __( 'Successfully subscribed to list', 'dcmm' ),
                    'data' => $result
                );
            } else {
                $error = $this->mailchimp->getLastError();
                return array(
                    'success' => false,
                    'message' => __( 'Failed to subscribe: ', 'dcmm' ) . $error,
                    'error' => $error
                );
            }
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => __( 'Exception during subscription: ', 'dcmm' ) . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Unsubscribe a user from a MailChimp list
     * 
     * @param string $list_id MailChimp list ID
     * @param string $email Email address
     * @param string $reason Reason for unsubscription for activity tracking (optional)
     * @return array Result with success status and message
     */
    public function unsubscribe_user( $list_id, $email, $reason = '' ) {
        if ( ! $this->mailchimp->validateEmail( $email ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid email address', 'dcmm' )
            );
        }
        
        try {
            $hashed_email = $this->mailchimp->subscriberHash( $email );
            
            $data = array( 'status' => 'unsubscribed' );
            
            // Add reason to merge fields and update status tag
            if ( ! empty( $reason ) ) {
                $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
                $data['merge_fields'] = array( 'MMSOURCE' => $plugin_name . ': ' . $reason );
                
                // Remove old status tags and add new one
                $this->remove_membership_status_tags( $list_id, $email );
                $data['tags'] = array( $plugin_name . ': ' . ucfirst( str_replace( 'Membership ', '', $reason ) ) );
            }
            
            $result = $this->mailchimp->patch( "lists/{$list_id}/members/{$hashed_email}", $data );
            
            if ( $this->mailchimp->success() ) {
                return array(
                    'success' => true,
                    'message' => __( 'Successfully unsubscribed from list', 'dcmm' ),
                    'data' => $result
                );
            } else {
                $error = $this->mailchimp->getLastError();
                return array(
                    'success' => false,
                    'message' => __( 'Failed to unsubscribe: ', 'dcmm' ) . $error,
                    'error' => $error
                );
            }
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => __( 'Exception during unsubscribe: ', 'dcmm' ) . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check if a user is subscribed to a list
     * 
     * @param string $list_id MailChimp list ID
     * @param string $email Email address
     * @return array Result with subscription status
     */
    public function is_subscribed( $list_id, $email ) {
        if ( ! $this->mailchimp->validateEmail( $email ) ) {
            return array(
                'subscribed' => false,
                'status' => 'invalid_email',
                'message' => __( 'Invalid email address', 'dcmm' )
            );
        }
        
        try {
            $hashed_email = $this->mailchimp->subscriberHash( $email );
            $result = $this->mailchimp->get( "lists/{$list_id}/members/{$hashed_email}" );
            
            if ( $this->mailchimp->success() ) {
                $status = isset( $result['status'] ) ? $result['status'] : 'unknown';
                return array(
                    'subscribed' => ( $status === 'subscribed' ),
                    'status' => $status,
                    'message' => sprintf( __( 'Member status: %s', 'dcmm' ), $status ),
                    'data' => $result
                );
            } else {
                // If member not found, they're not subscribed
                return array(
                    'subscribed' => false,
                    'status' => 'not_found',
                    'message' => __( 'Email not found on list', 'dcmm' )
                );
            }
        } catch ( \Exception $e ) {
            return array(
                'subscribed' => false,
                'status' => 'error',
                'message' => __( 'Error checking subscription: ', 'dcmm' ) . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get the last error from MailChimp API
     * 
     * @return string|false Last error or false if none
     */
    public function get_last_error() {
        return $this->mailchimp ? $this->mailchimp->getLastError() : false;
    }
    
    /**
     * Check if the last request was successful
     * 
     * @return bool True if successful, false otherwise
     */
    public function success() {
        return $this->mailchimp ? $this->mailchimp->success() : false;
    }
    
    /**
     * Clear the lists cache
     */
    public static function clear_lists_cache() {
        self::$lists_cache = null;
    }
    
    /**
     * Remove membership status tags from a member
     * 
     * @param string $list_id MailChimp list ID
     * @param string $email Email address
     */
    private function remove_membership_status_tags( $list_id, $email ) {
        try {
            $plugin_name = defined( 'DCMM_PLUGIN_NAME' ) ? DCMM_PLUGIN_NAME : 'DC Membership';
            $hashed_email = $this->mailchimp->subscriberHash( $email );
            
            // Get current member data to see existing tags
            $member = $this->mailchimp->get( "lists/{$list_id}/members/{$hashed_email}" );
            
            if ( $this->mailchimp->success() && isset( $member['tags'] ) ) {
                $tags_to_remove = array();
                
                foreach ( $member['tags'] as $tag ) {
                    $tag_name = isset( $tag['name'] ) ? $tag['name'] : '';
                    // Remove any tags that start with our plugin name
                    if ( strpos( $tag_name, $plugin_name . ':' ) === 0 ) {
                        $tags_to_remove[] = $tag_name;
                    }
                }
                
                // Remove the old tags
                if ( ! empty( $tags_to_remove ) ) {
                    foreach ( $tags_to_remove as $tag_name ) {
                        $this->mailchimp->post( "lists/{$list_id}/members/{$hashed_email}/tags", array(
                            'tags' => array(
                                array(
                                    'name' => $tag_name,
                                    'status' => 'inactive'
                                )
                            )
                        ) );
                    }
                }
            }
        } catch ( \Exception $e ) {
            // Note: Could log exceptions here if needed for debugging
        }
    }
    
    /**
     * Add a note to a MailChimp list member
     * 
     * @param string $list_id MailChimp list ID
     * @param string $email Email address
     * @param string $note Note content
     * @return array Result with success status and message
     */
    public function add_member_note( $list_id, $email, $note ) {
        if ( ! $this->mailchimp->validateEmail( $email ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid email address', 'dcmm' )
            );
        }
        
        if ( empty( $note ) ) {
            return array(
                'success' => false,
                'message' => __( 'Note content is required', 'dcmm' )
            );
        }
        
        try {
            $hashed_email = $this->mailchimp->subscriberHash( $email );
            $result = $this->mailchimp->post( "lists/{$list_id}/members/{$hashed_email}/notes", array(
                'note' => $note
            ) );
            
            if ( $this->mailchimp->success() ) {
                return array(
                    'success' => true,
                    'message' => __( 'Note added successfully', 'dcmm' ),
                    'data' => $result
                );
            } else {
                $error = $this->mailchimp->getLastError();
                return array(
                    'success' => false,
                    'message' => __( 'Failed to add note: ', 'dcmm' ) . $error,
                    'error' => $error
                );
            }
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'message' => __( 'Exception adding note: ', 'dcmm' ) . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get the MailChimp API instance for advanced usage
     * 
     * @return DCMM_MailChimp MailChimp API instance
     */
    public function get_api_instance() {
        return $this->mailchimp;
    }
}